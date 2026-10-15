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
use App\Models\CostingPriceDefault;
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

    /** Harga patokan costing (#541): sumber harga bila produk belum pernah ada di costing terkunci. */
    private function defaultPrice(Product $product, float $gross, float $net): CostingPriceDefault
    {
        return CostingPriceDefault::create([
            'product_id' => $product->id, 'gross_price' => $gross, 'net_price' => $net, 'source' => 'import',
        ]);
    }

    private function boningItem(Boning $boning, Product $product, float $weight, string $barcode): void
    {
        \App\Models\BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => (\App\Models\Grade::first() ?? \App\Models\Grade::create(['name' => 'CHILL']))->id,
            'weight' => $weight, 'qty_pcs' => 1, 'pack_date' => '2026-06-16', 'barcode' => $barcode,
            'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function the_june_lot_reproduces_the_hpp_md_numbers_to_two_decimals(): void
    {
        ['boning' => $boning] = $this->juneLotCattleChain();

        // GROSS 149.000 x (1 - 6%) = NET 140.060,00 -- persis hpp.md §1-2.
        $this->defaultPrice($this->topside, 149000, 140060);

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
        $fillerNet = 717762805.70 - (140060.00 * 100);
        $this->defaultPrice($filler, $fillerNet, $fillerNet);
        \App\Models\BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $filler->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => \App\Models\Grade::first()->id,
            'weight' => 1, 'qty_pcs' => 1, 'pack_date' => '2026-06-16', 'barcode' => 'BC-FILLER-001',
            'created_by' => $this->user->id,
        ]);

        $this->lockWithDrylog($boning);

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

        $this->defaultPrice($this->topside, 100000, 94000);
        \App\Models\BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $this->topside->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => \App\Models\Grade::create(['name' => 'CHILL'])->id,
            'weight' => 300, 'qty_pcs' => 10, 'pack_date' => '2026-08-20', 'barcode' => 'BC-TOPSIDE-002',
            'created_by' => $this->user->id,
        ]);

        $this->lockWithDrylog($boning);

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
    public function a_price_comes_from_the_last_locked_costing_before_the_default(): void
    {
        ['boning' => $boning] = $this->juneLotCattleChain();
        $this->defaultPrice($this->topside, 100000, 94000);
        $this->boningItem($boning, $this->topside, 100, 'BC-TOPSIDE-003');
        $this->lockWithDrylog($boning);

        // Dua costing terkunci untuk produk yang sama: yang TERAKHIR terkunci menang.
        foreach ([['2026-06-01 10:00:00', 150000, 140000, 'BN-OLD'], ['2026-06-10 10:00:00', 160000, 150400, 'BN-NEW']] as [$lockedAt, $gross, $net, $doc]) {
            $old = Boning::create(['doc_no' => $doc, 'boning_date' => '2026-06-01', 'created_by' => $this->user->id]);
            $costing = \App\Models\Costing::create(['boning_id' => $old->id, 'costing_date' => '2026-06-01']);
            $costing->items()->create(['product_id' => $this->topside->id, 'weight_kg' => 1, 'gross_price' => $gross, 'net_price' => $net]);
            $costing->forceFill(['status' => 'Locked', 'locked_at' => $lockedAt])->saveQuietly();
        }

        $item = collect(CostingCalculator::forBoning($boning->fresh())->calculate()['items'])->firstWhere('product_id', $this->topside->id);

        $this->assertSame(160000.0, $item['gross_price']);
        $this->assertSame(150400.0, $item['net_price']);
        $this->assertSame(6.0, $item['trading_terms_percent']);
        $this->assertNull($item['flag']);
    }

    /** @test */
    public function typed_prices_win_over_the_last_costing_and_the_discount_is_per_product(): void
    {
        ['boning' => $boning] = $this->juneLotCattleChain();
        $this->defaultPrice($this->topside, 100000, 94000);
        $this->boningItem($boning, $this->topside, 100, 'BC-TOPSIDE-005');
        $this->lockWithDrylog($boning);

        $item = collect(CostingCalculator::forBoning($boning->fresh(), 0, [$this->topside->id => ['gross' => 200000, 'net' => 166666.67]])->calculate()['items'])
            ->firstWhere('product_id', $this->topside->id);

        $this->assertSame(166666.67, $item['net_price']);
        $this->assertSame(16.67, $item['trading_terms_percent']);
    }

    /** @test */
    public function typing_only_the_gross_uses_the_last_discount_of_that_product_or_none(): void
    {
        $this->assertSame(200000.0, \App\Services\CostingPrices::netForGross($this->topside->id, 200000), 'Belum pernah punya harga: net = gross.');

        $this->defaultPrice($this->topside, 100000, 93750);

        $this->assertSame(187500.0, \App\Services\CostingPrices::netForGross($this->topside->id, 200000), 'Potongan terakhir 6,25%.');
    }

    /**
     * Angka wajib issue #541 (lot LJP 15 - 7 Okt): overhead ikut HPP lewat k, bukan +3.000 rata.
     * Offal tidak ikut dasar overhead (kg daging 4.737,44).
     *
     * @test
     */
    public function overhead_enters_hpp_through_k_with_the_ljp_7_october_numbers(): void
    {
        // Harga per kg bilangan bulat, jadi biaya beli 490.847.989,74 dibentuk dari dua kelas:
        // 8.180 kg x 60.000 + 15.996,58 kg x 3.
        ['boning' => $boning, 'cattleClass' => $classOne] = $this->juneLotCattleChain(pricePerKg: 60000, receivedWeight: 8180);
        $classTwo = CattleClass::create(['name' => 'COW']);
        $carcass = Carcass::first();
        $weighing = CattleWeighing::find($carcass->cattle_weighing_id);
        PurchaseCattleItem::create([
            'purchase_cattle_id' => CattleReceiving::find($weighing->cattle_receiving_id)->purchase_cattle_id,
            'cattle_class_id' => $classTwo->id, 'qty' => 1, 'price' => 3, 'created_by' => $this->user->id,
        ]);
        $receivingItem = CattleReceivingItem::create([
            'cattle_receiving_id' => $weighing->cattle_receiving_id, 'cattle_class_id' => $classTwo->id,
            'eartag' => 'EARTAG-002', 'initial_weight' => 15996.58,
        ]);
        $weighingItem = CattleWeighingItem::create([
            'cattle_weighing_id' => $weighing->id, 'cattle_receiving_item_id' => $receivingItem->id, 'actual_weight' => 15996.58,
        ]);
        CarcassItem::create([
            'carcass_id' => $carcass->id, 'cattle_weighing_item_id' => $weighingItem->id,
            'carcass_1' => 1, 'carcass_2' => 1, 'hides' => 1, 'tail' => 0,
        ]);

        $category = $this->topside->category_id;
        $make = fn (string $name, string $code) => Product::create(['name' => $name, 'code' => $code, 'category_id' => $category, 'structure_type' => 'main', 'is_active' => true]);
        $tenderloin = $make('TENDERLOIN', 'MT002');
        $cuberoll = $make('CUBEROLL', 'MT003');
        $offal = $make('OFFAL', 'MT004');
        $offal->update(['excluded_from_overhead' => true]);
        $fillerA = $make('FILLER A', 'MT998');
        $fillerB = $make('FILLER B', 'MT999');

        $kg = [$this->topside->id => 309.88, $tenderloin->id => 15.26, $cuberoll->id => 32.48, $offal->id => 4795.3];
        $net = [$this->topside->id => 138281.25, $tenderloin->id => 203437.50, $cuberoll->id => 138693.00, $offal->id => 8000.00];

        // Sisa daging 4.737,44 - 357,62 = 4.379,82 kg: 4.378,82 kg @ 1,00 + 1 kg yang menggenapkan total nilai jual.
        $kg[$fillerA->id] = 4378.82;
        $net[$fillerA->id] = 1.00;
        $kg[$fillerB->id] = 1.0;
        $net[$fillerB->id] = 0.0;
        $knownSales = 0.0;
        foreach ($kg as $id => $weight) {
            $knownSales += $weight * $net[$id];
        }
        $net[$fillerB->id] = round(559212720.18 - $knownSales, 2);

        $typed = [];
        $n = 0;
        foreach ($kg as $id => $weight) {
            $this->boningItem($boning, Product::find($id), $weight, 'BC-LJP-'.(++$n));
            $typed[$id] = ['gross' => $net[$id], 'net' => $net[$id]];
        }
        $this->lockWithDrylog($boning);

        $result = CostingCalculator::forBoning($boning->fresh(), 3000, $typed)->calculate();

        $this->assertSame(490847989.74, $result['purchase_cost']);
        $this->assertSame(559212720.18, $result['total_sales_value']);
        $this->assertSame(14212320.0, $result['overhead_total']);
        $this->assertSame(0.903163, $result['ratio_k']);
        $this->assertSame(54152410.44, $result['profit']);

        $hpp = fn (Product $p) => collect($result['items'])->firstWhere('product_id', $p->id)['hpp_per_kg'];
        $this->assertSame(124890.53, $hpp($this->topside));
        $this->assertSame(183737.25, $hpp($tenderloin));
        $this->assertSame(125262.40, $hpp($cuberoll));
        $this->assertSame(7225.31, $hpp($offal));
    }

    /** @test */
    public function only_the_flag_decides_the_overhead_kg_not_the_product_name(): void
    {
        ['boning' => $boning] = $this->juneLotCattleChain();
        $this->boningItem($boning, $this->topside, 100, 'BC-FLAG-1');
        $hides = Product::create(['name' => 'KULIT', 'code' => 'MT050', 'category_id' => $this->topside->category_id, 'structure_type' => 'main', 'is_active' => true]);
        $this->boningItem($boning, $hides, 50, 'BC-FLAG-2');
        $this->lockWithDrylog($boning);
        $typed = [$this->topside->id => ['gross' => 100000, 'net' => 100000], $hides->id => ['gross' => 0, 'net' => 20000]];

        // Bernama KULIT tetapi tanpa flag: ikut kg overhead (150 kg).
        $this->assertSame(150 * 1000.0, CostingCalculator::forBoning($boning->fresh(), 1000, $typed)->calculate()['overhead_total']);

        // Diberi flag: tidak ikut (100 kg) -- lalu diganti nama, hasilnya tetap.
        $hides->update(['excluded_from_overhead' => true]);
        $this->assertSame(100 * 1000.0, CostingCalculator::forBoning($boning->fresh(), 1000, $typed)->calculate()['overhead_total']);
        $hides->update(['name' => 'LAIN-LAIN']);
        $this->assertSame(100 * 1000.0, CostingCalculator::forBoning($boning->fresh(), 1000, $typed)->calculate()['overhead_total']);
    }

    /** @test */
    public function money_typed_with_thousand_separators_is_read_and_shown_back_the_same(): void
    {
        $this->assertSame(203437.5, \App\Services\CostingPrices::parseMoney('203.437,5'));
        $this->assertSame(217000.0, \App\Services\CostingPrices::parseMoney('217.000'));
        $this->assertSame(1234567.0, \App\Services\CostingPrices::parseMoney('1.234.567'));
        $this->assertSame(138281.25, \App\Services\CostingPrices::parseMoney('138.281,25'));
        $this->assertSame(3000.0, \App\Services\CostingPrices::parseMoney(3000));
        $this->assertSame(0.0, \App\Services\CostingPrices::parseMoney(''));

        $this->assertSame('203.437,5', \App\Services\CostingPrices::formatMoney(203437.5));
        $this->assertSame('217.000', \App\Services\CostingPrices::formatMoney('217000.00'));
        $this->assertSame('0', \App\Services\CostingPrices::formatMoney(0));
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

        $this->lockWithDrylog($boning);

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
