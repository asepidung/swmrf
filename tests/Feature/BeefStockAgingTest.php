<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BeefStockAgingResource\Pages\ListBeefStockAgings;
use App\Models\BeefStock;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockTake;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan dari penyisiran modul Beef Stock, 14 September 2026. Kategori
 * [A], disetujui Hafizh: `BeefStockAgingResource` bocor barcode utuh
 * selama opname berjalan -- aturan penyamarannya sudah ada di
 * `BeefStocksRelationManager`, cuma tidak ditiru ke laporan aging.
 */
class BeefStockAgingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_barcode_is_masked_while_a_beef_count_is_running(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($user);

        // CHILL dibuat lebih dulu supaya id-nya 1 -- satu-satunya grade
        // berumur pendek menurut ShelfLife::shortLivedGradeIds().
        $chill = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $product = Product::create([
            'name' => 'SIRLOIN',
            'code' => 'B001',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        $barcode = '0'.now()->subDays(90)->format('dmy').'B00110150001234567';

        BeefStock::create([
            'barcode' => $barcode,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'grade_id' => $chill->id,
            'weight' => 10,
            'qty_pcs' => 1,
            'pack_date' => now()->subDays(90)->toDateString(),
            'origin' => '1',
            'status' => 'IN_STOCK',
        ]);

        $this->assertFalse(StockTake::isCounting());

        Livewire::test(ListBeefStockAgings::class)
            ->assertSee($barcode);

        StockTake::create([
            'document_number' => 'ST#001',
            'period' => now()->format('Y-m'),
            'date' => now()->toDateString(),
            'status' => StockTake::STATUS_IN_PROGRESS,
            'created_by' => $user->id,
        ]);

        $this->assertTrue(StockTake::isCounting());

        Livewire::test(ListBeefStockAgings::class)
            ->assertDontSee($barcode)
            ->assertSee(substr($barcode, 0, -6).'******');
    }
}
