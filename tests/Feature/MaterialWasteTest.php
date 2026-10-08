<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BoningResource\Pages\MaterialUsageBoning;
use App\Models\Boning;
use App\Models\BoningCarcass;
use App\Models\BoningItem;
use App\Models\Carcass;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;
use App\Models\FinancialLoss;
use App\Models\Grade;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStock;
use App\Models\MaterialStockMovement;
use App\Models\MaterialUnit;
use App\Models\MaterialUsage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MaterialUnitPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Issue #509, langkah 4: bahan terbuang dan kerugian rupiahnya.
 *
 * Bahan terbuang dicatat per kejadian (bahan, jumlah, alasan wajib), boleh
 * banyak, boleh kosong. Saat dokumen DIKUNCI, tiap baris menjadi satu baris
 * Financial Loss bernilai: qty x harga per satuan pakai, yaitu rata-rata
 * tertimbang harga beli GR dibagi "Isi per Satuan". Tidak memotong stok.
 */
class MaterialWasteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Material $plastik;

    private Material $drylog;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer', 'username' => 'prog_'.uniqid(), 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'programmer', 'is_active' => true,
        ]);
        $this->actingAs($this->user);

        $category = MaterialCategory::firstOrCreate(['name' => 'PACKAGING']);
        $unit = MaterialUnit::firstOrCreate(['name' => 'BOX']);

        // Plastik dibeli per BOX, isi 1.000 pcs.
        $this->plastik = Material::create([
            'name' => 'PLASTIK VAKUM', 'material_category_id' => $category->id, 'material_unit_id' => $unit->id,
            'min_stock' => 0, 'is_active' => true, 'content_per_unit' => 1000,
        ]);
        $this->drylog = Material::create([
            'name' => 'DRYLOG', 'material_category_id' => $category->id, 'material_unit_id' => $unit->id,
            'min_stock' => 0, 'is_active' => true,
        ]);
        MaterialStock::create(['material_id' => $this->plastik->id, 'qty' => 1000]);

        $this->supplier = Supplier::create(['name' => 'PEMASOK '.uniqid(), 'address' => 'Bogor', 'pic' => 'A', 'top_days' => 30]);
    }

    // ---------------------------------------------------------------------
    // Fixture
    // ---------------------------------------------------------------------

    private ?int $requisitionId = null;

    /** Permintaan material induk bagi PO (kolomnya wajib terisi). */
    private function requisitionId(): int
    {
        return $this->requisitionId ??= DB::table('material_requisitions')->insertGetId([
            'document_number' => 'MR-'.uniqid(), 'user_id' => $this->user->id, 'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** GR material yang sah: satu baris item dengan qty dan harga beli per BOX. */
    private function goodsReceipt(Material $material, int $qty, float $price, bool $deleted = false): void
    {
        // PO induk tanpa item, supaya tidak ikut menjadi harga PO.
        $poId = DB::table('purchase_materials')->insertGetId([
            'po_number' => 'PO-GR-'.uniqid(), 'material_requisition_id' => $this->requisitionId(), 'supplier_id' => $this->supplier->id, 'po_date' => now()->toDateString(),
            'total_amount' => 0, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $grId = DB::table('goods_receipt_materials')->insertGetId([
            'gr_number' => 'GR-'.uniqid(), 'purchase_material_id' => $poId, 'supplier_id' => $this->supplier->id, 'receive_date' => now()->toDateString(),
            'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
            'deleted_at' => $deleted ? now() : null,
        ]);

        DB::table('goods_receipt_material_items')->insert([
            'goods_receipt_material_id' => $grId, 'material_id' => $material->id,
            'qty_received' => $qty, 'price' => $price, 'subtotal' => $qty * $price,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function purchaseOrder(Material $material, float $price, string $date): void
    {
        $poId = DB::table('purchase_materials')->insertGetId([
            'po_number' => 'PO-'.uniqid(), 'material_requisition_id' => $this->requisitionId(), 'supplier_id' => $this->supplier->id, 'po_date' => $date,
            'total_amount' => $price, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('purchase_material_items')->insert([
            'purchase_material_id' => $poId, 'material_id' => $material->id, 'qty' => 1, 'price' => $price,
            'subtotal' => $price, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Boning yang syarat Lock-nya terpenuhi (karkas, hasil, drylog 0). */
    private function boning(): Boning
    {
        $category = ProductCategory::firstOrCreate(['name' => 'DAGING'], ['prefix' => 1]);
        $product = Product::create([
            'code' => (string) random_int(100000, 999999), 'name' => 'TENDERLOIN', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $warehouse = Warehouse::firstOrCreate(['code' => 'JGL'], ['name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::firstOrCreate(['name' => 'CHILL'], ['is_active' => true]);

        $boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id]);

        BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'grade_id' => $grade->id, 'weight' => 10, 'qty_pcs' => 5, 'pack_date' => now()->toDateString(),
            'barcode' => 'BC-'.uniqid(), 'created_by' => $this->user->id,
        ]);

        $class = CattleClass::firstOrCreate(['name' => 'STEER'], ['is_active' => true]);
        $po = PurchaseCattle::create(['supplier_id' => $this->supplier->id, 'shipping_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        $po->items()->create(['cattle_class_id' => $class->id, 'qty' => 1, 'price' => 55000, 'created_by' => $this->user->id]);
        $receiving = CattleReceiving::create(['purchase_cattle_id' => $po->id, 'supplier_id' => $this->supplier->id, 'receive_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        $weighing = CattleWeighing::create(['cattle_receiving_id' => $receiving->id, 'weighing_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        $carcass = Carcass::create(['cattle_weighing_id' => $weighing->id, 'kill_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        BoningCarcass::create(['boning_id' => $boning->id, 'carcass_id' => $carcass->id]);

        $boning->forceFill(['drylog_qty' => 0])->save();

        return $boning->fresh();
    }

    private function waste(Boning $boning, Material $material, int $qty, string $reason = 'gagal vakum'): void
    {
        $boning->materialWastes()->create(['material_id' => $material->id, 'qty' => $qty, 'reason' => $reason]);
    }

    private function losses(Boning $boning)
    {
        return FinancialLoss::where('lossable_type', Boning::class)
            ->where('lossable_id', $boning->id)
            ->where('transaction_type', FinancialLoss::SUMBER_MATERIAL_WASTE)
            ->get();
    }

    // ---------------------------------------------------------------------
    // Harga per satuan pakai
    // ---------------------------------------------------------------------

    /** @test */
    public function one_box_at_a_million_holding_a_thousand_makes_a_wasted_pcs_cost_a_thousand_rupiah(): void
    {
        $this->goodsReceipt($this->plastik, 1, 1000000);

        $this->assertSame(1000.0, MaterialUnitPrice::perUsageUnit($this->plastik));

        $boning = $this->boning();
        $this->waste($boning, $this->plastik, 3);
        $boning->lock();

        $loss = $this->losses($boning)->sole();
        $this->assertSame(3000.0, (float) $loss->amount, '3 pcs x Rp 1.000 harus Rp 3.000.');
        $this->assertSame(3.0, (float) $loss->quantity);
        $this->assertSame($boning->doc_no, $loss->reference_number);
        $this->assertStringContainsString('gagal vakum', $loss->note);
        $this->assertStringContainsString('PLASTIK VAKUM', $loss->note);
        $this->assertFalse($loss->isNotPricedYet());
    }

    /** @test */
    public function the_price_is_the_quantity_weighted_average_of_the_goods_receipts(): void
    {
        // 1 box @ 1.000.000 dan 3 box @ 2.000.000 -> (1 x 1 jt + 3 x 2 jt) / 4 = 1.750.000 per box.
        $this->goodsReceipt($this->plastik, 1, 1000000);
        $this->goodsReceipt($this->plastik, 3, 2000000);

        $this->assertSame(1750.0, MaterialUnitPrice::perUsageUnit($this->plastik));
    }

    /** @test */
    public function a_deleted_goods_receipt_is_not_counted(): void
    {
        $this->goodsReceipt($this->plastik, 1, 1000000);
        $this->goodsReceipt($this->plastik, 5, 9000000, deleted: true);

        $this->assertSame(1000.0, MaterialUnitPrice::perUsageUnit($this->plastik));
    }

    /** @test */
    public function without_a_goods_receipt_the_latest_purchase_order_price_is_used(): void
    {
        $this->purchaseOrder($this->plastik, 800000, '2026-09-01');
        $this->purchaseOrder($this->plastik, 900000, '2026-10-01');

        $this->assertSame(900.0, MaterialUnitPrice::perUsageUnit($this->plastik));
    }

    /** @test */
    public function a_goods_receipt_takes_precedence_over_a_purchase_order(): void
    {
        $this->purchaseOrder($this->plastik, 5000000, '2026-10-05');
        $this->goodsReceipt($this->plastik, 1, 1000000);

        $this->assertSame(1000.0, MaterialUnitPrice::perUsageUnit($this->plastik));
    }

    /** @test */
    public function content_per_unit_of_one_means_the_purchase_unit_is_the_usage_unit(): void
    {
        $this->goodsReceipt($this->drylog, 10, 25000);

        $this->assertSame(25000.0, MaterialUnitPrice::perUsageUnit($this->drylog));
    }

    // ---------------------------------------------------------------------
    // Financial Loss
    // ---------------------------------------------------------------------

    /** @test */
    public function a_waste_without_any_price_is_recorded_at_zero_and_flagged_without_blocking_the_lock(): void
    {
        $boning = $this->boning();
        $this->waste($boning, $this->plastik, 4);

        $boning->lock();

        $this->assertTrue($boning->fresh()->kunci, 'Tanpa harga, Lock tidak boleh diblokir.');

        $loss = $this->losses($boning)->sole();
        $this->assertSame(0.0, (float) $loss->amount);
        $this->assertTrue($loss->isNotPricedYet(), 'Baris tanpa harga harus ditandai "belum ada harga".');
    }

    /** @test */
    public function the_price_is_a_snapshot_and_does_not_follow_later_purchases(): void
    {
        $this->goodsReceipt($this->plastik, 1, 1000000);
        $boning = $this->boning();
        $this->waste($boning, $this->plastik, 3);
        $boning->lock();

        // Harga beli melonjak sesudah dikunci.
        $this->goodsReceipt($this->plastik, 1, 9000000);

        $this->assertSame(3000.0, (float) $this->losses($boning)->sole()->amount);
    }

    /** @test */
    public function several_waste_rows_make_one_loss_row_each(): void
    {
        $this->goodsReceipt($this->plastik, 1, 1000000);
        $boning = $this->boning();
        $this->waste($boning, $this->plastik, 3, 'gagal vakum');
        $this->waste($boning, $this->plastik, 2, 'reject');

        $boning->lock();

        $this->assertSame([2000.0, 3000.0], $this->losses($boning)->pluck('amount')->map(fn ($a) => (float) $a)->sort()->values()->all());
    }

    /** @test */
    public function no_waste_means_no_loss_and_the_lock_still_works(): void
    {
        $boning = $this->boning();

        $boning->lock();

        $this->assertTrue($boning->fresh()->kunci);
        $this->assertSame(0, $this->losses($boning)->count());
    }

    /** @test */
    public function unlocking_reverses_the_losses_and_locking_again_does_not_duplicate_them(): void
    {
        $this->goodsReceipt($this->plastik, 1, 1000000);
        $boning = $this->boning();
        $this->waste($boning, $this->plastik, 3);

        $boning->lock();
        $this->assertSame(1, $this->losses($boning)->count());

        $boning->fresh()->unlock();
        $this->assertSame(0, $this->losses($boning)->count(), 'Unlock harus membalik kerugiannya.');

        $boning->fresh()->lock();
        $this->assertSame(1, $this->losses($boning)->count(), 'Lock ulang tidak boleh menggandakan.');
    }

    /** @test */
    public function a_row_deleted_before_the_next_lock_disappears_from_the_losses(): void
    {
        $this->goodsReceipt($this->plastik, 1, 1000000);
        $boning = $this->boning();
        $this->waste($boning, $this->plastik, 3);
        $this->waste($boning, $this->plastik, 2, 'reject');

        $boning->lock();
        $boning->fresh()->unlock();

        $boning->materialWastes()->where('reason', 'reject')->delete();
        $boning->fresh()->lock();

        $loss = $this->losses($boning)->sole();
        $this->assertSame(3000.0, (float) $loss->amount);
    }

    /** @test */
    public function locking_with_waste_does_not_touch_material_stock(): void
    {
        $this->goodsReceipt($this->plastik, 1, 1000000);
        $boning = $this->boning();
        $this->waste($boning, $this->plastik, 3);

        $boning->lock();

        $this->assertSame(0, MaterialUsage::count());
        $this->assertSame(0, MaterialStockMovement::count());
        $this->assertSame(1000.0, (float) MaterialStock::where('material_id', $this->plastik->id)->value('qty'));
    }

    /** @test */
    public function the_waste_loss_shows_up_as_its_own_source_in_the_financial_loss_module(): void
    {
        $this->assertContains(FinancialLoss::SUMBER_MATERIAL_WASTE, FinancialLoss::SEMUA_SUMBER);
    }

    // ---------------------------------------------------------------------
    // Halaman
    // ---------------------------------------------------------------------

    /** @test */
    public function the_page_saves_several_waste_rows_with_the_drylog(): void
    {
        $boning = $this->boning();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm([
                'drylog_qty' => 1,
                'materialWastes' => [
                    ['material_id' => $this->plastik->id, 'qty' => 3, 'reason' => 'gagal vakum'],
                    ['material_id' => $this->drylog->id, 'qty' => 2, 'reason' => 'reject'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, $boning->materialWastes()->count());
        $this->assertSame(0, FinancialLoss::count(), 'Kerugian baru ditulis saat dikunci, bukan saat disimpan.');
    }

    /** @test */
    public function a_waste_row_needs_a_reason_and_a_whole_quantity_of_at_least_one(): void
    {
        $boning = $this->boning();

        foreach ([
            ['qty' => 3, 'reason' => ''],
            ['qty' => 0, 'reason' => 'reject'],
            ['qty' => -2, 'reason' => 'reject'],
            ['qty' => 1.5, 'reason' => 'reject'],
        ] as $bad) {
            Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
                ->fillForm([
                    'drylog_qty' => 0,
                    'materialWastes' => [['material_id' => $this->plastik->id] + $bad],
                ])
                ->call('save')
                ->assertHasFormErrors();
        }

        $this->assertSame(0, $boning->materialWastes()->count());
    }

    /** @test */
    public function the_page_can_be_saved_with_no_waste_at_all(): void
    {
        $boning = $this->boning();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm(['drylog_qty' => 0, 'materialWastes' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $boning->materialWastes()->count());
    }

    /** @test */
    public function the_same_material_cannot_be_listed_twice_as_waste(): void
    {
        $boning = $this->boning();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm([
                'drylog_qty' => 0,
                'materialWastes' => [
                    ['material_id' => $this->plastik->id, 'qty' => 3, 'reason' => 'gagal vakum'],
                    ['material_id' => $this->plastik->id, 'qty' => 2, 'reason' => 'reject'],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame(0, $boning->materialWastes()->count(), 'Material kembar tidak boleh tersimpan separuh.');
    }

    /** @test */
    public function different_materials_are_fine_and_are_saved_together(): void
    {
        $boning = $this->boning();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->fillForm([
                'drylog_qty' => 0,
                'materialWastes' => [
                    ['material_id' => $this->plastik->id, 'qty' => 3, 'reason' => 'gagal vakum'],
                    ['material_id' => $this->drylog->id, 'qty' => 1, 'reason' => 'basah'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, $boning->materialWastes()->count());
    }
}
