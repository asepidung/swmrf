<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RepackResource\Pages\MaterialUsageRepack;
use App\Models\BarcodeCost;
use App\Models\BeefStock;
use App\Models\FinancialLoss;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Repack;
use App\Models\RepackMaterial;
use App\Models\RepackResult;
use App\Models\RepackValuation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RepackValuator;
use App\Support\BarcodeCosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Valuasi repack, ATURAN FINAL (keputusan Owner 12 Oktober 2026, issue #522):
 * ikut-produk -> susut ke Financial Loss (rata-rata seluruh bahan) -> produk baru /
 * kilo kelebihan pakai rata-rata stok produk itu -> selisih dibagi ke produk yang
 * disesetan menurut porsi biaya terpakai.
 *
 * Dua contoh Owner wajib lulus apa adanya (angka tidak disesuaikan ke hasil kode):
 *  1. TDR 10 @100.000, CBR 10 @80.000, STR 10 @90.000 (2.700.000); hasil TDR 8,8,
 *     CBR 10, STR 8,9, FQ 1,5 (stok FQ 37.000); susut 0,8 -> FL 72.000; FQ 55.500;
 *     selisih 91.500 -> TDR 105.697,38/kg, STR 94.647,53/kg, CBR 80.000.
 *     (Angka TDR di komentar #522 tertulis 105.697,39: share dibulatkan terlalu dini;
 *     dikoreksi Hafizh -- presisi penuh sampai cost per kg akhir, hpp.md 11.3.)
 *  2. RUMP 10 @120.000 habis terpakai -> PAHA DEPAN 9,5 (stok 70.000), susut 0,5:
 *     selisih ditanggung PAHA DEPAN.
 */
class RepackValuationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Grade $grade;

    /** @var array<string, Product> */
    private array $p = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);

        $this->warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);

        foreach (['TDR' => 'MT001', 'CBR' => 'MT002', 'STR' => 'MT003', 'FQ' => 'MT004', 'RUMP' => 'MT005', 'PAHA' => 'MT006'] as $name => $code) {
            $this->p[$name] = Product::create(['name' => $name, 'code' => $code, 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        }
    }

    /** Barcode bahan yang sudah bernilai (null = belum bernilai). */
    private function material(string $barcode, string $product, ?float $cost): void
    {
        BarcodeCost::updateOrCreate(['barcode' => $barcode], ['product_id' => $this->p[$product]->id, 'cost_per_kg' => $cost, 'source_type' => 'boning']);
    }

    /** Stok produk yang sudah bernilai di gudang (dasar nilai produk baru). */
    private function stock(string $barcode, string $product, float $kg, float $cost): void
    {
        BeefStock::create([
            'barcode' => $barcode, 'product_id' => $this->p[$product]->id, 'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1', 'status' => 'IN_STOCK',
        ]);
        BarcodeCost::create(['barcode' => $barcode, 'product_id' => $this->p[$product]->id, 'cost_per_kg' => $cost, 'source_type' => 'boning']);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: float}>  $bahan  [produk, barcode, kg]
     * @param  array<int, array{0: string, 1: float}>  $hasil  [produk, kg]
     */
    private function repack(array $bahan, array $hasil): Repack
    {
        $repack = Repack::create(['repack_date' => now()->toDateString(), 'created_by' => $this->user->id]);

        foreach ($bahan as [$product, $barcode, $kg]) {
            RepackMaterial::create([
                'repack_id' => $repack->id, 'product_id' => $this->p[$product]->id, 'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
                'barcode' => $barcode, 'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1', 'status' => 'IN_STOCK',
            ]);
        }

        foreach ($hasil as $i => [$product, $kg]) {
            $barcode = 'OUT-'.$repack->id.'-'.$i;

            RepackResult::create([
                'repack_id' => $repack->id, 'product_id' => $this->p[$product]->id, 'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
                'barcode' => $barcode, 'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(),
            ]);

            BeefStock::create([
                'barcode' => $barcode, 'product_id' => $this->p[$product]->id, 'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
                'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '2', 'status' => 'IN_STOCK',
            ]);
        }

        return $repack->fresh();
    }

    /** Contoh Owner 1. */
    private function example1(): Repack
    {
        $this->material('B-TDR', 'TDR', 100000);
        $this->material('B-CBR', 'CBR', 80000);
        $this->material('B-STR', 'STR', 90000);
        $this->stock('S-FQ', 'FQ', 100, 37000);

        return $this->repack(
            [['TDR', 'B-TDR', 10], ['CBR', 'B-CBR', 10], ['STR', 'B-STR', 10]],
            [['TDR', 8.8], ['CBR', 10], ['STR', 8.9], ['FQ', 1.5]],
        );
    }

    /** Contoh Owner 2: RUMP habis terpakai menjadi PAHA DEPAN. */
    private function example2(): Repack
    {
        $this->material('B-RUMP', 'RUMP', 120000);
        $this->stock('S-PAHA', 'PAHA', 100, 70000);

        return $this->repack([['RUMP', 'B-RUMP', 10]], [['PAHA', 9.5]]);
    }

    private function cost(Repack $repack, int $i): ?float
    {
        return BarcodeCosts::for('OUT-'.$repack->id.'-'.$i);
    }

    /** Jumlah nilai hasil + nilai susut (pemeriksaan wajib: = jumlah biaya bahan). */
    private function resultsPlusShrink(Repack $repack): float
    {
        $total = (float) $repack->fresh()->valuation->shrink_value;

        foreach ($repack->results as $result) {
            $total += (float) $result->weight * $this->cost($repack, $repack->results->search(fn ($r) => $r->id === $result->id));
        }

        return $total;
    }

    // ---------------------------------------------------------------------
    // Dua contoh wajib Owner
    // ---------------------------------------------------------------------

    /** @test */
    public function owner_example_1_gives_105697_38_94647_53_and_80000(): void
    {
        $repack = $this->example1();
        $this->lockWithDrylog($repack);

        $this->assertEqualsWithDelta(105697.38, $this->cost($repack, 0), 0.005);   // TDR
        $this->assertEqualsWithDelta(80000.00, $this->cost($repack, 1), 0.005);    // CBR tetap
        $this->assertEqualsWithDelta(94647.53, $this->cost($repack, 2), 0.005);    // STR
        $this->assertEqualsWithDelta(37000.00, $this->cost($repack, 3), 0.005);    // FQ = nilai stok

        $valuation = $repack->fresh()->valuation;
        $this->assertSame(RepackValuation::STATUS_VALUED, $valuation->status);
        $this->assertSame(2700000.0, $valuation->total_input_cost);
        $this->assertSame(0.8, $valuation->shrink_kg);
        $this->assertSame(72000.0, $valuation->shrink_value);
        $this->assertSame(55500.0, $valuation->new_product_value);
        $this->assertSame(91500.0, $valuation->difference_value);
        $this->assertFalse($valuation->needs_review);
    }

    /** @test */
    public function owner_example_1_adds_up_to_the_cost_of_the_input_goods_and_the_shrinkage_is_the_loss(): void
    {
        $repack = $this->example1();
        $this->lockWithDrylog($repack);

        $this->assertEqualsWithDelta(2700000.0, $this->resultsPlusShrink($repack), 0.5, 'Nilai hasil + susut = biaya bahan.');

        $loss = $repack->financialLoss()->first();
        $this->assertSame(72000.0, (float) $loss->amount);
        $this->assertSame(0.8, (float) $loss->quantity);
    }

    /** @test */
    public function owner_example_2_lets_paha_depan_carry_the_difference(): void
    {
        $repack = $this->example2();
        $this->lockWithDrylog($repack);

        $valuation = $repack->fresh()->valuation;
        $this->assertSame(60000.0, $valuation->shrink_value, '0,5 kg x 120.000.');
        $this->assertSame(665000.0, $valuation->new_product_value, '9,5 kg x 70.000 (stok).');
        $this->assertSame(475000.0, $valuation->difference_value);

        // RUMP habis terpakai: bagiannya dititipkan ke PAHA DEPAN -> 70.000 + 475.000 / 9,5 = 120.000.
        $this->assertEqualsWithDelta(120000.0, $this->cost($repack, 0), 0.005);
        $this->assertEqualsWithDelta(1200000.0, $this->resultsPlusShrink($repack), 0.5);
        $this->assertSame(60000.0, (float) $repack->financialLoss()->first()->amount);
    }

    /** @test */
    public function the_difference_does_not_touch_other_products_in_the_same_document(): void
    {
        $this->material('B-RUMP', 'RUMP', 120000);
        $this->material('B-TDR', 'TDR', 100000);
        $this->stock('S-PAHA', 'PAHA', 100, 70000);
        $repack = $this->repack([['RUMP', 'B-RUMP', 10], ['TDR', 'B-TDR', 5]], [['PAHA', 9.5], ['TDR', 5]]);

        $this->lockWithDrylog($repack);

        $this->assertEqualsWithDelta(100000.0, $this->cost($repack, 1), 0.005, 'TDR utuh dan tidak ikut menanggung apa pun.');
        // susut 0,5 x rata-rata 113.333,33; selisih 1.200.000 - 665.000 - 56.666,67 = 478.333,33 -> PAHA DEPAN.
        $this->assertEqualsWithDelta((665000 + 478333.33) / 9.5, $this->cost($repack, 0), 0.01);
        $this->assertEqualsWithDelta(1700000.0, $this->resultsPlusShrink($repack), 0.5);
    }

    // ---------------------------------------------------------------------
    // Snapshot nilai stok, dan yang tidak punya stok
    // ---------------------------------------------------------------------

    /** @test */
    public function the_stock_value_of_the_new_product_is_frozen_when_the_repack_is_locked(): void
    {
        $repack = $this->example1();
        $this->lockWithDrylog($repack);

        $this->assertEqualsWithDelta(37000.0, (float) $repack->fresh()->valuation->prices()->where('product_id', $this->p['FQ']->id)->value('stock_cost_per_kg'), 0.005);

        // Stok FQ berubah nilainya sesudah dikunci: tidak menggeser repack ini, bahkan saat dihitung ulang.
        BarcodeCost::where('barcode', 'S-FQ')->update(['cost_per_kg' => 99999]);
        RepackValuator::recalculate($repack->fresh());

        $this->assertEqualsWithDelta(37000.0, $this->cost($repack, 3), 0.005);
    }

    /** @test */
    public function a_new_product_without_valued_stock_uses_the_material_average_and_needs_review(): void
    {
        $this->material('B-RUMP', 'RUMP', 120000);
        $repack = $this->repack([['RUMP', 'B-RUMP', 10]], [['PAHA', 9.5]]);

        $this->lockWithDrylog($repack);

        $valuation = $repack->fresh()->valuation;
        $this->assertTrue($valuation->needs_review);
        $this->assertSame(RepackValuation::STATUS_VALUED, $valuation->status);
        // Rata-rata bahan 120.000 sebagai nilai produk baru; selisih = 1.200.000 - 1.140.000 - 60.000 = 0.
        $this->assertEqualsWithDelta(120000.0, $this->cost($repack, 0), 0.005);
        $this->assertEqualsWithDelta(1200000.0, $this->resultsPlusShrink($repack), 0.5);
    }

    /** @test */
    public function a_difference_with_no_new_product_to_absorb_it_is_spread_over_the_results_and_needs_review(): void
    {
        $this->material('B-A', 'TDR', 100000);
        $this->material('B-B', 'CBR', 200000);
        $repack = $this->repack([['TDR', 'B-A', 10], ['CBR', 'B-B', 10]], [['TDR', 10]]);

        $this->lockWithDrylog($repack);

        // susut 10 kg x 150.000 = 1.500.000; selisih 2.000.000 - 1.500.000 = 500.000 -> ke satu-satunya hasil.
        $this->assertEqualsWithDelta(150000.0, $this->cost($repack, 0), 0.005);
        $this->assertTrue($repack->fresh()->valuation->needs_review);
        $this->assertEqualsWithDelta(3000000.0, $this->resultsPlusShrink($repack), 0.5);
    }

    // ---------------------------------------------------------------------
    // Ikut produk, tanpa susut
    // ---------------------------------------------------------------------

    /** @test */
    public function a_product_that_keeps_its_kilos_keeps_its_own_cost_and_there_is_no_loss(): void
    {
        $this->material('B-TDR', 'TDR', 100000);
        $repack = $this->repack([['TDR', 'B-TDR', 10]], [['TDR', 10]]);

        $this->lockWithDrylog($repack);

        $this->assertEqualsWithDelta(100000.0, $this->cost($repack, 0), 0.005);
        $this->assertSame(0.0, $repack->fresh()->valuation->shrink_value);
        $this->assertNull($repack->financialLoss()->first(), 'Tanpa susut tidak ada baris kerugian.');
    }

    // ---------------------------------------------------------------------
    // Menunggu, rambatan, buka kunci
    // ---------------------------------------------------------------------

    /** @test */
    public function an_unvalued_material_makes_the_repack_pending_and_the_results_null(): void
    {
        $this->material('B-TDR', 'TDR', 100000);
        $this->material('B-CBR', 'CBR', null);
        $this->stock('S-FQ', 'FQ', 100, 37000);
        $repack = $this->repack(
            [['TDR', 'B-TDR', 10], ['CBR', 'B-CBR', 10]],
            [['TDR', 9], ['CBR', 9], ['FQ', 1]],
        );

        $this->lockWithDrylog($repack);

        $valuation = $repack->fresh()->valuation;
        $this->assertSame(RepackValuation::STATUS_PENDING, $valuation->status);
        $this->assertNull($valuation->total_input_cost);
        $this->assertNull($this->cost($repack, 0), 'Hasil menunggu: NULL, bukan 0.');
        $this->assertSame(0.0, (float) $repack->financialLoss()->first()->amount);
    }

    /** @test */
    public function a_pending_repack_is_valued_automatically_once_every_material_has_a_value(): void
    {
        $this->material('B-TDR', 'TDR', 100000);
        $this->material('B-CBR', 'CBR', null);
        $repack = $this->repack([['TDR', 'B-TDR', 10], ['CBR', 'B-CBR', 10]], [['TDR', 10], ['CBR', 9]]);
        $this->lockWithDrylog($repack);

        BarcodeCosts::set('B-CBR', 80000.0, 'boning');
        BarcodeCosts::propagateFrom('B-CBR');

        $this->assertSame(RepackValuation::STATUS_VALUED, $repack->fresh()->valuation->status);
        // susut 1 kg x rata-rata 90.000; kilo CBR yang hilang berbiaya 80.000 -> selisih -10.000 ditanggung CBR.
        $this->assertEqualsWithDelta(100000.0, $this->cost($repack, 0), 0.005);
        $this->assertEqualsWithDelta((9 * 80000 - 10000) / 9, $this->cost($repack, 1), 0.01);
        $this->assertSame(90000.0, (float) $repack->financialLoss()->first()->amount);
        $this->assertEqualsWithDelta(1800000.0, $this->resultsPlusShrink($repack), 0.5);
    }

    /** @test */
    public function a_changed_material_value_recalculates_the_repack_and_its_results_spread_on(): void
    {
        $repack = $this->example1();
        $this->lockWithDrylog($repack);

        // Hasil TDR repack ini jadi bahan repack kedua (utuh).
        $second = $this->repack([['TDR', 'OUT-'.$repack->id.'-0', 8.8]], [['TDR', 8.8]]);
        $this->lockWithDrylog($second);
        $this->assertEqualsWithDelta($this->cost($repack, 0), $this->cost($second, 0), 0.01);

        // Bahan TDR repack pertama naik nilainya -> kedua repack dihitung ulang.
        BarcodeCosts::set('B-TDR', 120000.0, 'boning');
        BarcodeCosts::propagateFrom('B-TDR');

        $this->assertGreaterThan(105697.38, $this->cost($repack, 0));
        $this->assertEqualsWithDelta($this->cost($repack, 0), $this->cost($second, 0), 0.01, 'Rambatan sampai ke repack berikutnya.');
        // biaya bahan baru: 10 x 120.000 + 10 x 80.000 + 10 x 90.000 = 2.900.000
        $this->assertEqualsWithDelta(2900000.0, $this->resultsPlusShrink($repack), 0.5, 'Tetap cocok dengan biaya bahan yang baru.');
    }

    /** @test */
    public function recalculating_without_a_change_does_nothing(): void
    {
        $repack = $this->example1();
        $this->lockWithDrylog($repack);

        $this->assertSame(0, RepackValuator::recalculate($repack->fresh()));
    }

    /** @test */
    public function unlocking_releases_the_values_and_the_valuation(): void
    {
        $repack = $this->example1();
        $this->lockWithDrylog($repack);
        $second = $this->repack([['TDR', 'OUT-'.$repack->id.'-0', 8.8]], [['TDR', 8.8]]);
        $this->lockWithDrylog($second);

        $repack->fresh()->unlock();

        $this->assertNull($this->cost($repack, 0));
        $this->assertNull($repack->fresh()->valuation);
        $this->assertNull(FinancialLoss::where('lossable_type', Repack::class)->where('lossable_id', $repack->id)->first());
        $this->assertSame(RepackValuation::STATUS_PENDING, $second->fresh()->valuation->status);
        $this->assertNull($this->cost($second, 0));
    }

    /** @test */
    public function locking_again_after_unlock_values_the_repack_again(): void
    {
        $repack = $this->example1();
        $this->lockWithDrylog($repack);
        $repack->fresh()->unlock();

        $this->lockWithDrylog($repack->fresh());

        $this->assertEqualsWithDelta(105697.38, $this->cost($repack, 0), 0.005);
        $this->assertSame(1, RepackValuation::where('repack_id', $repack->id)->count());
    }

    /** @test */
    public function a_result_barcode_is_born_unvalued_before_the_repack_is_locked(): void
    {
        $repack = $this->example1();

        $row = BarcodeCost::where('barcode', 'OUT-'.$repack->id.'-0')->first();

        $this->assertNotNull($row);
        $this->assertNull($row->cost_per_kg);
        $this->assertSame(BarcodeCost::SOURCE_REPACK, $row->source_type);
    }

    // ---------------------------------------------------------------------
    // Halaman
    // ---------------------------------------------------------------------

    /** @test */
    public function the_locked_repack_page_shows_the_valuation_block(): void
    {
        $repack = $this->example1();
        $this->lockWithDrylog($repack);

        Livewire::test(MaterialUsageRepack::class, ['record' => $repack->getRouteKey()])
            ->assertSee(__('Repack Valuation'))
            ->assertSee('Rp 2.700.000')
            ->assertSee('Rp 72.000')
            ->assertSee('Rp 55.500')
            ->assertSee('Rp 91.500')
            ->assertSee('Rp 105.697');
    }

    /** @test */
    public function the_unlocked_repack_page_has_no_valuation_block(): void
    {
        $repack = $this->example1();

        Livewire::test(MaterialUsageRepack::class, ['record' => $repack->getRouteKey()])
            ->assertDontSee(__('Repack Valuation'));
    }
}
