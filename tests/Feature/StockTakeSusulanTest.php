<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\StockTakeResource\Pages\ListStockTakes;
use App\Models\BeefStock;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran Stock Take (daging), 15 September 2026.
 */
class StockTakeSusulanTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $gudang;

    private Grade $chill;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gudang = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->chill = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $this->produk = Product::create([
            'name' => 'SIRLOIN', 'code' => 'B001',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    private function opnameBerjalan(): StockTake
    {
        return StockTake::create([
            'document_number' => 'ST-'.uniqid(),
            'period' => now()->format('Y-m'), 'date' => now()->toDateString(),
            'status' => StockTake::STATUS_IN_PROGRESS, 'summary_note' => 'Uji',
        ]);
    }

    private function programmer(): User
    {
        return User::factory()->create(['role' => 'programmer', 'is_active' => true]);
    }

    // =========================================================================
    // exp_date ikut disalin saat temuan UNEXPECTED dipromosikan ke beef_stocks
    // =========================================================================

    /** @test */
    public function finishing_copies_the_expiry_date_from_the_unexpected_finding_to_the_new_stock(): void
    {
        $this->actingAs($this->programmer());
        $opname = $this->opnameBerjalan();

        StockTakeItem::create([
            'stock_take_id' => $opname->id,
            'barcode' => '0'.now()->format('dmy').'0001000000110000550001',
            'product_id' => $this->produk->id,
            'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id,
            'weight' => 10.0, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'exp_date' => now()->addMonths(3)->toDateString(),
            'status' => 'UNEXPECTED',
            'is_manual' => true,
        ]);

        Livewire::test(ListStockTakes::class)
            ->callTableAction('finish', $opname);

        $stok = BeefStock::first();
        $this->assertNotNull($stok);
        $this->assertNotNull($stok->exp_date, 'exp_date tidak ikut disalin saat temuan dipromosikan ke beef_stocks.');
        $this->assertSame(now()->addMonths(3)->toDateString(), $stok->exp_date->toDateString());
    }

    // =========================================================================
    // Finish Opname: izin lewat authorize(), dan tidak bisa diklik dobel
    // =========================================================================

    /** @test */
    public function finishing_without_the_permission_does_not_touch_stock(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_stock_takes'], ['module_name' => 'x', 'description' => 'x'])->id
        );
        $this->actingAs($user);

        $opname = $this->opnameBerjalan();
        StockTakeItem::create([
            'stock_take_id' => $opname->id,
            'barcode' => '0'.now()->format('dmy').'0001000000110000550001',
            'product_id' => $this->produk->id, 'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id, 'weight' => 10.0, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'status' => 'UNEXPECTED', 'is_manual' => true,
        ]);

        Livewire::test(ListStockTakes::class)
            ->mountTableAction('finish', $opname)
            ->callMountedTableAction();

        $this->assertSame(0, BeefStock::count());
        $this->assertSame(StockTake::STATUS_IN_PROGRESS, $opname->fresh()->status);
    }

    /** @test */
    public function finishing_twice_in_a_row_does_not_crash_or_double_process(): void
    {
        $this->actingAs($this->programmer());
        $opname = $this->opnameBerjalan();
        StockTakeItem::create([
            'stock_take_id' => $opname->id,
            'barcode' => '0'.now()->format('dmy').'0001000000110000550001',
            'product_id' => $this->produk->id, 'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id, 'weight' => 10.0, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'status' => 'UNEXPECTED', 'is_manual' => true,
        ]);

        Livewire::test(ListStockTakes::class)->callTableAction('finish', $opname);

        // Kedua kali: status di basis data sudah COMPLETED, jadi baris ini
        // meniru klik ganda / dua admin -- tidak boleh mencoba memproses
        // ulang baris yang sama (yang akan menabrak unique barcode pada
        // BeefStock::create() kedua).
        Livewire::test(ListStockTakes::class)
            ->mountTableAction('finish', $opname->fresh())
            ->callMountedTableAction();

        $this->assertSame(1, BeefStock::count());
        $this->assertSame(StockTake::STATUS_COMPLETED, $opname->fresh()->status);
    }

    // =========================================================================
    // Barcode: constraint basis data sungguh melempar UniqueConstraintViolationException
    // =========================================================================

    /**
     * Menegaskan ASUMSI di balik `catch
     * (\Illuminate\Database\UniqueConstraintViolationException $e)` pada
     * aksi Manual Input: kalau asumsi kelasnya salah, catch itu tidak akan
     * pernah menggigit dan tabrakan barcode akan kembali meledak mentah.
     */
    /** @test */
    public function a_duplicate_barcode_on_stock_take_items_throws_the_exact_exception_class_the_action_catches(): void
    {
        $opname = $this->opnameBerjalan();
        $barcode = '0'.now()->format('dmy').'0001000000110000550001';

        StockTakeItem::create([
            'stock_take_id' => $opname->id, 'barcode' => $barcode,
            'product_id' => $this->produk->id, 'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id, 'weight' => 10.0, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'status' => 'UNEXPECTED', 'is_manual' => true,
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        StockTakeItem::create([
            'stock_take_id' => $opname->id, 'barcode' => $barcode,
            'product_id' => $this->produk->id, 'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id, 'weight' => 5.0, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'status' => 'UNEXPECTED', 'is_manual' => true,
        ]);
    }

    // =========================================================================
    // Route cetak: butuh view_stock_takes, bukan cuma login
    // =========================================================================

    /** @test */
    public function the_print_and_label_routes_require_view_stock_takes(): void
    {
        $opname = $this->opnameBerjalan();
        $item = StockTakeItem::create([
            'stock_take_id' => $opname->id,
            'barcode' => '0'.now()->format('dmy').'0001000000110000550001',
            'product_id' => $this->produk->id, 'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id, 'weight' => 10.0, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'status' => 'UNEXPECTED', 'is_manual' => true,
        ]);

        $orangLuar = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($orangLuar);

        $this->get(route('stock-take.print', $opname->id))->assertForbidden();
        $this->get(route('stock-take.label', $item->id))->assertForbidden();

        $orangLuar->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_stock_takes'], ['module_name' => 'x', 'description' => 'x'])->id
        );
        $this->actingAs($orangLuar->fresh());

        $this->get(route('stock-take.print', $opname->id))->assertSuccessful();
        $this->get(route('stock-take.label', $item->id))->assertSuccessful();
    }
}
