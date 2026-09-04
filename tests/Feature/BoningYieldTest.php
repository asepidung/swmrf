<?php

namespace Tests\Feature;

use App\Models\Boning;
use App\Models\BoningCarcass;
use App\Models\BoningItem;
use App\Models\Carcass;
use App\Models\CarcassItem;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseCattle;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Boning: karkas yang masuk dibanding produk yang keluar.
 *
 * Berat karkasnya SUDAH tercatat sejak lama -- di `carcass_items`, per ekor,
 * sebagai belahan A, belahan B, kulit, dan buntut. Yang tidak ada hanyalah
 * satu baris kode yang membacanya: sampai 4 September 2026 kata `weight` cuma
 * muncul SEKALI di seluruh `BoningResource`, dan itu pun `->weight('bold')` --
 * ketebalan huruf, bukan berat.
 *
 * Yang masuk boning: belahan A + belahan B + buntut. Kulit dan jeroan tidak
 * ikut, keduanya dijual langsung ke kontraktor. Keputusan Project Owner, 4
 * September 2026, dan rumus yang sama persis ada di aplikasi lama.
 */
class BoningYieldTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private Warehouse $warehouse;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);

        $this->warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $this->product = Product::create([
            'name' => 'TENDERLOIN', 'code' => 'B001',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    /**
     * Satu dokumen karkas beserta sapinya.
     *
     * @param  array<int, array{hidup: float, a: float, b: float, kulit: float, buntut: float}>  $ekor
     */
    private function karkas(array $ekor): Carcass
    {
        $supplier = Supplier::create([
            'name' => 'TEGUH AMANAH', 'address' => 'Bogor', 'pic' => 'Teguh', 'top_days' => 30,
        ]);

        $class = CattleClass::firstOrCreate(['name' => 'STEER'], ['is_active' => true]);

        $po = PurchaseCattle::create([
            'supplier_id' => $supplier->id,
            'shipping_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $po->items()->create([
            'cattle_class_id' => $class->id,
            'qty' => max(count($ekor), 1),
            'price' => 55000,
            'created_by' => $this->user->id,
        ]);

        $receiving = CattleReceiving::create([
            'purchase_cattle_id' => $po->id,
            'supplier_id' => $supplier->id,
            'receive_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $weighing = CattleWeighing::create([
            'cattle_receiving_id' => $receiving->id,
            'weighing_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $carcass = Carcass::create([
            'cattle_weighing_id' => $weighing->id,
            'kill_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        foreach ($ekor as $i => $e) {
            $eartag = 'EAR-'.$carcass->id.'-'.$i;

            $receivingItem = $receiving->items()->create([
                'cattle_class_id' => $class->id,
                'eartag' => $eartag,
                'initial_weight' => $e['hidup'],
            ]);

            $weighingItem = $weighing->items()->create([
                'cattle_receiving_item_id' => $receivingItem->id,
                'cattle_class_id' => $class->id,
                'eartag' => $eartag,
                'initial_weight' => $e['hidup'],
                'actual_weight' => $e['hidup'],
            ]);

            CarcassItem::create([
                'carcass_id' => $carcass->id,
                'cattle_weighing_item_id' => $weighingItem->id,
                'carcass_1' => $e['a'],
                'carcass_2' => $e['b'],
                'hides' => $e['kulit'],
                'tail' => $e['buntut'],
            ]);
        }

        return $carcass->fresh();
    }

    /** @param  array<int, float>  $hasil */
    private function boning(Carcass $karkas, array $hasil): Boning
    {
        $boning = Boning::create([
            'boning_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        BoningCarcass::create(['boning_id' => $boning->id, 'carcass_id' => $karkas->id]);

        foreach ($hasil as $i => $berat) {
            BoningItem::create([
                'boning_id' => $boning->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'grade_id' => $this->grade->id,
                'barcode' => 'HASIL-'.$boning->id.'-'.$i,
                'weight' => $berat,
                'qty_pcs' => 1,
                'pack_date' => now()->toDateString(),
                'created_by' => $this->user->id,
            ]);
        }

        return $boning->fresh();
    }

    // =====================================================================
    // Rendemen karkas -- angka yang HILANG saat aplikasi ditulis ulang
    // =====================================================================

    /**
     * Rendemen karkas: berapa persen bobot hidup yang menjadi karkas.
     *
     * Angka baku di rumah potong, dan sudah ADA di aplikasi lama sebagai
     * `$totalCarcass / $totalLive * 100`. Ia hilang saat ditulis ulang.
     *
     * Angkanya diambil dari laporan sungguhan 27 Agustus 2026 supaya bisa
     * dibandingkan langsung dengan kertas yang dipegang orang lapangan.
     */
    public function test_it_measures_carcass_yield_against_live_weight(): void
    {
        $karkas = $this->karkas([
            ['hidup' => 531.00, 'a' => 149.96, 'b' => 158.98, 'kulit' => 34.60, 'buntut' => 0.00],
            ['hidup' => 564.00, 'a' => 158.96, 'b' => 162.94, 'kulit' => 32.10, 'buntut' => 0.00],
        ]);

        $this->assertSame(1095.00, $karkas->liveWeight());
        $this->assertSame(630.84, $karkas->carcassWeight());
        $this->assertSame(66.70, $karkas->hidesWeight());

        // 630,84 / 1.095,00 = 57,61%
        $this->assertSame(57.61, $karkas->yieldPercent());
    }

    /**
     * Yang masuk boning: karkas DITAMBAH buntut. Kulit tidak ikut.
     *
     * Kulit dan jeroan dijual langsung ke kontraktor dan tidak pernah masuk
     * ruang boning. Buntut ikut, karena ia menjadi oxtail -- produk jual yang
     * keluar dari boning. Kalau buntutnya tidak dihitung sebagai bahan,
     * oxtail yang keluar akan membuat hasil tampak LEBIH BERAT daripada
     * bahannya.
     */
    public function test_the_boning_input_is_carcass_plus_tail_but_never_hides(): void
    {
        $karkas = $this->karkas([
            ['hidup' => 516.00, 'a' => 150.02, 'b' => 153.48, 'kulit' => 36.80, 'buntut' => 24.90],
        ]);

        $this->assertSame(303.50, $karkas->carcassWeight());
        $this->assertSame(24.90, $karkas->tailWeight());
        $this->assertSame(36.80, $karkas->hidesWeight());

        // 303,50 + 24,90 -- kulitnya TIDAK ikut
        $this->assertSame(328.40, $karkas->boningInputWeight());
    }

    public function test_a_carcass_without_live_weight_has_no_yield_at_all(): void
    {
        $karkas = $this->karkas([]);

        $this->assertNull($karkas->yieldPercent());
    }

    // =====================================================================
    // Susut boning
    // =====================================================================

    public function test_it_measures_the_carcass_that_went_in_against_the_products_that_came_out(): void
    {
        $karkas = $this->karkas([
            ['hidup' => 531.00, 'a' => 150.00, 'b' => 150.00, 'kulit' => 34.60, 'buntut' => 10.00],
        ]);

        $boning = $this->boning($karkas, [200.00, 100.00]);

        $this->assertSame(310.00, $boning->inputWeight());
        $this->assertSame(300.00, $boning->outputWeight());
        $this->assertSame(10.00, $boning->shrinkWeight());
        $this->assertSame(3.23, $boning->shrinkPercent());
    }

    public function test_a_boning_without_carcass_has_no_percentage_at_all(): void
    {
        $boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id]);

        $this->assertNull($boning->shrinkPercent());
    }

    public function test_without_a_limit_nothing_is_held_back(): void
    {
        $karkas = $this->karkas([['hidup' => 500.00, 'a' => 150.00, 'b' => 150.00, 'kulit' => 30.00, 'buntut' => 0.00]]);
        $boning = $this->boning($karkas, [100.00]);

        $this->assertNull(Boning::shrinkLimitPercent());
        $this->assertTrue($boning->isWithinShrinkLimit());

        $boning->lock();

        $this->assertTrue($boning->fresh()->kunci);
        $this->assertNull($boning->fresh()->yield_override_at);
    }

    public function test_beyond_the_limit_it_refuses_without_a_reason(): void
    {
        Setting::write(Setting::BONING_MAX_SHRINK_PERCENT, 5, $this->user->id);

        $karkas = $this->karkas([['hidup' => 500.00, 'a' => 150.00, 'b' => 150.00, 'kulit' => 30.00, 'buntut' => 0.00]]);
        $boning = $this->boning($karkas, [200.00]);

        $this->assertFalse($boning->isWithinShrinkLimit());
        $this->expectException(\RuntimeException::class);

        $boning->lock();
    }

    public function test_beyond_the_limit_it_locks_with_a_written_reason(): void
    {
        Setting::write(Setting::BONING_MAX_SHRINK_PERCENT, 5, $this->user->id);

        $karkas = $this->karkas([['hidup' => 500.00, 'a' => 150.00, 'b' => 150.00, 'kulit' => 30.00, 'buntut' => 0.00]]);
        $boning = $this->boning($karkas, [200.00]);

        $boning->lock('Karkasnya berlemak tebal, trimming banyak.', $this->user->id);

        $tersimpan = $boning->fresh();

        $this->assertTrue($tersimpan->kunci);
        $this->assertSame('Karkasnya berlemak tebal, trimming banyak.', $tersimpan->yield_override_reason);
        $this->assertSame($this->user->id, $tersimpan->yield_override_by);
        $this->assertTrue($tersimpan->shrinkLimitWasOverridden());
    }

    /**
     * Hasil LEBIH BERAT daripada karkasnya selalu di luar batas.
     */
    public function test_output_heavier_than_the_carcass_is_never_within_any_limit(): void
    {
        Setting::write(Setting::BONING_MAX_SHRINK_PERCENT, 100, $this->user->id);

        $karkas = $this->karkas([['hidup' => 500.00, 'a' => 150.00, 'b' => 150.00, 'kulit' => 30.00, 'buntut' => 0.00]]);
        $boning = $this->boning($karkas, [500.00]);

        $this->assertSame(-200.00, $boning->shrinkWeight());
        $this->assertFalse($boning->isWithinShrinkLimit());
    }

    /**
     * Boning TANPA KARKAS tetap bisa dikunci.
     *
     * Kulit dan jeroan masuk stok lewat dokumen boning tersendiri yang memang
     * tidak punya karkas -- Project Owner menyebutnya "bikin boning baru,
     * bikin label kulit dan offal". Sempat ada syarat harus punya karkas, dan
     * syarat itu akan membuat dokumen semacam itu tidak pernah bisa dikunci.
     *
     * Susutnya memang tidak bisa dinilai, dan itu terbaca sendiri: persennya
     * `null`, dan daftarnya menuliskan "tanpa karkas" alih-alih angka.
     * Menahannya bukan cara menyampaikan itu.
     */
    public function test_a_boning_without_a_carcass_can_still_be_locked(): void
    {
        $boning = Boning::create([
            'boning_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        BoningItem::create([
            'boning_id' => $boning->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'barcode' => 'KULIT-001',
            'weight' => 449.81,
            'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $boning->fresh()->lock();

        $this->assertTrue($boning->fresh()->kunci);
        $this->assertNull($boning->fresh()->shrinkPercent());
    }

    /**
     * Berat offal SAMA PERSIS dengan berat bahan boning, dan itu disengaja.
     *
     * Jeroannya tidak pernah ditimbang; angka ini dipakai sebagai beratnya
     * menurut kesepakatan yang berlaku di rumah potong modern. Contoh Project
     * Owner: karkas A 100 + karkas B 110 + buntut 40 = offal 250.
     */
    public function test_the_offal_weight_is_the_carcass_plus_tail_by_agreement(): void
    {
        $karkas = $this->karkas([
            ['hidup' => 500.00, 'a' => 100.00, 'b' => 110.00, 'kulit' => 30.00, 'buntut' => 40.00],
        ]);

        $this->assertSame(250.00, $karkas->offalWeight());
        $this->assertSame(250.00, $karkas->boningInputWeight());
    }

    public function test_a_boning_without_output_cannot_be_locked(): void
    {
        $karkas = $this->karkas([['hidup' => 500.00, 'a' => 150.00, 'b' => 150.00, 'kulit' => 30.00, 'buntut' => 0.00]]);
        $boning = $this->boning($karkas, []);

        $this->expectException(\RuntimeException::class);

        $boning->lock();
    }

    public function test_unlocking_clears_the_override_trace(): void
    {
        Setting::write(Setting::BONING_MAX_SHRINK_PERCENT, 5, $this->user->id);

        $karkas = $this->karkas([['hidup' => 500.00, 'a' => 150.00, 'b' => 150.00, 'kulit' => 30.00, 'buntut' => 0.00]]);
        $boning = $this->boning($karkas, [200.00]);
        $boning->lock('Alasan lama', $this->user->id);

        $boning->fresh()->unlock();

        $tersimpan = $boning->fresh();

        $this->assertFalse($tersimpan->kunci);
        $this->assertNull($tersimpan->yield_override_reason);
        $this->assertFalse($tersimpan->shrinkLimitWasOverridden());
    }
}
