<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\GradeResource;
use App\Filament\Clusters\ProductsCluster\Resources\ProductCategoryResource;
use App\Filament\Clusters\ProductsCluster\Resources\ProductCategoryResource\Pages\CreateProductCategory;
use App\Filament\Clusters\ProductsCluster\Resources\ProductResource;
use App\Filament\Clusters\ProductsCluster\Resources\ProductResource\Pages\CreateProduct;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CattleProductsFormTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer',
            'username' => 'programmer_cattle',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);
    }

    /**
     * Grade diletakkan paling bawah dalam cluster karena isinya paling jarang
     * berubah, sementara Beef dan Beef Categories jauh lebih sering dibuka.
     *
     * @test
     */
    public function it_orders_grade_last_within_the_cattle_products_cluster()
    {
        $this->assertSame(1, ProductResource::getNavigationSort());
        $this->assertSame(2, ProductCategoryResource::getNavigationSort());
        $this->assertSame(3, GradeResource::getNavigationSort());

        $this->assertGreaterThan(
            ProductCategoryResource::getNavigationSort(),
            GradeResource::getNavigationSort(),
            'Grade harus berada di bawah Beef Categories.'
        );
    }

    /**
     * autofocus wajib berada di field PERTAMA. Sebelumnya autofocus dipasang di
     * field 'name' yang berada di urutan keempat, sehingga menekan Tab dari
     * sana melewatkan tiga field di atasnya dan langsung menuju tombol.
     *
     * @test
     */
    public function it_autofocuses_the_first_field_so_tab_sweeps_every_field_before_the_buttons()
    {
        $page = Livewire::actingAs($this->user)->test(CreateProduct::class)->instance();

        $schema = ProductResource::form(\Filament\Forms\Form::make($page))->getComponents();

        $section = $schema[0];
        $fields = $section->getChildComponents();

        $this->assertSame('structure_type', $fields[0]->getName());
        $this->assertTrue($fields[0]->isAutofocused(), 'Field pertama wajib autofocus.');

        foreach ($fields as $field) {
            if ($field->getName() !== 'structure_type') {
                $this->assertFalse(
                    $field->isAutofocused(),
                    "Hanya field pertama yang boleh autofocus, tapi '{$field->getName()}' ikut autofocus."
                );
            }
        }
    }

    /** @test */
    public function it_suggests_the_next_prefix_on_the_category_page()
    {
        ProductCategory::create(['name' => 'PRIME CUTS', 'prefix' => 1]);

        Livewire::actingAs($this->user)
            ->test(CreateProductCategory::class)
            ->assertFormSet(['prefix' => 2]);
    }

    /** @test */
    public function it_suggests_prefix_one_when_no_category_exists_yet()
    {
        Livewire::actingAs($this->user)
            ->test(CreateProductCategory::class)
            ->assertFormSet(['prefix' => 1]);
    }

    /** @test */
    public function it_suggests_the_next_prefix_after_the_highest_one_not_after_the_newest()
    {
        ProductCategory::create(['name' => 'A', 'prefix' => 1]);
        ProductCategory::create(['name' => 'B', 'prefix' => 7]);
        ProductCategory::create(['name' => 'C', 'prefix' => 3]);

        Livewire::actingAs($this->user)
            ->test(CreateProductCategory::class)
            ->assertFormSet(['prefix' => 8]);
    }

    /** @test */
    public function it_still_accepts_a_manually_chosen_prefix()
    {
        ProductCategory::create(['name' => 'PRIME CUTS', 'prefix' => 1]);

        Livewire::actingAs($this->user)
            ->test(CreateProductCategory::class)
            ->fillForm(['name' => 'offal', 'prefix' => 9])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('product_categories', ['name' => 'OFFAL', 'prefix' => 9]);
    }

    /** @test */
    public function it_rejects_a_prefix_that_is_already_taken()
    {
        ProductCategory::create(['name' => 'PRIME CUTS', 'prefix' => 1]);

        Livewire::actingAs($this->user)
            ->test(CreateProductCategory::class)
            ->fillForm(['name' => 'OFFAL', 'prefix' => 1])
            ->call('create')
            ->assertHasFormErrors(['prefix']);
    }
}
