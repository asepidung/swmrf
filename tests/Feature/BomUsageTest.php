<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BoningResource\Pages\MaterialUsageBoning;
use App\Filament\Admin\Resources\RepackResource\Pages\MaterialUsageRepack;
use App\Models\Boning;
use App\Models\BoningItem;
use App\Models\Grade;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStock;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMaterial;
use App\Models\Repack;
use App\Models\RepackResult;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BomUsageCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Issue #344 (susulan): BOM -> pemakaian bahan per Boning/Repack.
 *
 * BOM MENGUSULKAN, manusia MEMUTUSKAN -- keputusan Owner 7 September
 * 2026 (`.agents/hpp.md` §3): potongan stok tetap dilakukan manual per
 * box/ikat, bukan otomatis, karena menghitung satuan kecil (plastik,
 * lembar) di lapangan tidak mungkin. Tombol "Fill from BOM" hanya
 * MENGISI Repeater `MaterialUsage`-nya; menyimpan tetap lewat tombol
 * Save biasa, dan qty-nya masih bisa diedit lebih dulu.
 */
class BomUsageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Grade $grade;

    private Product $topside;

    private Product $silverside;

    private Product $bone;

    private Material $karton;

    private Material $plastik;

    private Material $drylog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (['view_bonings', 'edit_bonings', 'view_repacks', 'edit_repacks'] as $permission) {
            $this->user->permissions()->attach(
                Permission::firstOrCreate(['name' => $permission], ['module_name' => 'Fixture', 'description' => $permission])->id
            );
        }
        $this->user = $this->user->fresh();
        $this->actingAs($this->user);

        $this->warehouse = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        $this->topside = Product::create([
            'name' => 'TOPSIDE', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->silverside = Product::create([
            'name' => 'SILVERSIDE', 'code' => 'MT002', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->bone = Product::create([
            'name' => 'BONE', 'code' => 'MT003', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);

        $materialCategory = MaterialCategory::create(['name' => 'PACKAGING']);
        $materialUnit = MaterialUnit::create(['name' => 'PCS']);

        $this->karton = Material::create([
            'code' => 'MAT-KARTON', 'name' => 'KARTON', 'material_category_id' => $materialCategory->id,
            'material_unit_id' => $materialUnit->id, 'min_stock' => 0, 'is_active' => true,
        ]);
        $this->plastik = Material::create([
            'code' => 'MAT-PLASTIK', 'name' => 'PLASTIK VAKUM', 'material_category_id' => $materialCategory->id,
            'material_unit_id' => $materialUnit->id, 'min_stock' => 0, 'is_active' => true,
        ]);
        $this->drylog = Material::create([
            'code' => 'MAT-DRYLOG', 'name' => 'DRYLOG', 'material_category_id' => $materialCategory->id,
            'material_unit_id' => $materialUnit->id, 'min_stock' => 0, 'is_active' => true,
        ]);

        // TOPSIDE: 1 karton per box, 2 plastik per pcs, drylog jumlahnya
        // tidak tetap (harus dilewati, bukan dihitung 0).
        ProductMaterial::create(['product_id' => $this->topside->id, 'material_id' => $this->karton->id, 'quantity' => 1, 'basis' => 'box']);
        ProductMaterial::create(['product_id' => $this->topside->id, 'material_id' => $this->plastik->id, 'quantity' => 2, 'basis' => 'piece']);
        ProductMaterial::create(['product_id' => $this->topside->id, 'material_id' => $this->drylog->id, 'quantity' => null, 'basis' => 'piece']);

        // SILVERSIDE: sama material KARTON -- membuktikan penjumlahan
        // LINTAS PRODUK untuk material yang sama.
        ProductMaterial::create(['product_id' => $this->silverside->id, 'material_id' => $this->karton->id, 'quantity' => 1, 'basis' => 'box']);

        // BONE sengaja TANPA baris BOM sama sekali.

        MaterialStock::create(['material_id' => $this->karton->id, 'qty' => 1000]);
        MaterialStock::create(['material_id' => $this->plastik->id, 'qty' => 1000]);
    }

    private function boningWithItems(): Boning
    {
        $boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id]);

        // TOPSIDE: 3 box, pcs 5+4+3 = 12.
        foreach ([5, 4, 3] as $i => $pcs) {
            BoningItem::create([
                'boning_id' => $boning->id, 'product_id' => $this->topside->id,
                'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
                'weight' => 10, 'qty_pcs' => $pcs, 'pack_date' => now()->toDateString(),
                'barcode' => 'BC-TOP-'.$i, 'created_by' => $this->user->id,
            ]);
        }

        // SILVERSIDE: 2 box, pcs 6+4 = 10.
        foreach ([6, 4] as $i => $pcs) {
            BoningItem::create([
                'boning_id' => $boning->id, 'product_id' => $this->silverside->id,
                'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
                'weight' => 8, 'qty_pcs' => $pcs, 'pack_date' => now()->toDateString(),
                'barcode' => 'BC-SIL-'.$i, 'created_by' => $this->user->id,
            ]);
        }

        // BONE: 1 box, tanpa BOM.
        BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $this->bone->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => 5, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(),
            'barcode' => 'BC-BONE-1', 'created_by' => $this->user->id,
        ]);

        return $boning->fresh();
    }

    // =========================================================================
    // BomUsageCalculator (murni)
    // =========================================================================

    /** @test */
    public function it_aggregates_usage_across_products_using_box_and_piece_basis(): void
    {
        $boning = $this->boningWithItems();

        $result = BomUsageCalculator::calculate($boning->items);

        // KARTON: TOPSIDE 3 box x 1 + SILVERSIDE 2 box x 1 = 5.
        $this->assertSame(5.0, (float) $result['usage'][$this->karton->id]);
        // PLASTIK: TOPSIDE 12 pcs x 2 = 24.
        $this->assertSame(24.0, (float) $result['usage'][$this->plastik->id]);
        $this->assertArrayNotHasKey($this->drylog->id, $result['usage']);
    }

    /** @test */
    public function a_bom_row_with_null_quantity_is_skipped_but_reported(): void
    {
        $boning = $this->boningWithItems();

        $result = BomUsageCalculator::calculate($boning->items);

        $this->assertCount(1, $result['skipped']);
        $this->assertSame($this->drylog->id, $result['skipped'][0]['material_id']);
        $this->assertSame('TOPSIDE', $result['skipped'][0]['product_name']);
    }

    /** @test */
    public function a_product_without_any_bom_row_is_reported(): void
    {
        $boning = $this->boningWithItems();

        $result = BomUsageCalculator::calculate($boning->items);

        $this->assertCount(1, $result['without_bom']);
        $this->assertSame('BONE', $result['without_bom'][0]['product_name']);
    }

    /** @test */
    public function box_and_pcs_are_reported_per_product(): void
    {
        $boning = $this->boningWithItems();

        $result = BomUsageCalculator::calculate($boning->items);

        $topside = collect($result['products'])->firstWhere('product_id', $this->topside->id);
        $this->assertSame(3, $topside['box']);
        $this->assertSame(12, $topside['pcs']);

        $silverside = collect($result['products'])->firstWhere('product_id', $this->silverside->id);
        $this->assertSame(2, $silverside['box']);
        $this->assertSame(10, $silverside['pcs']);
    }

    // =========================================================================
    // Halaman MaterialUsageBoning
    // =========================================================================

    /** @test */
    public function fill_from_bom_populates_the_repeater_without_removing_a_manual_row(): void
    {
        $boning = $this->boningWithItems();

        $state = Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm([
                'materialUsages' => [
                    'manual-1' => ['material_id' => $this->drylog->id, 'qty' => 7, 'note' => 'manual, dari BOM tidak tetap'],
                ],
            ])
            ->callAction('fill_from_bom')
            ->assertHasNoActionErrors()
            ->get('data.materialUsages');

        $byMaterial = collect($state)->keyBy('material_id');

        // Baris manual (DRYLOG) tetap ada dengan qty aslinya.
        $this->assertSame(7, (int) $byMaterial[$this->drylog->id]['qty']);
        // Baris hasil BOM ditambahkan.
        $this->assertSame(5, (int) $byMaterial[$this->karton->id]['qty']);
        $this->assertSame(24, (int) $byMaterial[$this->plastik->id]['qty']);
    }

    /** @test */
    public function fill_from_bom_overwrites_an_existing_row_for_the_same_material_instead_of_duplicating(): void
    {
        $boning = $this->boningWithItems();

        $state = Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm([
                'materialUsages' => [
                    'existing-karton' => ['material_id' => $this->karton->id, 'qty' => 999, 'note' => null],
                ],
            ])
            ->callAction('fill_from_bom')
            ->get('data.materialUsages');

        // Baris KARTON yang sudah ada DITIMPA (kunci array-nya tetap sama),
        // bukan ditambah baris baru di sebelahnya -- totalnya 2 (karton +
        // plastik yang baru), bukan 3.
        $this->assertCount(2, $state);
        $this->assertSame(5, (int) $state['existing-karton']['qty']);
    }

    /** @test */
    public function saving_after_fill_from_bom_deducts_stock_by_the_computed_amount(): void
    {
        $boning = $this->boningWithItems();

        $state = Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->callAction('fill_from_bom')
            ->get('data.materialUsages');

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm(['materialUsages' => $state])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1000 - 5.0, (float) MaterialStock::where('material_id', $this->karton->id)->value('qty'));
        $this->assertSame(1000 - 24.0, (float) MaterialStock::where('material_id', $this->plastik->id)->value('qty'));
    }

    // =========================================================================
    // Halaman MaterialUsageRepack -- struktur `repack_results` sama persis
    // dengan `boning_items` (satu baris = satu box, `qty_pcs` isinya).
    // =========================================================================

    /** @test */
    public function fill_from_bom_works_for_repack_using_repack_results(): void
    {
        $repack = Repack::create(['repack_date' => now()->toDateString(), 'created_by' => $this->user->id]);

        foreach ([5, 4, 3] as $i => $pcs) {
            RepackResult::create([
                'repack_id' => $repack->id, 'product_id' => $this->topside->id,
                'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
                'weight' => 10, 'qty_pcs' => $pcs, 'pack_date' => now()->toDateString(),
                'barcode' => 'BC-RPK-'.$i,
            ]);
        }

        $state = Livewire::test(MaterialUsageRepack::class, ['record' => $repack->getRouteKey()])
            ->callAction('fill_from_bom')
            ->get('data.materialUsages');

        $byMaterial = collect($state)->keyBy('material_id');

        $this->assertSame(3, (int) $byMaterial[$this->karton->id]['qty']);
        $this->assertSame(24, (int) $byMaterial[$this->plastik->id]['qty']);
    }
}
