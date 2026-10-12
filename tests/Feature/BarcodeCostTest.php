<?php

namespace Tests\Feature;

use App\Models\BarcodeCost;
use App\Models\Boning;
use App\Models\BoningItem;
use App\Models\Costing;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BarcodeCosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * HPP tahap 2, PR 1 (issue #522): buku nilai per barcode (`barcode_costs`).
 *
 * Yang dijaga: barcode boning lahir belum bernilai (NULL, bukan 0); costing
 * dikunci mengisi SEMUA barcode lot itu -- mundur, termasuk yang sudah berpindah;
 * dikunci ulang menimpa dan merambat ke turunan relabel; semuanya idempoten dan
 * tercatat di activity log.
 */
class BarcodeCostTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $tenderloin;

    private Product $cube;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($this->user);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        $this->tenderloin = Product::create(['name' => 'TENDERLOIN', 'code' => 'MT001', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        $this->cube = Product::create(['name' => 'CUBE ROLL', 'code' => 'MT002', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
    }

    private function boning(): Boning
    {
        return Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id, 'kunci' => true, 'status' => 'LOCKED']);
    }

    private function label(Boning $boning, Product $product, string $barcode, float $weight = 10): BoningItem
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'JGL'], ['name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::firstOrCreate(['name' => 'CHILL'], ['is_active' => true]);

        return BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'grade_id' => $grade->id, 'weight' => $weight, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(),
            'barcode' => $barcode, 'created_by' => $this->user->id,
        ]);
    }

    /** Costing draft untuk boning itu, dengan hpp per produk. */
    private function costing(Boning $boning, array $hppPerProduct): Costing
    {
        $costing = Costing::create([
            'costing_date' => now()->toDateString(), 'boning_id' => $boning->id, 'purchase_cost' => 1000,
            'total_sales_value' => 1000, 'ratio_k' => 1, 'overhead_per_kg' => 0, 'total_kg' => 10, 'profit' => 0,
        ]);

        foreach ($hppPerProduct as $productId => $hpp) {
            $costing->items()->create([
                'product_id' => $productId, 'weight_kg' => 10, 'gross_price' => $hpp, 'net_price' => $hpp,
                'sales_value' => $hpp * 10, 'hpp_per_kg' => $hpp,
            ]);
        }

        return $costing;
    }

    private function cost(string $barcode): ?float
    {
        return BarcodeCosts::for($barcode);
    }

    // ---------------------------------------------------------------------
    // Lahir belum bernilai
    // ---------------------------------------------------------------------

    /** @test */
    public function a_boning_barcode_is_born_unvalued_and_not_zero(): void
    {
        $this->label($this->boning(), $this->tenderloin, 'BC-1');

        $row = BarcodeCost::where('barcode', 'BC-1')->first();

        $this->assertNotNull($row);
        $this->assertNull($row->cost_per_kg, 'Belum bernilai = NULL, bukan 0.');
        $this->assertSame(BarcodeCost::SOURCE_BONING, $row->source_type);
        $this->assertSame($this->tenderloin->id, $row->product_id);
        $this->assertNull(BarcodeCosts::for('BC-1'));
        $this->assertNull(BarcodeCosts::valueOf('BC-1', 5), 'Nilai barcode yang belum bernilai tidak dihitung nol.');
        $this->assertNull(BarcodeCosts::for('TIDAK-ADA'));
    }

    // ---------------------------------------------------------------------
    // Costing dikunci
    // ---------------------------------------------------------------------

    /** @test */
    public function locking_a_costing_values_every_barcode_of_that_boning_by_product(): void
    {
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');
        $this->label($boning, $this->tenderloin, 'TL-2');
        $this->label($boning, $this->cube, 'CR-1');

        $costing = $this->costing($boning, [$this->tenderloin->id => 104087.0, $this->cube->id => 81783.0]);
        $costing->lock();

        $this->assertSame(104087.0, $this->cost('TL-1'));
        $this->assertSame(104087.0, $this->cost('TL-2'));
        $this->assertSame(81783.0, $this->cost('CR-1'));
        $this->assertSame(1040870.0, BarcodeCosts::valueOf('TL-1', 10.0));

        $row = BarcodeCost::where('barcode', 'TL-1')->first();
        $this->assertSame($costing->id, (int) $row->source_id);
        $this->assertNotNull($row->valued_at);
    }

    /** @test */
    public function barcodes_of_other_bonings_are_left_alone(): void
    {
        $mine = $this->boning();
        $other = $this->boning();
        $this->label($mine, $this->tenderloin, 'MINE');
        $this->label($other, $this->tenderloin, 'OTHER');

        $this->costing($mine, [$this->tenderloin->id => 100000.0])->lock();

        $this->assertSame(100000.0, $this->cost('MINE'));
        $this->assertNull($this->cost('OTHER'));
    }

    /** @test */
    public function a_product_missing_from_the_costing_stays_unvalued(): void
    {
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');
        $this->label($boning, $this->cube, 'CR-1');

        $this->costing($boning, [$this->tenderloin->id => 100000.0])->lock();

        $this->assertSame(100000.0, $this->cost('TL-1'));
        $this->assertNull($this->cost('CR-1'), 'Produk yang tidak ada di costing tidak boleh ditebak.');
        $this->assertNotNull(BarcodeCost::where('barcode', 'CR-1')->first(), 'Barisnya tetap ada.');
    }

    /** @test */
    public function locking_values_a_barcode_that_has_already_moved_on(): void
    {
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');

        // Barcode sudah direlabel sebelum costing dikunci (keputusan Owner 1: mundur).
        BarcodeCosts::relabel('TL-1', 'TL-1-R', $this->tenderloin->id);
        $this->assertNull($this->cost('TL-1-R'));

        $this->costing($boning, [$this->tenderloin->id => 90000.0])->lock();

        $this->assertSame(90000.0, $this->cost('TL-1'));
        $this->assertSame(90000.0, $this->cost('TL-1-R'), 'Turunan relabel ikut bernilai.');
    }

    // ---------------------------------------------------------------------
    // Relabel
    // ---------------------------------------------------------------------

    /** @test */
    public function a_relabelled_barcode_copies_the_value_and_remembers_its_origin(): void
    {
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');
        $this->costing($boning, [$this->tenderloin->id => 90000.0])->lock();

        $row = BarcodeCosts::relabel('TL-1', 'TL-1-R', $this->tenderloin->id);

        $this->assertSame(90000.0, $row->cost_per_kg);
        $this->assertSame(BarcodeCost::SOURCE_RELABEL, $row->source_type);
        $this->assertSame('TL-1', $row->source_barcode);
    }

    /** @test */
    public function a_chain_of_relabels_is_valued_through_every_step(): void
    {
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'A');
        BarcodeCosts::relabel('A', 'B', $this->tenderloin->id);
        BarcodeCosts::relabel('B', 'C', $this->tenderloin->id);

        $this->costing($boning, [$this->tenderloin->id => 75000.0])->lock();

        $this->assertSame(75000.0, $this->cost('B'));
        $this->assertSame(75000.0, $this->cost('C'));
    }

    /** @test */
    public function propagation_stops_at_the_depth_limit_and_survives_a_cycle(): void
    {
        BarcodeCost::create(['barcode' => 'X', 'cost_per_kg' => 10, 'source_type' => 'relabel', 'source_barcode' => 'Y']);
        BarcodeCost::create(['barcode' => 'Y', 'cost_per_kg' => 20, 'source_type' => 'relabel', 'source_barcode' => 'X']);

        // Putaran X <-> Y tidak boleh membuat rambatan tak berujung.
        $this->assertIsInt(BarcodeCosts::propagateFrom('X'));
    }

    // ---------------------------------------------------------------------
    // Kunci ulang
    // ---------------------------------------------------------------------

    /** @test */
    public function locking_again_after_unlock_overwrites_the_barcode_and_its_descendants(): void
    {
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');
        $costing = $this->costing($boning, [$this->tenderloin->id => 100000.0]);
        $costing->lock();
        BarcodeCosts::relabel('TL-1', 'TL-1-R', $this->tenderloin->id);

        $costing->unlock();
        $this->assertSame(100000.0, $this->cost('TL-1'), 'Unlock membiarkan nilai yang ada sampai dikunci ulang.');

        $costing->items()->update(['hpp_per_kg' => 110000]);
        $costing->fresh()->lock();

        $this->assertSame(110000.0, $this->cost('TL-1'));
        $this->assertSame(110000.0, $this->cost('TL-1-R'));
    }

    /** @test */
    public function valuing_twice_changes_nothing_the_second_time(): void
    {
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');
        $costing = $this->costing($boning, [$this->tenderloin->id => 100000.0]);
        $costing->lock();

        $logs = Activity::where('log_name', 'barcode_costs')->count();

        $this->assertSame(0, BarcodeCosts::valueBoning($costing->fresh()));
        $this->assertSame($logs, Activity::where('log_name', 'barcode_costs')->count(), 'Idempoten: tidak ada log baru.');
    }

    /** @test */
    public function every_change_of_value_is_in_the_activity_log(): void
    {
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');
        $costing = $this->costing($boning, [$this->tenderloin->id => 100000.0]);
        $costing->lock();

        $costing->unlock();
        $costing->items()->update(['hpp_per_kg' => 120000]);
        $costing->fresh()->lock();

        $row = BarcodeCost::where('barcode', 'TL-1')->first();
        $changes = Activity::where('log_name', 'barcode_costs')
            ->where('subject_type', BarcodeCost::class)->where('subject_id', $row->id)->get();

        $this->assertTrue($changes->contains(fn ($a) => (string) ($a->properties['old']['cost_per_kg'] ?? '') === '100000'
            && (string) ($a->properties['attributes']['cost_per_kg'] ?? '') === '120000'), 'Nilai lama dan baru tercatat.');
    }

    /** @test */
    public function a_failed_lock_values_nothing(): void
    {
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');
        $costing = $this->costing($boning, [$this->tenderloin->id => 100000.0]);
        $costing->items()->update(['flag' => \App\Models\CostingItem::FLAG_NO_PRICE]);

        try {
            $costing->lock();
            $this->fail('Costing dengan produk tanpa harga tidak boleh terkunci.');
        } catch (\RuntimeException) {
        }

        $this->assertNull($this->cost('TL-1'));
    }

    // ---------------------------------------------------------------------
    // "Hajar semua": stok produk yang sama yang belum bernilai ikut diberi
    // HPP costing yang dikunci (keputusan Owner, 12 Oktober 2026)
    // ---------------------------------------------------------------------

    /** Stok di tangan; $cost false = barcode belum punya baris sama sekali. */
    private function stock(string $barcode, \App\Models\Product $product, float|false|null $cost, string $source = 'boning', string $status = 'IN_STOCK'): void
    {
        $warehouse = \App\Models\Warehouse::firstOrCreate(['code' => 'JGL'], ['name' => 'JONGGOL', 'is_active' => true]);
        $grade = \App\Models\Grade::firstOrCreate(['name' => 'CHILL'], ['is_active' => true]);

        \App\Models\BeefStock::create([
            'barcode' => $barcode, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'grade_id' => $grade->id,
            'weight' => 10, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1', 'status' => $status,
        ]);

        if ($cost !== false) {
            BarcodeCost::updateOrCreate(['barcode' => $barcode], ['product_id' => $product->id, 'cost_per_kg' => $cost, 'source_type' => $source]);
        }
    }

    /** @test */
    public function locking_a_costing_also_values_unvalued_stock_of_the_same_product(): void
    {
        $this->stock('OLD-1', $this->tenderloin, null);     // ada baris, belum bernilai
        $this->stock('OLD-2', $this->tenderloin, false);    // stok migrasi, belum punya baris
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');

        $this->costing($boning, [$this->tenderloin->id => 90000.0])->lock();

        $this->assertSame(90000.0, $this->cost('OLD-1'));
        $this->assertSame(90000.0, $this->cost('OLD-2'));
        $this->assertSame(BarcodeCost::SOURCE_OPENING, BarcodeCost::where('barcode', 'OLD-2')->value('source_type'));
    }

    /** @test */
    public function stock_that_already_has_a_value_is_not_overwritten(): void
    {
        $this->stock('KNOWN', $this->tenderloin, 70000.0);
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');

        $this->costing($boning, [$this->tenderloin->id => 90000.0])->lock();

        $this->assertSame(70000.0, $this->cost('KNOWN'));
    }

    /** @test */
    public function only_stock_still_in_the_warehouse_and_only_products_of_the_costing_are_valued(): void
    {
        $this->stock('SOLD', $this->tenderloin, null, 'boning', 'SHIPPED');
        $this->stock('OTHER-PRODUCT', $this->cube, null);
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');

        $this->costing($boning, [$this->tenderloin->id => 90000.0])->lock();

        $this->assertNull($this->cost('SOLD'), 'Yang sudah keluar dari stok tidak disentuh.');
        $this->assertNull($this->cost('OTHER-PRODUCT'), 'Produk lain tidak disentuh.');
    }

    /** @test */
    public function an_unvalued_repack_result_is_left_to_the_repack_valuation(): void
    {
        $this->stock('REPACK-OUT', $this->tenderloin, null, BarcodeCost::SOURCE_REPACK);
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');

        $this->costing($boning, [$this->tenderloin->id => 90000.0])->lock();

        $this->assertNull($this->cost('REPACK-OUT'), 'Nilai hasil repack milik valuasi repack, yang menunggu bahannya.');
    }

    /** @test */
    public function the_unvalued_stock_follows_the_costing_when_it_is_locked_again(): void
    {
        $this->stock('OLD-1', $this->tenderloin, false);
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');
        $costing = $this->costing($boning, [$this->tenderloin->id => 90000.0]);
        $costing->lock();

        $costing->unlock();
        $costing->items()->update(['hpp_per_kg' => 100000]);
        $costing->fresh()->lock();

        $this->assertSame(100000.0, $this->cost('OLD-1'), 'Nilai yang diisi costing ini sendiri ikut diperbarui.');
    }

    /** @test */
    public function a_newer_costing_does_not_overwrite_stock_valued_by_an_older_one(): void
    {
        $this->stock('OLD-1', $this->tenderloin, false);
        $first = $this->boning();
        $this->label($first, $this->tenderloin, 'TL-A');
        $this->costing($first, [$this->tenderloin->id => 90000.0])->lock();

        $second = $this->boning();
        $this->label($second, $this->tenderloin, 'TL-B');
        $this->costing($second, [$this->tenderloin->id => 120000.0])->lock();

        $this->assertSame(90000.0, $this->cost('OLD-1'), 'Sudah punya nilai: tidak ditimpa costing berikutnya.');
        $this->assertSame(120000.0, $this->cost('TL-B'));
    }

    /** @test */
    public function the_stock_valued_this_way_spreads_to_its_relabels(): void
    {
        $this->stock('OLD-1', $this->tenderloin, null);
        BarcodeCosts::relabel('OLD-1', 'OLD-1-R', $this->tenderloin->id);
        $boning = $this->boning();
        $this->label($boning, $this->tenderloin, 'TL-1');

        $this->costing($boning, [$this->tenderloin->id => 90000.0])->lock();

        $this->assertSame(90000.0, $this->cost('OLD-1-R'));
    }
}
