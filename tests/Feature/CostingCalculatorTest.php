<?php

namespace Tests\Feature;

use App\Models\Boning;
use App\Models\BoningCarcass;
use App\Models\Carcass;
use App\Models\CarcassItem;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\CattleReceivingItem;
use App\Models\CattleWeighing;
use App\Models\CattleWeighingItem;
use App\Models\CostingItem;
use App\Models\CustomerGroup;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseCattle;
use App\Models\PurchaseCattleItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CostingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Langkah 1 dari issue #480 (Mesin HPP): `CostingCalculator` murni,
 * diuji terhadap angka lot Juni yang sudah diverifikasi manual di
 * `.agents/hpp.md` §1-2. Kalau angka di sini tidak cocok sampai dua
 * desimal, MASALAHNYA ADA DI KODE -- bukan di test.
 */
class CostingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Product $topside;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($this->user);

        $this->warehouse = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        $this->topside = Product::create([
            'name' => 'TOPSIDE', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    /**
     * Satu carcass, satu kelas sapi, satu PO -- cukup untuk lot ini.
     * Berat terima 10.888 kg, harga 62.500/kg -> biaya beli 680.500.000,
     * persis `.agents/hpp.md` §1-2.
     *
     * @return array{boning: Boning, cattleClass: CattleClass}
     */
    private function juneLotCattleChain(float $pricePerKg = 62500, float $receivedWeight = 10888): array
    {
        $cattleClass = CattleClass::create(['name' => 'STEER']);
        $supplier = Supplier::create([
            'name' => 'PT LEMBU JANTAN PERKASA', 'address' => 'X', 'pic' => 'X',
            'phone' => '08', 'top_days' => 30,
        ]);

        $po = PurchaseCattle::create([
            'supplier_id' => $supplier->id, 'shipping_date' => '2026-06-13', 'created_by' => $this->user->id,
        ]);
        PurchaseCattleItem::create([
            'purchase_cattle_id' => $po->id, 'cattle_class_id' => $cattleClass->id,
            'qty' => 20, 'price' => $pricePerKg, 'created_by' => $this->user->id,
        ]);

        $receiving = CattleReceiving::create([
            'purchase_cattle_id' => $po->id, 'supplier_id' => $supplier->id,
            'receive_date' => '2026-06-14', 'created_by' => $this->user->id,
        ]);
        $receivingItem = CattleReceivingItem::create([
            'cattle_receiving_id' => $receiving->id, 'cattle_class_id' => $cattleClass->id,
            'eartag' => 'EARTAG-001', 'initial_weight' => $receivedWeight,
        ]);

        $weighing = CattleWeighing::create([
            'cattle_receiving_id' => $receiving->id, 'weighing_date' => '2026-06-14', 'created_by' => $this->user->id,
        ]);
        $weighingItem = CattleWeighingItem::create([
            'cattle_weighing_id' => $weighing->id, 'cattle_receiving_item_id' => $receivingItem->id,
            'actual_weight' => $receivedWeight,
        ]);

        $carcass = Carcass::create([
            'cattle_weighing_id' => $weighing->id, 'kill_date' => '2026-06-15', 'created_by' => $this->user->id,
        ]);
        CarcassItem::create([
            'carcass_id' => $carcass->id, 'cattle_weighing_item_id' => $weighingItem->id,
            'carcass_1' => $receivedWeight * 0.3, 'carcass_2' => $receivedWeight * 0.26,
            'hides' => $receivedWeight * 0.07, 'tail' => 0,
        ]);

        $boning = Boning::create([
            'doc_no' => 'BN26-0001', 'boning_date' => '2026-06-16', 'created_by' => $this->user->id,
        ]);
        BoningCarcass::create(['boning_id' => $boning->id, 'carcass_id' => $carcass->id]);

        return ['boning' => $boning, 'cattleClass' => $cattleClass];
    }

    private function lionGroup(): CustomerGroup
    {
        return CustomerGroup::create([
            'name' => 'LION', 'top' => 30, 'trading_terms_percent' => 6, 'is_costing_reference' => true,
        ]);
    }

    private function priceListItem(CustomerGroup $group, Product $product, float $grossPrice): PriceListItem
    {
        $priceList = PriceList::firstOrCreate(['customer_group_id' => $group->id], ['created_by' => $this->user->id]);

        return PriceListItem::create([
            'price_list_id' => $priceList->id, 'product_id' => $product->id, 'price' => $grossPrice,
        ]);
    }

    /** @test */
    public function the_june_lot_reproduces_the_hpp_md_numbers_to_two_decimals(): void
    {
        ['boning' => $boning] = $this->juneLotCattleChain();

        $lion = $this->lionGroup();
        $this->topside->update(['costing_customer_group_id' => $lion->id]);
        // GROSS 149.000 x (1 - 6%) = NET 140.060,00 -- persis hpp.md §1-2.
        $this->priceListItem($lion, $this->topside, 149000);

        \App\Models\BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $this->topside->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => \App\Models\Grade::create(['name' => 'CHILL'])->id,
            'weight' => 100, 'qty_pcs' => 39, 'pack_date' => '2026-06-16', 'barcode' => 'BC-TOPSIDE-001',
            'created_by' => $this->user->id,
        ]);

        // Produk "pengisi" -- berdiri untuk SISA ~29 baris produk lot 15
        // Juni yang angka per-produknya tidak diketahui di sini (hanya
        // Topside/Silverside/Chuck/Bone yang dikonfirmasi hpp.md §1-2).
        // Beratnya 1 kg dengan net = SISA total_nilai_jual yang dibutuhkan
        // supaya totalnya persis 717.762.805,70 -- angka itu sendiri
        // adalah fakta dari hpp.md, bukan tebakan. Karena k = biaya_beli /
        // total_nilai_jual, dan HPP Topside = net_topside x k, HASIL HPP
        // Topside HANYA bergantung pada net Topside dan KEDUA total itu --
        // tidak bergantung pada bagaimana produk lain membentuk totalnya.
        $filler = Product::create([
            'name' => 'FILLER (SISA LOT)', 'code' => 'MT999', 'category_id' => $this->topside->category_id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $general = CustomerGroup::create(['name' => 'UMUM', 'is_general_price_reference' => true]);
        $fillerNet = 717762805.70 - (140060.00 * 100);
        $this->priceListItem($general, $filler, $fillerNet);
        \App\Models\BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $filler->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => \App\Models\Grade::first()->id,
            'weight' => 1, 'qty_pcs' => 1, 'pack_date' => '2026-06-16', 'barcode' => 'BC-FILLER-001',
            'created_by' => $this->user->id,
        ]);

        $boning->lock();

        $result = CostingCalculator::forBoning($boning->fresh())->calculate();

        $this->assertSame(680500000.0, $result['purchase_cost']);
        $this->assertSame(717762805.70, $result['total_sales_value']);
        $this->assertSame(0.948085, $result['ratio_k']);

        $topsideItem = collect($result['items'])->firstWhere('product_id', $this->topside->id);
        $this->assertSame(140060.0, $topsideItem['net_price']);
        $this->assertSame(132788.76, $topsideItem['hpp_per_kg']);
        $this->assertNull($topsideItem['flag']);

        $cattleRow = $result['cattle'][0];
        $this->assertSame(10888.0, $cattleRow['received_weight']);
        $this->assertSame(62500.0, $cattleRow['price_per_kg']);
        $this->assertSame(680500000.0, $cattleRow['amount']);
    }

    /** @test */
    public function two_cattle_classes_with_different_prices_are_summed_separately(): void
    {
        $heifer = CattleClass::create(['name' => 'HEIFER']);
        $steer = CattleClass::create(['name' => 'STEER']);
        $supplier = Supplier::create([
            'name' => 'PT LEMBU JANTAN PERKASA', 'address' => 'X', 'pic' => 'X',
            'phone' => '08', 'top_days' => 30,
        ]);

        $po = PurchaseCattle::create([
            'supplier_id' => $supplier->id, 'shipping_date' => '2026-08-19', 'created_by' => $this->user->id,
        ]);
        // CPO-260112: HEIFER 61.700, STEER 62.000 (hpp.md §6).
        PurchaseCattleItem::create([
            'purchase_cattle_id' => $po->id, 'cattle_class_id' => $heifer->id,
            'qty' => 5, 'price' => 61700, 'created_by' => $this->user->id,
        ]);
        PurchaseCattleItem::create([
            'purchase_cattle_id' => $po->id, 'cattle_class_id' => $steer->id,
            'qty' => 5, 'price' => 62000, 'created_by' => $this->user->id,
        ]);

        $receiving = CattleReceiving::create([
            'purchase_cattle_id' => $po->id, 'supplier_id' => $supplier->id,
            'receive_date' => '2026-08-19', 'created_by' => $this->user->id,
        ]);
        $weighing = CattleWeighing::create([
            'cattle_receiving_id' => $receiving->id, 'weighing_date' => '2026-08-19', 'created_by' => $this->user->id,
        ]);
        $carcass = Carcass::create([
            'cattle_weighing_id' => $weighing->id, 'kill_date' => '2026-08-19', 'created_by' => $this->user->id,
        ]);

        foreach ([['class' => $heifer, 'weight' => 500.0], ['class' => $steer, 'weight' => 600.0]] as $spec) {
            $receivingItem = CattleReceivingItem::create([
                'cattle_receiving_id' => $receiving->id, 'cattle_class_id' => $spec['class']->id,
                'eartag' => 'EARTAG-'.$spec['class']->id, 'initial_weight' => $spec['weight'],
            ]);
            $weighingItem = CattleWeighingItem::create([
                'cattle_weighing_id' => $weighing->id, 'cattle_receiving_item_id' => $receivingItem->id,
                'actual_weight' => $spec['weight'],
            ]);
            CarcassItem::create([
                'carcass_id' => $carcass->id, 'cattle_weighing_item_id' => $weighingItem->id,
                'carcass_1' => $spec['weight'] * 0.3, 'carcass_2' => $spec['weight'] * 0.26,
                'hides' => $spec['weight'] * 0.07, 'tail' => 0,
            ]);
        }

        $boning = Boning::create(['doc_no' => 'BN26-0002', 'boning_date' => '2026-08-20', 'created_by' => $this->user->id]);
        BoningCarcass::create(['boning_id' => $boning->id, 'carcass_id' => $carcass->id]);

        $group = $this->lionGroup();
        $this->topside->update(['costing_customer_group_id' => $group->id]);
        $this->priceListItem($group, $this->topside, 100000);
        \App\Models\BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $this->topside->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => \App\Models\Grade::create(['name' => 'CHILL'])->id,
            'weight' => 300, 'qty_pcs' => 10, 'pack_date' => '2026-08-20', 'barcode' => 'BC-TOPSIDE-002',
            'created_by' => $this->user->id,
        ]);

        $boning->lock();

        $result = CostingCalculator::forBoning($boning->fresh())->calculate();

        // Bukan (500+600) x satu harga -- dua baris, masing-masing kelasnya sendiri.
        $this->assertCount(2, $result['cattle']);
        $byClass = collect($result['cattle'])->keyBy('cattle_class_id');
        $this->assertSame(61700.0, $byClass[$heifer->id]['price_per_kg']);
        $this->assertSame(500.0 * 61700, $byClass[$heifer->id]['amount']);
        $this->assertSame(62000.0, $byClass[$steer->id]['price_per_kg']);
        $this->assertSame(600.0 * 62000, $byClass[$steer->id]['amount']);
        $this->assertSame((500.0 * 61700) + (600.0 * 62000), $result['purchase_cost']);
    }

    /** @test */
    public function a_product_without_a_reference_group_is_flagged_no_reference_and_uses_general_price(): void
    {
        ['boning' => $boning] = $this->juneLotCattleChain();

        $general = CustomerGroup::create(['name' => 'UMUM', 'is_general_price_reference' => true]);
        $this->priceListItem($general, $this->topside, 50000);
        // costing_customer_group_id TIDAK diisi -- kosong = harga umum.

        \App\Models\BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $this->topside->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => \App\Models\Grade::create(['name' => 'CHILL'])->id,
            'weight' => 100, 'qty_pcs' => 39, 'pack_date' => '2026-06-16', 'barcode' => 'BC-TOPSIDE-003',
            'created_by' => $this->user->id,
        ]);

        $boning->lock();

        $result = CostingCalculator::forBoning($boning->fresh())->calculate();

        $item = collect($result['items'])->firstWhere('product_id', $this->topside->id);
        $this->assertSame(CostingItem::FLAG_NO_REFERENCE, $item['flag']);
        // Harga umum TIDAK dipotong trading terms -- hanya LION/HYPERMART yang begitu.
        $this->assertSame(0.0, $item['trading_terms_percent']);
        $this->assertSame(50000.0, $item['net_price']);
        $this->assertGreaterThan(0, $item['hpp_per_kg']);
    }

    /** @test */
    public function a_product_with_no_price_anywhere_is_flagged_no_price_with_zero_value(): void
    {
        ['boning' => $boning] = $this->juneLotCattleChain();

        // Tidak ada grup acuan, dan tidak ada grup "harga umum" yang
        // ditandai sama sekali -- tidak ada sumber harga untuk ditemukan.
        \App\Models\BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $this->topside->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => \App\Models\Grade::create(['name' => 'CHILL'])->id,
            'weight' => 100, 'qty_pcs' => 39, 'pack_date' => '2026-06-16', 'barcode' => 'BC-TOPSIDE-004',
            'created_by' => $this->user->id,
        ]);

        $boning->lock();

        $result = CostingCalculator::forBoning($boning->fresh())->calculate();

        $item = collect($result['items'])->firstWhere('product_id', $this->topside->id);
        $this->assertSame(CostingItem::FLAG_NO_PRICE, $item['flag']);
        $this->assertSame(0.0, $item['gross_price']);
        $this->assertSame(0.0, $item['net_price']);
        $this->assertSame(0.0, $item['hpp_per_kg']);
    }

    /** @test */
    public function an_unlocked_boning_cannot_be_costed(): void
    {
        ['boning' => $boning] = $this->juneLotCattleChain();

        $this->expectException(\RuntimeException::class);

        CostingCalculator::forBoning($boning)->calculate();
    }
}
