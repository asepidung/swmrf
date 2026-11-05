<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\StockTakeAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Laporan analisa opname daging: disusun sistem dari data, hanya membaca. */
class StockTakeAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private StockTake $opname;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $gudang = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $this->produk = Product::create([
            'name' => 'SIRLOIN',
            'code' => 'B001',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        $this->opname = StockTake::create([
            'document_number' => 'ST#26001',
            'period' => 'Oktober 2026',
            'date' => '2026-11-03',
            'status' => StockTake::STATUS_COMPLETED,
        ]);

        $item = fn (string $barcode, string $status, float $w, bool $manual = false) => StockTakeItem::create([
            'stock_take_id' => $this->opname->id,
            'barcode' => $barcode,
            'product_id' => $this->produk->id,
            'warehouse_id' => $gudang->id,
            'grade_id' => $grade->id,
            'weight' => $w,
            'qty_pcs' => 1,
            'status' => $status,
            'is_manual' => $manual,
        ]);

        $item('B1', 'MATCHED', 10);
        $item('B2', 'MISSING', 12.88);   // berpasangan dengan B3
        $item('B3', 'UNEXPECTED', 12.88, true);
        $item('B4', 'MISSING', 7.40);    // tanpa pasangan
        $item('B5', 'UNEXPECTED', 5.00, true); // tanpa pasangan
    }

    public function test_angka_dan_pasangan_dihitung_dari_data(): void
    {
        $a = StockTakeAnalysis::for($this->opname);

        $this->assertSame(1, $a['matched_count']);
        $this->assertSame(2, $a['missing_count']);
        $this->assertSame(2, $a['found_count']);
        $this->assertEqualsWithDelta(20.28, $a['missing_kg'], 0.001);
        $this->assertEqualsWithDelta(17.88, $a['found_kg'], 0.001);
        $this->assertEqualsWithDelta(-2.40, $a['net_kg'], 0.001);
        $this->assertSame(1, $a['pair_count']);
        $this->assertSame(1, $a['unpaired_missing']->count());
        $this->assertSame('B4', $a['unpaired_missing']->first()->barcode);
        $this->assertSame('B5', $a['unpaired_found']->first()->barcode);
        $this->assertSame(2, $a['found_manual_count']);
    }

    public function test_halaman_laporan_tampil_untuk_yang_berizin(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $this->actingAs($user)->get(route('stock-take.analysis', $this->opname->id))
            ->assertOk()
            ->assertSee('Analisa Hasil Stock Opname ST#26001')
            ->assertSee('kekurangan')
            ->assertSee('Lampiran Detail')
            ->assertSee('B4');
    }

    public function test_tanpa_selisih_laporan_tidak_mengarang_cerita(): void
    {
        StockTakeItem::where('stock_take_id', $this->opname->id)->whereIn('status', ['MISSING', 'UNEXPECTED'])->delete();
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $this->actingAs($user)->get(route('stock-take.analysis', $this->opname->id))
            ->assertOk()
            ->assertSee('Tidak ada selisih')
            ->assertDontSee('Lampiran Detail')
            ->assertDontSee('pasangan temuan');
    }

    public function test_tanpa_izin_ditolak(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        $this->actingAs($user)->get(route('stock-take.analysis', $this->opname->id))->assertForbidden();
    }
}
