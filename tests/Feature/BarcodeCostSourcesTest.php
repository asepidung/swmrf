<?php

namespace Tests\Feature;

use App\Models\BarcodeCost;
use App\Models\BeefStock;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BarcodeCosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HPP tahap 2 (issue #522): dari mana nilai sebuah barcode datang selain boning --
 * GR Beef, retur jual, temuan opname. (Stok lama yang belum bernilai dinilai
 * oleh costing yang dikunci; lihat `BarcodeCostTest`.)
 */
class BarcodeCostSourcesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($this->user);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        $this->product = Product::create(['name' => 'TENDERLOIN', 'code' => 'MT001', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
    }

    /** Stok di tangan; $cost null = barcode belum bernilai, false = barcode belum punya baris sama sekali. */
    private function stock(string $barcode, float $kg, float|false|null $cost, ?Product $product = null, string $status = 'IN_STOCK'): void
    {
        $product ??= $this->product;
        $warehouse = Warehouse::firstOrCreate(['code' => 'JGL'], ['name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::firstOrCreate(['name' => 'CHILL'], ['is_active' => true]);

        BeefStock::create([
            'barcode' => $barcode, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'grade_id' => $grade->id,
            'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'exp_date' => now()->addYear()->toDateString(),
            'origin' => '1', 'status' => $status,
        ]);

        if ($cost !== false) {
            BarcodeCost::create(['barcode' => $barcode, 'product_id' => $product->id, 'cost_per_kg' => $cost, 'source_type' => 'boning']);
        }
    }

    // ---------------------------------------------------------------------
    // GR Beef
    // ---------------------------------------------------------------------

    /** @test */
    public function a_goods_receipt_barcode_is_valued_at_the_purchase_price_per_kg(): void
    {
        BarcodeCosts::fromGoodsReceipt('GR-1', $this->product->id, 72500.0, 9);

        $row = BarcodeCost::where('barcode', 'GR-1')->first();

        $this->assertSame(72500.0, $row->cost_per_kg);
        $this->assertSame(BarcodeCost::SOURCE_GOODS_RECEIPT, $row->source_type);
        $this->assertSame(9, (int) $row->source_id);
    }

    /** @test */
    public function a_goods_receipt_without_a_price_stays_unvalued_instead_of_zero(): void
    {
        BarcodeCosts::fromGoodsReceipt('GR-2', $this->product->id, 0.0);

        $this->assertNull(BarcodeCost::where('barcode', 'GR-2')->first()->cost_per_kg);
    }

    // ---------------------------------------------------------------------
    // Retur dan temuan: barcode lama membawa nilainya, yang baru dirata-rata
    // ---------------------------------------------------------------------

    /** @test */
    public function a_returned_barcode_that_is_already_known_keeps_its_own_value(): void
    {
        $this->stock('STOCK-A', 10, 100000.0);
        BarcodeCost::create(['barcode' => 'SOLD-1', 'product_id' => $this->product->id, 'cost_per_kg' => 80000.0, 'source_type' => 'boning']);

        $row = BarcodeCosts::fromAverage('SOLD-1', $this->product->id, BarcodeCost::SOURCE_SALES_RETURN, 5);

        $this->assertSame(80000.0, $row->cost_per_kg, 'Barcode lama kembali dengan nilainya sendiri, bukan rata-rata.');
        $this->assertSame(BarcodeCost::SOURCE_BONING, $row->source_type);
    }

    /** @test */
    public function a_new_barcode_gets_the_weighted_average_of_the_product_in_stock(): void
    {
        $this->stock('S-1', 10, 100000.0);
        $this->stock('S-2', 30, 80000.0);

        // (10 x 100.000 + 30 x 80.000) / 40 = 85.000
        $this->assertSame(85000.0, BarcodeCosts::averageInStock($this->product->id));

        $row = BarcodeCosts::fromAverage('MANUAL-1', $this->product->id, BarcodeCost::SOURCE_SALES_RETURN, 5);

        $this->assertSame(85000.0, $row->cost_per_kg);
        $this->assertSame(BarcodeCost::SOURCE_SALES_RETURN, $row->source_type);
    }

    /** @test */
    public function the_average_ignores_unvalued_barcodes_and_stock_that_is_gone(): void
    {
        $this->stock('S-1', 10, 100000.0);
        $this->stock('S-2', 50, null);                             // belum bernilai: tidak ikut
        $this->stock('S-3', 20, 40000.0, null, 'SHIPPED');         // sudah keluar: tidak ikut

        $this->assertSame(100000.0, BarcodeCosts::averageInStock($this->product->id));
    }

    /** @test */
    public function with_no_valued_stock_a_new_barcode_is_unvalued_and_flagged(): void
    {
        $this->stock('S-1', 10, null);

        $row = BarcodeCosts::fromAverage('MANUAL-1', $this->product->id, BarcodeCost::SOURCE_STOCK_TAKE);

        $this->assertNull($row->cost_per_kg, 'Tidak ada stok bernilai: NULL, bukan 0.');
        $this->assertNotNull($row->note, 'Ditandai supaya kelihatan kenapa kosong.');
        $this->assertNull(BarcodeCosts::averageInStock(999999));
    }
}
