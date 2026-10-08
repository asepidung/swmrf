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
use App\Models\MaterialStockMovement;
use App\Models\MaterialUsage;
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
 * Sejak #509 (Owner, 7 Oktober 2026) halaman pemakaian material Boning/Repack
 * hanya MENAMPILKAN kebutuhan bahan menurut BOM -- otomatis, terkunci, dan
 * TIDAK memotong stok. Tombol "Fill from BOM" (#485) sudah dibuang. Potongan
 * stok tetap manual lewat Material Usage > Create Manual Usage.
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
    public function a_sack_for_bone_is_a_per_box_row_and_counts_one_per_label(): void
    {
        // Bone dikemas karung, bukan box (Owner, 7 Oktober 2026): satu label
        // satu karung, jadi karung diisi `Per Box` dan jumlahnya jumlah label.
        $karung = Material::create([
            'code' => 'MAT-KARUNG', 'name' => 'KARUNG', 'material_category_id' => $this->karton->material_category_id,
            'material_unit_id' => $this->karton->material_unit_id, 'min_stock' => 0, 'is_active' => true,
        ]);
        ProductMaterial::create(['product_id' => $this->bone->id, 'material_id' => $karung->id, 'quantity' => 1, 'basis' => 'box']);

        $result = BomUsageCalculator::calculate($this->boningWithItems()->items);

        $this->assertSame(1.0, (float) $result['usage'][$karung->id]);
        $this->assertSame([], $result['without_bom']);
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

    /** @test */
    public function a_tenderloin_with_three_labels_needs_the_expected_materials(): void
    {
        // Contoh dari #509: 3 label (5, 4, 6 pcs) -> cryovac per pcs = 15,
        // linier per box = 3, karton tutup dan bawah per box = 3 masing-masing.
        $tenderloin = Product::create([
            'code' => '900001', 'name' => 'TENDERLOIN', 'category_id' => $this->topside->category_id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $material = fn (string $name): Material => Material::create([
            'code' => 'M-'.$name, 'name' => $name, 'material_category_id' => $this->karton->material_category_id,
            'material_unit_id' => $this->karton->material_unit_id, 'min_stock' => 0, 'is_active' => true,
        ]);
        $cryovac = $material('CRYOVAC');
        $linier = $material('LINIER');
        $tutup = $material('KARTON TUTUP');
        $bawah = $material('KARTON BAWAH');
        ProductMaterial::create(['product_id' => $tenderloin->id, 'material_id' => $cryovac->id, 'quantity' => 1, 'basis' => 'piece']);
        ProductMaterial::create(['product_id' => $tenderloin->id, 'material_id' => $linier->id, 'quantity' => 1, 'basis' => 'box']);
        ProductMaterial::create(['product_id' => $tenderloin->id, 'material_id' => $tutup->id, 'quantity' => 1, 'basis' => 'box']);
        ProductMaterial::create(['product_id' => $tenderloin->id, 'material_id' => $bawah->id, 'quantity' => 1, 'basis' => 'box']);

        $boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        foreach ([5, 4, 6] as $i => $pcs) {
            BoningItem::create([
                'boning_id' => $boning->id, 'product_id' => $tenderloin->id,
                'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
                'weight' => 10, 'qty_pcs' => $pcs, 'pack_date' => now()->toDateString(),
                'barcode' => 'BC-TEN-'.$i, 'created_by' => $this->user->id,
            ]);
        }

        $usage = BomUsageCalculator::calculate($boning->fresh()->items)['usage'];

        $this->assertSame(15, (int) $usage[$cryovac->id]);
        $this->assertSame(3, (int) $usage[$linier->id]);
        $this->assertSame(3, (int) $usage[$tutup->id]);
        $this->assertSame(3, (int) $usage[$bawah->id]);
    }

    // =========================================================================
    // Halaman Pemakaian Material Boning/Repack (#509): menampilkan BOM,
    // terkunci, TIDAK memotong stok.
    // =========================================================================

    private function assertNothingWasWritten(): void
    {
        $this->assertSame(0, MaterialUsage::count());
        $this->assertSame(0, MaterialStockMovement::count());
        $this->assertSame(1000.0, (float) MaterialStock::where('material_id', $this->karton->id)->value('qty'));
        $this->assertSame(1000.0, (float) MaterialStock::where('material_id', $this->plastik->id)->value('qty'));
    }

    /** @test */
    public function the_boning_page_shows_the_bom_usage_and_warnings(): void
    {
        $boning = $this->boningWithItems();

        $page = Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('KARTON')
            ->assertSee('PLASTIK')
            // BONE tanpa BOM dan baris DRYLOG "jumlah tidak tetap" -> peringatan.
            ->assertSee(__('No BOM').': BONE')
            ->assertSee(__('Variable quantity, filled in manually').': TOPSIDE -- DRYLOG');

        $usage = $page->instance()->bomUsage();
        $byMaterial = collect($usage['rows'])->keyBy('material');
        $this->assertSame(5, (int) $byMaterial['KARTON']['qty']);
        $this->assertSame(24, (int) $byMaterial['PLASTIK VAKUM']['qty']);
        $this->assertFalse($byMaterial->has('DRYLOG'));
    }

    /** @test */
    public function the_fill_from_bom_action_is_gone_because_the_section_is_automatic(): void
    {
        $boning = $this->boningWithItems();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->assertActionDoesNotExist('fill_from_bom');
    }

    /** @test */
    public function saving_the_boning_page_writes_nothing_and_leaves_stock_alone(): void
    {
        $boning = $this->boningWithItems();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm(['drylog_qty' => 3])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3, $boning->fresh()->drylog_qty);
        $this->assertNothingWasWritten();
    }

    /**
     * Menyembunyikan tombol Save tidak menutup permintaan langsung: baris yang
     * dikirim lewat Livewire ke `data.materialUsages` tidak boleh melahirkan
     * MaterialUsage -- yang memotong stok.
     *
     * @test
     */
    public function a_direct_request_cannot_create_a_usage_row_that_would_cut_stock(): void
    {
        $boning = $this->boningWithItems();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm(['drylog_qty' => 0])
            ->set('data.materialUsages', [
                'x' => ['material_id' => $this->karton->id, 'qty' => 999, 'note' => 'langsung'],
            ])
            ->call('save');

        $this->assertNothingWasWritten();
    }

    /** @test */
    public function the_repack_page_shows_the_bom_usage_from_repack_results_and_writes_nothing(): void
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

        $page = Livewire::test(MaterialUsageRepack::class, ['record' => $repack->getRouteKey()])
            ->assertSuccessful()
            ->assertActionDoesNotExist('fill_from_bom');

        $byMaterial = collect($page->instance()->bomUsage()['rows'])->keyBy('material');
        $this->assertSame(3, (int) $byMaterial['KARTON']['qty']);
        $this->assertSame(24, (int) $byMaterial['PLASTIK VAKUM']['qty']);

        $page->fillForm(['drylog_qty' => 0])
            ->set('data.materialUsages', ['x' => ['material_id' => $this->karton->id, 'qty' => 5]])
            ->call('save');
        $this->assertNothingWasWritten();
    }

    /** @test */
    public function a_locked_document_cannot_open_the_page(): void
    {
        $boning = $this->boningWithItems();
        $boning->forceFill(['kunci' => true])->save();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->assertForbidden();
    }

    // =====================================================================
    // Drylog wajib sebelum Lock, dan snapshot saat dikunci (#509 langkah 3)
    // =====================================================================

    /** Dokumen yang syarat lainnya terpenuhi, kecuali drylog. */
    private function boningReadyToLock(): Boning
    {
        $boning = $this->boningWithItems();
        // Lock hanya memeriksa bahwa ada baris karkas, tetapi baris itu butuh
        // rantai karkas yang sah (pembelian, penerimaan, penimbangan).
        $supplier = \App\Models\Supplier::create(['name' => 'TEGUH '.uniqid(), 'address' => 'Bogor', 'pic' => 'Teguh', 'top_days' => 30]);
        $class = \App\Models\CattleClass::firstOrCreate(['name' => 'STEER'], ['is_active' => true]);
        $po = \App\Models\PurchaseCattle::create(['supplier_id' => $supplier->id, 'shipping_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        $po->items()->create(['cattle_class_id' => $class->id, 'qty' => 1, 'price' => 55000, 'created_by' => $this->user->id]);
        $receiving = \App\Models\CattleReceiving::create(['purchase_cattle_id' => $po->id, 'supplier_id' => $supplier->id, 'receive_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        $weighing = \App\Models\CattleWeighing::create(['cattle_receiving_id' => $receiving->id, 'weighing_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        $carcass = \App\Models\Carcass::create(['cattle_weighing_id' => $weighing->id, 'kill_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        \App\Models\BoningCarcass::create(['boning_id' => $boning->id, 'carcass_id' => $carcass->id]);

        return $boning->fresh();
    }

    /** @test */
    public function the_drylog_fields_are_required_on_the_page(): void
    {
        $boning = $this->boningWithItems();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm(['drylog_qty' => null])
            ->call('save')
            ->assertHasFormErrors(['drylog_qty' => 'required']);

        $this->assertNull($boning->fresh()->drylog_qty);
    }

    /** @test */
    public function an_explicit_zero_drylog_is_valid_and_is_not_the_same_as_empty(): void
    {
        $boning = $this->boningWithItems();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm(['drylog_qty' => 0])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $boning->fresh();
        $this->assertSame(0, $fresh->drylog_qty);
        $this->assertTrue($fresh->drylogWasFilled());
        $this->assertFalse(Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id])->fresh()->drylogWasFilled());
    }

    /** @test */
    public function a_negative_or_fractional_drylog_is_refused(): void
    {
        $boning = $this->boningWithItems();

        foreach ([-1, 2.5] as $bad) {
            Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
                ->fillForm(['drylog_qty' => $bad])
                ->call('save')
                ->assertHasFormErrors(['drylog_qty']);
        }

        $this->assertNull($boning->fresh()->drylog_qty);
    }

    /** @test */
    public function a_boning_cannot_be_locked_until_the_drylog_is_filled(): void
    {
        $boning = $this->boningReadyToLock();

        try {
            $boning->lock();
            $this->fail('Boning tanpa drylog seharusnya tidak bisa dikunci.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('drylog', strtolower($e->getMessage()));
        }

        $this->assertFalse($boning->fresh()->kunci);

        $boning->forceFill(['drylog_qty' => 0])->save();
        $boning->lock();

        $this->assertTrue($boning->fresh()->kunci, 'Dengan drylog 0 yang diisi eksplisit, dokumen harus bisa dikunci.');
    }

    /** @test */
    public function locking_freezes_the_bom_usage_and_unlocking_releases_it(): void
    {
        $boning = $this->boningReadyToLock();
        $boning->forceFill(['drylog_qty' => 2])->save();

        $boning->lock();

        $snap = $boning->bomSnapshots()->pluck('qty', 'material_id');
        $this->assertSame(5, (int) $snap[$this->karton->id]);
        $this->assertSame(24, (int) $snap[$this->plastik->id]);

        // BOM produk diubah sesudah dikunci: snapshot tidak ikut berubah.
        ProductMaterial::where('material_id', $this->karton->id)->update(['quantity' => 50]);
        $this->assertSame(5, (int) $boning->bomSnapshots()->where('material_id', $this->karton->id)->value('qty'));

        $boning->fresh()->unlock();
        $this->assertSame(0, $boning->bomSnapshots()->count(), 'Snapshot harus dilepas saat di-unlock.');
    }

    /** @test */
    public function locking_a_boning_does_not_touch_material_stock(): void
    {
        $boning = $this->boningReadyToLock();
        $boning->forceFill(['drylog_qty' => 9])->save();

        $boning->lock();

        $this->assertNothingWasWritten();
    }

    /** @test */
    public function a_direct_request_can_only_change_the_drylog_columns(): void
    {
        $boning = $this->boningWithItems();
        $docNo = $boning->doc_no;

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm(['drylog_qty' => 1])
            ->set('data.doc_no', 'BN99999')
            ->set('data.status', 'LOCKED')
            ->set('data.kunci', true)
            ->call('save');

        $fresh = $boning->fresh();
        $this->assertSame($docNo, $fresh->doc_no);
        $this->assertFalse($fresh->kunci);
        $this->assertSame(1, $fresh->drylog_qty);
    }

    /** @test */
    public function a_document_locked_in_the_meantime_cannot_be_saved(): void
    {
        $boning = $this->boningWithItems();

        $page = Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm(['drylog_qty' => 4]);

        $boning->forceFill(['kunci' => true])->save();

        $page->call('save')->assertForbidden();
        $this->assertNull($boning->fresh()->drylog_qty);
    }

    /** @test */
    public function the_document_info_has_no_empty_process_field(): void
    {
        // "Process" tidak pernah terisi pada dokumen yang sudah ada, tidak
        // disimpan, dan jenis prosesnya sudah terbaca dari judul halaman.
        $boning = $this->boningWithItems();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->assertFormFieldExists('doc_no')
            ->assertFormFieldExists('boning_date')
            ->assertFormFieldDoesNotExist('process');
    }
}
