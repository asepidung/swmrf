<?php

namespace Tests\Feature;

use App\Models\BarcodeCost;
use App\Models\BeefStock;
use App\Models\CustomerGroup;
use App\Models\FinancialLoss;
use App\Models\Grade;
use App\Models\PriceList;
use App\Models\PriceListItem;
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
use Tests\TestCase;

/**
 * HPP tahap 2, PR 3 (issue #522): valuasi repack.
 *
 * Contoh wajib (angka diskusi Owner): bahan TL 10 kg @100.000 + CR 10 kg @80.000
 * = 1.800.000; hasil TL 9, CR 9, FQ 1, susut 1 kg -> kerugian 90.000; sisa
 * 1.710.000; net TL 140.000, CR 110.000, FQ 50.000 -> TL 104.087, CR 81.783,
 * FQ 37.174 (dibulatkan); jumlah kg x harga = 1.710.000. Angka-angka ini tidak
 * disesuaikan ke hasil kode.
 */
class RepackValuationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Grade $grade;

    private Product $tl;

    private Product $cr;

    private Product $fq;

    private PriceList $priceList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);

        $this->warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);

        $make = fn (string $name, string $code) => Product::create(['name' => $name, 'code' => $code, 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        $this->tl = $make('TENDERLOIN', 'MT001');
        $this->cr = $make('CUBE ROLL', 'MT002');
        $this->fq = $make('FQ 85 CL', 'MT003');

        // Harga acuan = harga umum (potongan 0%), jadi net = gross.
        $general = CustomerGroup::create(['name' => 'UMUM', 'is_general_price_reference' => true]);
        $this->priceList = PriceList::create(['customer_group_id' => $general->id, 'created_by' => $this->user->id]);
        $this->price($this->tl, 140000);
        $this->price($this->cr, 110000);
        $this->price($this->fq, 50000);
    }

    private function price(Product $product, float $price): void
    {
        PriceListItem::updateOrCreate(['price_list_id' => $this->priceList->id, 'product_id' => $product->id], ['price' => $price]);
    }

    /** Barcode bahan yang sudah bernilai (null = belum bernilai). */
    private function material(string $barcode, Product $product, ?float $cost): void
    {
        BarcodeCost::updateOrCreate(['barcode' => $barcode], ['product_id' => $product->id, 'cost_per_kg' => $cost, 'source_type' => 'boning']);
    }

    /**
     * @param  array<int, array{0: Product, 1: string, 2: float}>  $bahan  [produk, barcode, kg]
     * @param  array<int, array{0: Product, 1: float}>  $hasil  [produk, kg]
     */
    private function repack(array $bahan, array $hasil): Repack
    {
        $repack = Repack::create(['repack_date' => now()->toDateString(), 'created_by' => $this->user->id]);

        foreach ($bahan as [$product, $barcode, $kg]) {
            RepackMaterial::create([
                'repack_id' => $repack->id, 'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
                'barcode' => $barcode, 'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1', 'status' => 'IN_STOCK',
            ]);
        }

        foreach ($hasil as $i => [$product, $kg]) {
            $barcode = 'OUT-'.$repack->id.'-'.$i;

            RepackResult::create([
                'repack_id' => $repack->id, 'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
                'barcode' => $barcode, 'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(),
            ]);

            BeefStock::create([
                'barcode' => $barcode, 'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
                'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '2', 'status' => 'IN_STOCK',
            ]);
        }

        return $repack->fresh();
    }

    /** Repack contoh Owner. */
    private function ownerExample(): Repack
    {
        $this->material('BAHAN-TL', $this->tl, 100000);
        $this->material('BAHAN-CR', $this->cr, 80000);

        return $this->repack(
            [[$this->tl, 'BAHAN-TL', 10], [$this->cr, 'BAHAN-CR', 10]],
            [[$this->tl, 9], [$this->cr, 9], [$this->fq, 1]],
        );
    }

    private function cost(Repack $repack, int $i): ?float
    {
        return BarcodeCosts::for('OUT-'.$repack->id.'-'.$i);
    }

    // ---------------------------------------------------------------------
    // Contoh angka Owner
    // ---------------------------------------------------------------------

    /** @test */
    public function the_owner_example_gives_104087_81783_and_37174(): void
    {
        $repack = $this->ownerExample();
        $this->lockWithDrylog($repack);

        $this->assertSame(104087, (int) round($this->cost($repack, 0)));
        $this->assertSame(81783, (int) round($this->cost($repack, 1)));
        $this->assertSame(37174, (int) round($this->cost($repack, 2)));

        // Jumlah kg x harga = sisa biaya, sampai pembulatan per barcode.
        $total = 9 * $this->cost($repack, 0) + 9 * $this->cost($repack, 1) + 1 * $this->cost($repack, 2);
        $this->assertEqualsWithDelta(1710000.0, $total, 0.1);

        $valuation = $repack->fresh()->valuation;
        $this->assertSame(RepackValuation::STATUS_VALUED, $valuation->status);
        $this->assertSame(1800000.0, $valuation->total_input_cost);
        $this->assertSame(20.0, $valuation->total_input_kg);
        $this->assertSame(1.0, $valuation->shrink_kg);
        $this->assertSame(90000.0, $valuation->shrink_value);
        $this->assertFalse($valuation->needs_review);
    }

    /** @test */
    public function the_shrinkage_loss_gets_its_value_in_rupiah(): void
    {
        $repack = $this->ownerExample();
        $this->lockWithDrylog($repack);

        $loss = $repack->financialLoss()->first();

        $this->assertSame(1.0, (float) $loss->quantity);
        $this->assertSame(90000.0, (float) $loss->amount, '1 kg x rata-rata harga pokok bahan Rp 90.000.');
        $this->assertFalse($loss->isNotPricedYet());
    }

    /** @test */
    public function the_reference_prices_are_frozen_in_the_valuation(): void
    {
        $repack = $this->ownerExample();
        $this->lockWithDrylog($repack);

        $prices = $repack->fresh()->valuation->prices->keyBy('product_id');

        $this->assertSame(140000.0, $prices[$this->tl->id]->net_price);
        $this->assertSame(110000.0, $prices[$this->cr->id]->net_price);
        $this->assertSame(50000.0, $prices[$this->fq->id]->net_price);

        // Daftar harga berubah sesudah dikunci: nilai tidak bergeser, bahkan saat dihitung ulang.
        $this->price($this->tl, 999999);
        $before = $this->cost($repack, 0);

        RepackValuator::recalculate($repack->fresh());

        $this->assertSame($before, $this->cost($repack, 0));
    }

    // ---------------------------------------------------------------------
    // Hasil tanpa harga acuan
    // ---------------------------------------------------------------------

    /** @test */
    public function a_result_without_a_reference_price_splits_the_cost_by_weight_and_needs_review(): void
    {
        PriceListItem::where('product_id', $this->fq->id)->delete();
        $repack = $this->ownerExample();
        $this->lockWithDrylog($repack);

        // sisa 1.710.000 / 19 kg hasil = 90.000 untuk semua hasil.
        foreach ([0, 1, 2] as $i) {
            $this->assertSame(90000.0, $this->cost($repack, $i));
        }

        $valuation = $repack->fresh()->valuation;
        $this->assertTrue($valuation->needs_review);
        $this->assertSame(RepackValuation::STATUS_VALUED, $valuation->status);
    }

    // ---------------------------------------------------------------------
    // Bahan belum bernilai
    // ---------------------------------------------------------------------

    /** @test */
    public function an_unvalued_material_makes_the_repack_pending_and_the_results_null(): void
    {
        $this->material('BAHAN-TL', $this->tl, 100000);
        $this->material('BAHAN-CR', $this->cr, null);
        $repack = $this->repack(
            [[$this->tl, 'BAHAN-TL', 10], [$this->cr, 'BAHAN-CR', 10]],
            [[$this->tl, 9], [$this->cr, 9], [$this->fq, 1]],
        );

        $this->lockWithDrylog($repack);

        $valuation = $repack->fresh()->valuation;
        $this->assertSame(RepackValuation::STATUS_PENDING, $valuation->status);
        $this->assertNull($valuation->total_input_cost);
        $this->assertNull($this->cost($repack, 0), 'Hasil menunggu: NULL, bukan 0.');
        $this->assertSame(0.0, (float) $repack->financialLoss()->first()->amount, 'Nilai susut menunggu.');
    }

    /** @test */
    public function a_pending_repack_is_valued_automatically_once_every_material_has_a_value(): void
    {
        $this->material('BAHAN-TL', $this->tl, 100000);
        $this->material('BAHAN-CR', $this->cr, null);
        $repack = $this->repack(
            [[$this->tl, 'BAHAN-TL', 10], [$this->cr, 'BAHAN-CR', 10]],
            [[$this->tl, 9], [$this->cr, 9], [$this->fq, 1]],
        );
        $this->lockWithDrylog($repack);

        BarcodeCosts::set('BAHAN-CR', 80000.0, 'boning');
        BarcodeCosts::propagateFrom('BAHAN-CR');

        $this->assertSame(RepackValuation::STATUS_VALUED, $repack->fresh()->valuation->status);
        $this->assertSame(104087, (int) round($this->cost($repack, 0)));
        $this->assertSame(90000.0, (float) $repack->financialLoss()->first()->amount);
    }

    // ---------------------------------------------------------------------
    // Rambatan
    // ---------------------------------------------------------------------

    /** @test */
    public function a_changed_material_value_recalculates_the_repack_and_its_results_spread_on(): void
    {
        $repack = $this->ownerExample();
        $this->lockWithDrylog($repack);

        // Hasil pertama repack ini jadi bahan repack kedua.
        $second = $this->repack([[$this->tl, 'OUT-'.$repack->id.'-0', 9]], [[$this->tl, 9]]);
        $this->lockWithDrylog($second);

        $this->assertEqualsWithDelta($this->cost($repack, 0), $this->cost($second, 0), 0.01, 'Tanpa susut dan satu produk, nilainya sama.');

        // Bahan repack pertama berubah nilai -> repack pertama dan KEDUA dihitung ulang.
        BarcodeCosts::set('BAHAN-TL', 120000.0, 'boning');
        BarcodeCosts::propagateFrom('BAHAN-TL');

        // Biaya bahan 1.200.000 + 800.000 = 2.000.000; susut 1 kg x 100.000 -> sisa 1.900.000;
        // k = 1.900.000 / 2.300.000.
        $expected = 140000 * (1900000 / 2300000);
        $this->assertEqualsWithDelta($expected, $this->cost($repack, 0), 0.01);
        $this->assertEqualsWithDelta($expected, $this->cost($second, 0), 0.01, 'Rambatan sampai ke repack berikutnya.');
        $this->assertSame(100000.0, (float) $repack->financialLoss()->first()->amount);
    }

    /** @test */
    public function recalculating_without_a_change_does_nothing(): void
    {
        $repack = $this->ownerExample();
        $this->lockWithDrylog($repack);

        $this->assertSame(0, RepackValuator::recalculate($repack->fresh()));
    }

    // ---------------------------------------------------------------------
    // Tanpa susut, dan buka kunci
    // ---------------------------------------------------------------------

    /** @test */
    public function with_no_shrinkage_the_whole_cost_goes_to_the_results(): void
    {
        $this->material('BAHAN-TL', $this->tl, 100000);
        $repack = $this->repack([[$this->tl, 'BAHAN-TL', 10]], [[$this->tl, 10]]);

        $this->lockWithDrylog($repack);

        $this->assertEqualsWithDelta(100000.0, $this->cost($repack, 0), 0.01);
        $this->assertSame(0.0, $repack->fresh()->valuation->shrink_value);
        $this->assertNull($repack->financialLoss()->first(), 'Tanpa susut tidak ada baris kerugian.');
    }

    /** @test */
    public function unlocking_releases_the_values_and_the_valuation(): void
    {
        $repack = $this->ownerExample();
        $this->lockWithDrylog($repack);
        $second = $this->repack([[$this->tl, 'OUT-'.$repack->id.'-0', 9]], [[$this->tl, 9]]);
        $this->lockWithDrylog($second);

        $repack->fresh()->unlock();

        $this->assertNull($this->cost($repack, 0));
        $this->assertNull($repack->fresh()->valuation);
        $this->assertNull(FinancialLoss::where('lossable_type', Repack::class)->where('lossable_id', $repack->id)->first());
        $this->assertSame(RepackValuation::STATUS_PENDING, $second->fresh()->valuation->status, 'Repack berikutnya kehilangan bahannya yang bernilai.');
        $this->assertNull($this->cost($second, 0));
    }

    /** @test */
    public function locking_again_after_unlock_values_the_repack_again(): void
    {
        $repack = $this->ownerExample();
        $this->lockWithDrylog($repack);
        $repack->fresh()->unlock();

        $this->lockWithDrylog($repack->fresh());

        $this->assertSame(104087, (int) round($this->cost($repack, 0)));
        $this->assertSame(1, RepackValuation::where('repack_id', $repack->id)->count());
    }

    /** @test */
    public function a_result_barcode_is_born_unvalued_before_the_repack_is_locked(): void
    {
        $repack = $this->ownerExample();

        $row = BarcodeCost::where('barcode', 'OUT-'.$repack->id.'-0')->first();

        $this->assertNotNull($row);
        $this->assertNull($row->cost_per_kg);
        $this->assertSame(BarcodeCost::SOURCE_REPACK, $row->source_type);
    }

    /** @test */
    public function the_locked_repack_page_shows_the_valuation_block(): void
    {
        $repack = $this->ownerExample();
        $this->lockWithDrylog($repack);

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\RepackResource\Pages\MaterialUsageRepack::class, ['record' => $repack->getRouteKey()])
            ->assertSee(__('Repack Valuation'))
            ->assertSee('Rp 1.800.000')
            ->assertSee('Rp 90.000')
            ->assertSee('Rp 104.087');
    }

    /** @test */
    public function the_unlocked_repack_page_has_no_valuation_block(): void
    {
        $repack = $this->ownerExample();

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\RepackResource\Pages\MaterialUsageRepack::class, ['record' => $repack->getRouteKey()])
            ->assertDontSee(__('Repack Valuation'));
    }
}
