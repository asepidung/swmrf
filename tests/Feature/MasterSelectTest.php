<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\CreateMaterialRequisition;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\CreateProductRequisition;
use App\Filament\Support\MasterSelect;
use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Issue #512: dropdown ke master yang terus bertambah mencari ke SERVER.
 *
 * Keluhan Owner: item yang dibuat di tab lain tidak muncul di dropdown form yang
 * sudah terbuka, kecuali halamannya di-refresh -- dan itu berarti mengisi ulang
 * form dari awal. Yang dibuktikan di sini: item yang lahir SESUDAH form dibuka
 * tetap ditemukan lewat pencarian.
 */
class MasterSelectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer', 'username' => 'prog_'.uniqid(), 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'programmer', 'is_active' => true,
        ]);
    }

    private function material(string $name, bool $active = true): Material
    {
        return Material::create([
            'name' => $name,
            'material_category_id' => MaterialCategory::firstOrCreate(['name' => 'PACKAGING'])->id,
            'material_unit_id' => MaterialUnit::firstOrCreate(['name' => 'PCS'])->id,
            'min_stock' => 0,
            'is_active' => $active,
        ]);
    }

    private function product(string $name, bool $active = true): Product
    {
        return Product::create([
            'code' => (string) random_int(100000, 999999), 'name' => $name,
            'category_id' => ProductCategory::firstOrCreate(['name' => 'DAGING'], ['prefix' => 1])->id,
            'structure_type' => 'main', 'is_active' => $active,
        ]);
    }

    private function supplier(string $name, bool $active = true): Supplier
    {
        return Supplier::create(['name' => $name, 'address' => 'Bogor', 'pic' => 'A', 'top_days' => 30, 'is_active' => $active]);
    }

    /** Field dengan nama tertentu di form sebuah halaman Create. */
    private function field($test, string $name): Select
    {
        return $test->instance()
            ->getForm('form')
            ->getFlatComponents()[$this->pathOf($test, $name)] ?? $this->fail("Field {$name} tidak ada di form.");
    }

    private function pathOf($test, string $name): string
    {
        foreach ($test->instance()->getForm('form')->getFlatComponents() as $path => $component) {
            if (method_exists($component, 'getName') && $component->getName() === $name) {
                return $path;
            }
        }

        return $this->fail("Field {$name} tidak ada di form.");
    }

    // ---------------------------------------------------------------------
    // Pencarian (helper)
    // ---------------------------------------------------------------------

    /** @test */
    public function the_search_is_case_insensitive_and_matches_the_code_as_well_as_the_name(): void
    {
        $material = $this->material('PLASTIK VAKUM');

        $this->assertSame([$material->id => 'PLASTIK VAKUM'], MasterSelect::search(Material::class, 'plastik'));
        $this->assertSame([$material->id => 'PLASTIK VAKUM'], MasterSelect::search(Material::class, 'VAKUM'));
        $this->assertSame([$material->id => 'PLASTIK VAKUM'], MasterSelect::search(Material::class, strtolower($material->code)));
        $this->assertSame([], MasterSelect::search(Material::class, 'tidak ada'));
    }

    /** @test */
    public function supplier_and_customer_search_by_name_even_though_they_have_no_code_column(): void
    {
        $supplier = $this->supplier('TEGUH AMANAH');

        $this->assertSame([$supplier->id => 'TEGUH AMANAH'], MasterSelect::search(Supplier::class, 'teguh'));
        $this->assertSame([], MasterSelect::search(Customer::class, 'teguh'));
    }

    /** @test */
    public function the_results_are_limited_ordered_by_name_and_exclude_inactive_ones_for_new_choices(): void
    {
        foreach (range(1, MasterSelect::LIMIT + 5) as $i) {
            $this->material(sprintf('BAHAN %03d', $i));
        }
        $this->material('BAHAN MATI', active: false);

        $results = MasterSelect::search(Material::class, 'bahan');

        $this->assertCount(MasterSelect::LIMIT, $results);
        $this->assertSame('BAHAN 001', array_values($results)[0]);
        $this->assertNotContains('BAHAN MATI', $results);
    }

    /** @test */
    public function a_percent_or_underscore_typed_by_the_user_is_not_a_wildcard(): void
    {
        $this->material('PLASTIK 100%');
        $this->material('PLASTIK BIASA');

        $this->assertCount(1, MasterSelect::search(Material::class, '100%'));
        $this->assertCount(0, MasterSelect::search(Material::class, '%%%%x'));
    }

    /** @test */
    public function the_label_of_a_saved_value_survives_the_record_becoming_inactive(): void
    {
        $material = $this->material('BAHAN LAMA', active: false);

        $this->assertSame('BAHAN LAMA', MasterSelect::label(Material::class, $material->id));
        $this->assertNull(MasterSelect::label(Material::class, null));
        $this->assertNull(MasterSelect::label(Material::class, 999999));
    }

    // ---------------------------------------------------------------------
    // Form Request Material dan Request Beef: item yang lahir SESUDAH form dibuka
    // ---------------------------------------------------------------------

    /** @test */
    public function a_material_created_after_the_request_form_opened_is_found_by_the_item_dropdown(): void
    {
        $this->actingAs($this->user);

        $test = Livewire::test(CreateMaterialRequisition::class)
            ->fillForm(['items' => [['material_id' => null, 'qty' => 1]]]);

        $this->material('KARTON BARU DARI TAB LAIN');   // dibuat sesudah form dibuka

        $field = $this->field($test, 'material_id');

        $this->assertContains('KARTON BARU DARI TAB LAIN', $field->getSearchResults('karton baru'));
    }

    /** @test */
    public function a_product_created_after_the_request_form_opened_is_found_by_the_item_dropdown(): void
    {
        $this->actingAs($this->user);

        $test = Livewire::test(CreateProductRequisition::class)
            ->fillForm(['items' => [['product_id' => null, 'qty' => 1]]]);

        $this->product('TENDERLOIN BARU');

        $field = $this->field($test, 'product_id');

        $this->assertContains('TENDERLOIN BARU', $field->getSearchResults('tenderloin'));
    }

    /** @test */
    public function a_supplier_created_after_the_request_form_opened_is_found_and_inactive_ones_are_not(): void
    {
        $this->actingAs($this->user);

        $test = Livewire::test(CreateMaterialRequisition::class);

        $this->supplier('PEMASOK BARU');
        $this->supplier('PEMASOK MATI', active: false);

        $field = $this->field($test, 'supplier_id');

        $this->assertContains('PEMASOK BARU', $field->getSearchResults('pemasok'));
        $this->assertNotContains('PEMASOK MATI', $field->getSearchResults('pemasok'));
    }

    /** @test */
    public function the_dropdowns_no_longer_preload_everything_at_page_open(): void
    {
        $this->actingAs($this->user);
        $this->material('BAHAN A');

        $test = Livewire::test(CreateMaterialRequisition::class)
            ->fillForm(['items' => [['material_id' => null, 'qty' => 1]]]);

        $this->assertSame([], $this->field($test, 'material_id')->getOptions(), 'Pilihan tidak boleh dimuat sekaligus saat halaman dibuka.');
    }

    // ---------------------------------------------------------------------
    // Repeater: satu item satu baris
    // ---------------------------------------------------------------------

    /** @test */
    public function the_same_material_cannot_be_picked_in_two_rows_of_the_request(): void
    {
        $this->actingAs($this->user);
        $material = $this->material('KARTON TOP');
        $supplier = $this->supplier('PEMASOK');

        Livewire::test(CreateMaterialRequisition::class)
            ->fillForm([
                'due_date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'items' => [
                    ['material_id' => $material->id, 'qty' => 1, 'price' => 100],
                    ['material_id' => $material->id, 'qty' => 2, 'price' => 100],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, \App\Models\MaterialRequisition::count());
    }

    /** @test */
    public function in_the_search_results_a_material_already_picked_in_a_sibling_row_is_disabled(): void
    {
        $this->actingAs($this->user);
        $picked = $this->material('KARTON TOP');
        $this->material('KARTON BOTTOM');

        $test = Livewire::test(CreateMaterialRequisition::class)
            ->fillForm(['items' => [
                ['material_id' => $picked->id, 'qty' => 1],
                ['material_id' => null, 'qty' => 1],
            ]]);

        // Baris kedua: hasil pencarian menandai bahan yang sudah dipilih di baris pertama.
        $second = collect($test->instance()->getForm('form')->getFlatComponents())
            ->filter(fn ($component, $path) => method_exists($component, 'getName') && $component->getName() === 'material_id')
            ->values()
            ->get(1);

        $js = collect($second->getSearchResultsForJs('karton'))->keyBy('value');

        $this->assertTrue($js[(string) $picked->id]['disabled'], 'Bahan yang sudah dipilih di baris lain harus dinonaktifkan.');
    }
}
