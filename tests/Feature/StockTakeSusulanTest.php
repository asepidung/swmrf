<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\StockTakeResource;
use App\Filament\Admin\Resources\StockTakeResource\Pages\ListStockTakes;
use App\Filament\Admin\Resources\StockTakeResource\Pages\ScanStockTake;
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

    /**
     * `view_stock_takes` selalu ikut disertakan: `ScanStockTake` mewarisi
     * DUA gerbang `canAccess()` yang berbeda dan berjalan SENDIRI-SENDIRI --
     * punya halaman sendiri (`Filament\Pages\Concerns\CanAuthorizeAccess`,
     * yang baru ditambahkan di sini mensyaratkan `edit_stock_takes`) DAN
     * milik `StockTakeResource` (`CanAuthorizeResourceAccess`, bawaan,
     * mensyaratkan `view_stock_takes` lewat `canViewAny()`). Keduanya
     * dipasang Livewire sebagai hook `mount*` terpisah dan SAMA-SAMA harus
     * lolos -- tanpa `view_stock_takes` di sini, kasus "harus berhasil"
     * akan salah menuduh gerbang `edit_stock_takes` yang menolak, padahal
     * yang menolak gerbang `view_stock_takes` bawaan Resource.
     */
    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (array_unique([...$permissionNames, 'view_stock_takes']) as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Stock Takes', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function temuanUnexpected(\App\Models\StockTake $opname, ?string $barcode = null): StockTakeItem
    {
        return StockTakeItem::create([
            'stock_take_id' => $opname->id,
            'barcode' => $barcode ?? '0'.now()->format('dmy').'000100000011000055'.random_int(1000, 9999),
            'product_id' => $this->produk->id, 'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id, 'weight' => 10.0, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'status' => 'UNEXPECTED', 'is_manual' => true,
        ]);
    }

    // =========================================================================
    // Susulan batch 3, 17 September 2026: ScanStockTake tanpa canAccess() sama
    // sekali -- siapa pun yang login bisa memakai seluruh halaman ini.
    // =========================================================================

    /** @test */
    public function scan_page_is_closed_over_http_without_edit_stock_takes(): void
    {
        $opname = $this->opnameBerjalan();

        $this->actingAs($this->employee())
            ->get(StockTakeResource::getUrl('scan', ['record' => $opname]))
            ->assertForbidden();

        $this->actingAs($this->employee(['edit_stock_takes']))
            ->get(StockTakeResource::getUrl('scan', ['record' => $opname]))
            ->assertSuccessful();
    }

    /** @test */
    public function deleting_an_unexpected_finding_requires_the_same_permission(): void
    {
        $opname = $this->opnameBerjalan();
        $item = $this->temuanUnexpected($opname);

        Livewire::actingAs($this->employee(['edit_stock_takes']))
            ->test(ScanStockTake::class, ['record' => $opname])
            ->mountTableAction('delete', $item->id)
            ->callMountedTableAction();

        $this->assertDatabaseMissing('stock_take_items', ['id' => $item->id]);
    }

    /** @test */
    public function scanning_a_missing_item_after_the_count_has_just_finished_leaves_it_missing(): void
    {
        $opname = $this->opnameBerjalan();
        $item = StockTakeItem::create([
            'stock_take_id' => $opname->id,
            'barcode' => 'FX-STALE-SCAN-0001',
            'product_id' => $this->produk->id, 'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id, 'weight' => 10.0, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'status' => 'MISSING', 'is_manual' => true,
        ]);

        // Halaman dibuka SELAGI opname masih IN_PROGRESS.
        $test = Livewire::actingAs($this->employee(['edit_stock_takes']))
            ->test(ScanStockTake::class, ['record' => $opname]);

        // "Finish Opname" selesai dari sesi lain sebelum scan ini
        // benar-benar menulis.
        StockTake::whereKey($opname->id)->update(['status' => StockTake::STATUS_COMPLETED]);

        $test->set('barcode', $item->barcode)->call('scan');

        $this->assertSame('MISSING', $item->fresh()->status);
    }

    /**
     * `manualInputAction()` sebelumnya menulis tanpa mengecek status
     * dokumennya sama sekali.
     */
    /** @test */
    public function manual_input_after_the_count_has_finished_is_rejected(): void
    {
        $opname = $this->opnameBerjalan();

        $test = Livewire::actingAs($this->employee(['edit_stock_takes']))
            ->test(ScanStockTake::class, ['record' => $opname]);

        StockTake::whereKey($opname->id)->update(['status' => StockTake::STATUS_COMPLETED]);

        $test->mountAction('manualInput')
            ->setActionData([
                'warehouse_id' => $this->gudang->id,
                'product_id' => $this->produk->id,
                'grade_id' => $this->chill->id,
                'qty_pcs_combined' => '10.00/1',
            ])
            ->callMountedAction();

        $this->assertSame(0, StockTakeItem::where('stock_take_id', $opname->id)->count());
    }

    /** @test */
    public function opening_the_scan_page_for_a_finished_count_actually_redirects(): void
    {
        $opname = $this->opnameBerjalan();
        StockTake::whereKey($opname->id)->update(['status' => StockTake::STATUS_COMPLETED]);

        Livewire::actingAs($this->employee(['edit_stock_takes']))
            ->test(ScanStockTake::class, ['record' => $opname->fresh()])
            ->assertRedirect(StockTakeResource::getUrl('view', ['record' => $opname->id]));
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
