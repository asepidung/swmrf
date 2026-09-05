<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\BeefStock;
use App\Models\Warehouse;
use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;
use App\Filament\Admin\Resources\BeefStockResource\Pages\ListBeefStocks;
use App\Filament\Admin\Resources\BeefStockResource\Pages\ViewBeefStock;
use App\Filament\Admin\Resources\BeefStockResource\RelationManagers\BeefStocksRelationManager;

class BeefStockTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Warehouse $jonggol;
    protected Warehouse $perum;
    protected Grade $chill;
    protected Grade $frozen;
    protected Product $product1;
    protected Product $product2;
    protected ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'programmer',
            'is_active' => true
        ]);

        $this->jonggol = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->perum = Warehouse::create(['code' => 'PERUM', 'name' => 'PERUM', 'is_active' => true]);

        $this->chill = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $this->frozen = Grade::create(['name' => 'FROZEN', 'is_active' => true]);

        $this->category = ProductCategory::create([
            'name' => 'MEAT',
            'prefix' => 'MT',
            'is_active' => true,
        ]);

        $this->product1 = Product::create([
            'name' => 'SIRLOIN',
            'code' => 'B001',
            'category_id' => $this->category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        $this->product2 = Product::create([
            'name' => 'RIBEYE',
            'code' => 'B002',
            'category_id' => $this->category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_calculates_correct_stock_summaries_for_warehouses_and_grades()
    {
        // Add stock to product1 (Sirloin)
        BeefStock::create([
            'barcode' => 'BC001',
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->jonggol->id, // 1
            'grade_id' => $this->chill->id, // 1
            'weight' => 10.50,
            'qty_pcs' => 1,
            'pack_date' => now(),
            'status' => 'IN_STOCK',
        ]);

        BeefStock::create([
            'barcode' => 'BC002',
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->jonggol->id, // 1
            'grade_id' => $this->frozen->id, // 2
            'weight' => 15.00,
            'qty_pcs' => 2,
            'pack_date' => now(),
            'status' => 'IN_STOCK',
        ]);

        BeefStock::create([
            'barcode' => 'BC003',
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->perum->id, // 2
            'grade_id' => $this->chill->id, // 1
            'weight' => 20.00,
            'qty_pcs' => 3,
            'pack_date' => now(),
            'status' => 'IN_STOCK',
        ]);

        BeefStock::create([
            'barcode' => 'BC004',
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->perum->id, // 2
            'grade_id' => $this->frozen->id, // 2
            'weight' => 25.50,
            'qty_pcs' => 4,
            'pack_date' => now(),
            'status' => 'IN_STOCK',
        ]);

        // Add out of stock item (should not be counted)
        BeefStock::create([
            'barcode' => 'BC005',
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->jonggol->id,
            'grade_id' => $this->chill->id,
            'weight' => 100.00,
            'qty_pcs' => 10,
            'pack_date' => now(),
            'status' => 'OUT_TO_REPACK', // sold or repacked
        ]);

        Livewire::actingAs($this->user)
            ->test(ListBeefStocks::class)
            ->assertSee('SIRLOIN')
            ->assertSee('10.50')
            ->assertSee('15.00')
            ->assertSee('20.00')
            ->assertSee('25.50')
            ->assertSee('71.00'); // total weight
    }

    /** @test */
    public function it_filters_out_empty_stock_by_default()
    {
        // product1 has stock, product2 has NO stock
        BeefStock::create([
            'barcode' => 'BC001',
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->jonggol->id,
            'grade_id' => $this->chill->id,
            'weight' => 10.50,
            'qty_pcs' => 1,
            'pack_date' => now(),
            'status' => 'IN_STOCK',
        ]);

        Livewire::actingAs($this->user)
            ->test(ListBeefStocks::class)
            ->assertSee('SIRLOIN')
            ->assertDontSee('RIBEYE');

        // If we turn off hide_empty filter, we should see both
        Livewire::actingAs($this->user)
            ->test(ListBeefStocks::class)
            ->set('tableFilters.hide_empty.isActive', false)
            ->assertSee('SIRLOIN')
            ->assertSee('RIBEYE');
    }

    /** @test */
    public function it_shows_detailed_box_list_and_allows_manual_deletion_with_movement_logging()
    {
        $stock = BeefStock::create([
            'barcode' => 'BC_DELETE_TEST',
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->jonggol->id,
            'grade_id' => $this->chill->id,
            'weight' => 10.50,
            'qty_pcs' => 1,
            'pack_date' => now()->subDays(5),
            'status' => 'IN_STOCK',
        ]);

        // Access view page
        Livewire::actingAs($this->user)
            ->test(ViewBeefStock::class, ['record' => $this->product1->getKey()])
            ->assertSuccessful();

        // Test relation manager detailed listing and delete action
        Livewire::actingAs($this->user)
            ->test(BeefStocksRelationManager::class, [
                'ownerRecord' => $this->product1,
                'pageClass' => ViewBeefStock::class
            ])
            ->assertSee('BC_DELETE_TEST')
            ->assertSee('005 days') // age display
            ->callTableAction('delete', $stock, ['reason' => 'Fisiknya tidak ada di rak']);

        // Verify stock is removed from DB
        $this->assertDatabaseMissing('beef_stocks', ['barcode' => 'BC_DELETE_TEST']);

        // Verify a VOID_STOCK movement log is created
        //
        // Termasuk SIAPA yang menghapus dan KENAPA. `BeefStock` tidak memakai
        // hapus lunak, jadi baris pergerakan ini satu-satunya yang tersisa;
        // sebelumnya ia ditulis tanpa `created_by` -- satu-satunya pergerakan
        // stok di seluruh aplikasi yang tidak punya nama pelakunya.
        $this->assertDatabaseHas('beef_stock_movements', [
            'barcode' => 'BC_DELETE_TEST',
            'transaction_type' => 'VOID_STOCK',
            'weight_out' => 10.50,
            'created_by' => $this->user->getKey(),
            'note' => 'Fisiknya tidak ada di rak',
        ]);
    }

    /** Alasannya wajib: tanpa itu, penghapusannya tidak boleh jadi. */
    public function test_deleting_stock_without_a_reason_is_refused(): void
    {
        $stock = BeefStock::create([
            'barcode' => 'BC_NO_REASON',
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->jonggol->id,
            'grade_id' => $this->chill->id,
            'weight' => 7.25,
            'qty_pcs' => 1,
            'pack_date' => now()->subDays(2),
            'status' => 'IN_STOCK',
        ]);

        Livewire::actingAs($this->user)
            ->test(BeefStocksRelationManager::class, [
                'ownerRecord' => $this->product1,
                'pageClass' => ViewBeefStock::class,
            ])
            ->callTableAction('delete', $stock, ['reason' => ''])
            ->assertHasTableActionErrors(['reason']);

        $this->assertDatabaseHas('beef_stocks', ['barcode' => 'BC_NO_REASON']);
    }
}
