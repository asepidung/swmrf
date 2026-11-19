<?php

namespace Tests\Feature;

use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\Grade;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** LG-01: stok awal hasil impor (tanpa baris masuk) diberi baris pembuka supaya posisi tanggal mundur benar. */
class WriteOpeningStockMovementsTest extends TestCase
{
    use RefreshDatabase;

    private function siapkan(): array
    {
        $user = User::factory()->create(['role' => 'programmer']);
        $category = \App\Models\ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        $product = Product::create(['name' => 'TENDERLOIN', 'code' => 'MT001', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        $wh = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        // Stok awal yang masih ada, dan stok awal yang sudah keluar (hanya baris KELUAR).
        BeefStock::create(['barcode' => 'AWAL1', 'product_id' => $product->id, 'warehouse_id' => $wh->id, 'grade_id' => $grade->id,
            'weight' => 10, 'qty_pcs' => 1, 'pack_date' => '2026-10-01', 'origin' => '1', 'status' => 'IN_STOCK']);
        DB::table('beef_stock_movements')->insert([
            'product_id' => $product->id, 'warehouse_id' => $wh->id, 'condition' => (string) $grade->id, 'barcode' => 'AWAL2',
            'transaction_type' => 'TALLY', 'weight_in' => 0, 'weight_out' => 5, 'pcs_in' => 0, 'pcs_out' => 1,
            'created_by' => $user->id, 'created_at' => '2026-11-05 10:00:00', 'updated_at' => '2026-11-05 10:00:00',
        ]);

        return [$product, $wh, $grade];
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->siapkan();

        $this->artisan('stock:write-opening', ['--date' => '2026-10-30'])->assertSuccessful();

        $this->assertSame(1, DB::table('beef_stock_movements')->count());
    }

    public function test_apply_makes_the_position_on_an_earlier_date_correct_and_is_repeatable(): void
    {
        [$product] = $this->siapkan();

        $this->artisan('stock:write-opening', ['--date' => '2026-10-30', '--apply' => true])->assertSuccessful();
        $this->artisan('stock:write-opening', ['--date' => '2026-10-30', '--apply' => true])->assertSuccessful();

        $this->assertSame(3, DB::table('beef_stock_movements')->count(), 'Dua barcode, masing-masing satu baris pembuka; ulangan tidak menambah.');
        $this->assertSame(10.0, (float) DB::table('beef_stock_movements')->where('barcode', 'AWAL1')->where('transaction_type', 'OPENING_BALANCE')->value('weight_in'));
        $this->assertSame(5.0, (float) DB::table('beef_stock_movements')->where('barcode', 'AWAL2')->where('transaction_type', 'OPENING_BALANCE')->value('weight_in'));

        // 2 November: stok awal 15 kg ada semua; 10 November: AWAL2 sudah keluar -> 10 kg.
        $pada = fn (string $tgl) => (float) DB::table('beef_stock_movements')->where('product_id', $product->id)
            ->where('created_at', '<=', $tgl.' 23:59:59')->selectRaw('SUM(weight_in) - SUM(weight_out) as kg')->value('kg');
        $this->assertSame(15.0, $pada('2026-11-02'));
        $this->assertSame(10.0, $pada('2026-11-10'));
    }

    public function test_an_opening_stock_barcode_that_was_reverted_before_leaving_also_gets_an_opening_row(): void
    {
        [$product, $wh, $grade] = $this->siapkan();
        $user = User::first();

        // Stok awal: ditally (keluar), dikembalikan (masuk), lalu keluar ke repack -> baris masuknya ada, pembukanya tidak.
        foreach ([['TALLY', 0, 7, '2026-11-10 07:00:00'], ['TALLY_REVERT', 7, 0, '2026-11-10 07:10:00'], ['OUT_TO_REPACK', 0, 7, '2026-11-10 07:20:00']] as [$tipe, $in, $out, $waktu]) {
            DB::table('beef_stock_movements')->insert([
                'product_id' => $product->id, 'warehouse_id' => $wh->id, 'condition' => (string) $grade->id, 'barcode' => 'AWAL3',
                'transaction_type' => $tipe, 'weight_in' => $in, 'weight_out' => $out, 'pcs_in' => 0, 'pcs_out' => 0,
                'created_by' => $user->id, 'created_at' => $waktu, 'updated_at' => $waktu,
            ]);
        }

        $this->artisan('stock:write-opening', ['--date' => '2026-11-02', '--apply' => true])->assertSuccessful();

        $this->assertSame(7.0, (float) DB::table('beef_stock_movements')->where('barcode', 'AWAL3')->where('transaction_type', 'OPENING_BALANCE')->value('weight_in'));
        $net = (float) DB::table('beef_stock_movements')->where('barcode', 'AWAL3')->selectRaw('SUM(weight_in) - SUM(weight_out) as n')->value('n');
        $this->assertSame(0.0, $net, 'Buku besar barcode yang sudah habis harus nol, bukan minus.');
    }

    public function test_opening_balance_is_registered_as_an_incoming_type(): void
    {
        $this->assertSame('in', BeefStockMovement::TYPES['OPENING_BALANCE']);
    }

    public function test_a_date_is_required(): void
    {
        $this->artisan('stock:write-opening')->assertFailed();
    }
}
