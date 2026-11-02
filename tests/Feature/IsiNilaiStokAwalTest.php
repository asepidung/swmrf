<?php

namespace Tests\Feature;

use App\Models\BarcodeCost;
use App\Models\BeefStock;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsiNilaiStokAwalTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csv = sys_get_temp_dir().'/harga-uji-'.uniqid().'.csv';
        file_put_contents($this->csv, "produk,harga_per_kg,dasar\nsirloin,126400,uji\nTidak Ada,1000,uji\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);
        parent::tearDown();
    }

    private function stok(string $barcode, string $status = 'IN_STOCK'): Product
    {
        $produk = Product::firstOrCreate(
            ['name' => 'SIRLOIN'],
            ['code' => 'B001', 'category_id' => ProductCategory::firstOrCreate(['name' => 'MEAT'], ['prefix' => 'B0', 'is_active' => true])->id, 'structure_type' => 'main', 'is_active' => true],
        );

        BeefStock::forceCreate([
            'barcode' => $barcode, 'product_id' => $produk->id, 'weight' => 10, 'qty_pcs' => 1, 'status' => $status, 'origin' => 'BONING', 'pack_date' => now()->toDateString(),
            'warehouse_id' => Warehouse::firstOrCreate(['code' => 'JGL'], ['name' => 'JONGGOL', 'is_active' => true])->id,
            'grade_id' => Grade::firstOrCreate(['name' => 'CHILL'], ['is_active' => true])->id,
        ]);

        return $produk;
    }

    private function jalankan(array $opsi = []): void
    {
        $this->artisan('costing:isi-stok-awal', ['--source' => $this->csv] + $opsi)->assertSuccessful();
    }

    public function test_the_dry_run_saves_nothing(): void
    {
        $this->stok('BC-1');

        $this->jalankan();

        $this->assertSame(0, BarcodeCost::count());
    }

    public function test_apply_values_unvalued_in_stock_barcodes_only(): void
    {
        $this->stok('BC-1');
        $this->stok('BC-TERJUAL', 'OUT');
        $produk = $this->stok('BC-SUDAH');
        BarcodeCost::create(['barcode' => 'BC-SUDAH', 'product_id' => $produk->id, 'cost_per_kg' => 100000, 'source_type' => 'boning']);
        $this->stok('BC-KOSONG');
        BarcodeCost::create(['barcode' => 'BC-KOSONG', 'product_id' => $produk->id, 'cost_per_kg' => null, 'source_type' => 'opening']);

        $this->jalankan(['--apply' => true]);

        $this->assertSame(126400.0, BarcodeCost::where('barcode', 'BC-1')->first()->cost_per_kg);
        $this->assertSame(126400.0, BarcodeCost::where('barcode', 'BC-KOSONG')->first()->cost_per_kg);
        $this->assertSame(100000.0, BarcodeCost::where('barcode', 'BC-SUDAH')->first()->cost_per_kg);
        $this->assertNull(BarcodeCost::where('barcode', 'BC-TERJUAL')->first());
    }

    public function test_repack_results_are_left_to_the_repack_valuation(): void
    {
        $produk = $this->stok('BC-RP');
        BarcodeCost::create(['barcode' => 'BC-RP', 'product_id' => $produk->id, 'cost_per_kg' => null, 'source_type' => 'repack']);

        $this->jalankan(['--apply' => true]);

        $this->assertNull(BarcodeCost::where('barcode', 'BC-RP')->first()->cost_per_kg);
    }

    public function test_barcodes_that_already_left_stock_are_valued_too_so_sales_and_repack_costs_fill_in(): void
    {
        $produk = $this->stok('BC-MASIH');
        $gudang = Warehouse::first();

        \Illuminate\Support\Facades\DB::table('beef_stock_movements')->insert([
            ['product_id' => $produk->id, 'warehouse_id' => $gudang->id, 'condition' => 'CHILL', 'barcode' => 'BC-TERJUAL-TALLY', 'transaction_type' => 'TALLY', 'weight_out' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $produk->id, 'warehouse_id' => $gudang->id, 'condition' => 'CHILL', 'barcode' => 'BC-BAHAN-REPACK', 'transaction_type' => 'OUT_TO_REPACK', 'weight_out' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $produk->id, 'warehouse_id' => $gudang->id, 'condition' => 'CHILL', 'barcode' => 'BC-HASIL-REPACK', 'transaction_type' => 'IN_REPACK', 'weight_in' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->jalankan(['--apply' => true]);

        $this->assertSame(126400.0, BarcodeCost::where('barcode', 'BC-TERJUAL-TALLY')->first()->cost_per_kg);
        $this->assertSame(126400.0, BarcodeCost::where('barcode', 'BC-BAHAN-REPACK')->first()->cost_per_kg);
        $this->assertNull(BarcodeCost::where('barcode', 'BC-HASIL-REPACK')->first());
    }
}
