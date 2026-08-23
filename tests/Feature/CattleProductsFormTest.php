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
     * Owner ingin kursor mendarat langsung di Beef Name saat membuat produk.
     *
     * Catatan: urutan Tab pada form ini masih belum wajar secara keseluruhan
     * dan sudah dicatat sebagai utang teknis di .agents/agents.md.
     *
     * @test
     */
    public function it_autofocuses_the_beef_name_field()
    {
        $page = Livewire::actingAs($this->user)->test(CreateProduct::class)->instance();

        $fields = ProductResource::form(\Filament\Forms\Form::make($page))
            ->getComponents()[0]
            ->getChildComponents();

        $autofocused = [];
        foreach ($fields as $field) {
            if ($field->isAutofocused()) {
                $autofocused[] = $field->getName();
            }
        }

        $this->assertSame(['name'], $autofocused);
    }

    /**
     * Produk yang baru dibuat sudah pasti aktif, jadi toggle ini hanya bikin
     * satu perhentian Tab yang tidak perlu. Kolomnya sendiri sudah default
     * aktif di database.
     *
     * @test
     */
    public function it_hides_the_active_toggle_on_create_but_keeps_it_on_edit()
    {
        Livewire::actingAs($this->user)
            ->test(CreateProduct::class)
            ->assertFormFieldIsHidden('is_active');

        $category = ProductCategory::create(['name' => 'PRIME CUTS', 'prefix' => 1]);
        $product = \App\Models\Product::create([
            'name' => 'TENDERLOIN',
            'code' => 'MT00100',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Filament\Clusters\ProductsCluster\Resources\ProductResource\Pages\EditProduct::class, ['record' => $product->id])
            ->assertFormFieldExists('is_active');
    }

    /** @test */
    public function it_creates_a_product_as_active_even_though_the_toggle_is_hidden()
    {
        $category = ProductCategory::create(['name' => 'PRIME CUTS', 'prefix' => 1]);

        Livewire::actingAs($this->user)
            ->test(CreateProduct::class)
            ->fillForm([
                'structure_type' => 'main',
                'category_id' => $category->id,
                'name' => 'tenderloin',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = \App\Models\Product::where('name', 'TENDERLOIN')->first();

        $this->assertNotNull($product);
        $this->assertTrue((bool) $product->is_active, 'Produk baru wajib langsung aktif.');
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
