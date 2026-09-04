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
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rendemen karkas, dan syarat mengunci sebuah batch boning.
 *
 * SUSUT BONING SENGAJA TIDAK DIHITUNG. Keputusan Project Owner, 5 September
 * 2026, dan alasannya ada pada bentuk pekerjaannya: kulit dan offal diberi
 * label DI DALAM dokumen boning yang sama -- satu-satunya pintu agar keduanya
 * punya stok, karena kontraktor mengambilnya hari itu juga lewat DO yang butuh
 * SO, Tally, dan stok. Akibatnya hasil sebuah boning memuat barang yang tidak
 * berasal dari karkasnya, dan tiap batch akan terbaca hasilnya jauh melebihi
 * bahannya. Alarm palsu pada SETIAP dokumen.
 *
 * Yang tetap diukur: RENDEMEN KARKAS -- berapa persen bobot hidup yang menjadi
 * karkas. Ia tidak menyentuh hasil boning sama sekali, sudah ada di aplikasi
 * lama, dan hilangnya saat ditulis ulang adalah regresi.
 */
class CarcassYieldTest extends TestCase
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

    // =====================================================================
    // Syarat mengunci sebuah batch boning
    // =====================================================================

    /**
     * Boning WAJIB punya karkas.
     *
     * Karkasnya dipilih saat dokumennya dibuat -- Project Owner: "pas create
     * boning kan kita pilih karkas mana yang di boning bahkan bisa pilih
     * beberapa karkas". Boning yang tidak menyebut karkasnya adalah dokumen
     * yang belum selesai dibuat.
     */
    public function test_a_boning_without_a_carcass_cannot_be_locked(): void
    {
        $boning = Boning::create([
            'boning_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $this->expectException(\RuntimeException::class);

        $boning->lock();
    }

    public function test_a_boning_without_output_cannot_be_locked(): void
    {
        $karkas = $this->karkas([['hidup' => 500.00, 'a' => 150.00, 'b' => 150.00, 'kulit' => 30.00, 'buntut' => 0.00]]);
        $boning = $this->boning($karkas, []);

        $this->expectException(\RuntimeException::class);

        $boning->lock();
    }

    public function test_a_locked_boning_cannot_be_locked_twice(): void
    {
        $karkas = $this->karkas([['hidup' => 500.00, 'a' => 150.00, 'b' => 150.00, 'kulit' => 30.00, 'buntut' => 0.00]]);
        $boning = $this->boning($karkas, [200.00]);

        $boning->lock();

        $this->assertTrue($boning->fresh()->kunci);
        $this->assertSame('LOCKED', $boning->fresh()->status);

        $this->expectException(\RuntimeException::class);

        $boning->fresh()->lock();
    }

    public function test_unlocking_reopens_the_batch(): void
    {
        $karkas = $this->karkas([['hidup' => 500.00, 'a' => 150.00, 'b' => 150.00, 'kulit' => 30.00, 'buntut' => 0.00]]);
        $boning = $this->boning($karkas, [200.00]);

        $boning->lock();
        $boning->fresh()->unlock();

        $this->assertFalse($boning->fresh()->kunci);
        $this->assertSame('OPEN', $boning->fresh()->status);
    }

    /**
     * Susut boning TIDAK dihitung, dan itu disengaja.
     *
     * Kulit dan offal diberi label di dalam dokumen boning yang sama, jadi
     * hasilnya memuat barang yang tidak berasal dari karkasnya. Menghitung
     * susutnya akan menghasilkan alarm palsu pada SETIAP dokumen -- dan alarm
     * yang selalu menyala mengajari orang mengabaikannya.
     *
     * Penjaga ini menahan fiturnya dihidupkan kembali tanpa sengaja.
     */
    public function test_boning_deliberately_has_no_shrinkage_calculation(): void
    {
        foreach (['inputWeight', 'outputWeight', 'shrinkWeight', 'shrinkPercent', 'isWithinShrinkLimit'] as $metode) {
            $this->assertFalse(
                method_exists(Boning::class, $metode),
                "Boning::{$metode}() dihidupkan kembali. Baca alur satu batch boning di agents.md lebih dulu.",
            );
        }
    }
}
