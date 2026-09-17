<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource;
use App\Filament\Admin\Resources\MaterialStockTakeResource;
use App\Filament\Admin\Resources\SalesOrderResource;
use App\Filament\Admin\Resources\SalesOrderResource\Pages\ListSalesOrders;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Permission;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan sistemik, 17 September 2026: helper `authorize()` bawaan Filament
 * JATUH KE ALLOW kalau method ability (`deleteAny`/`forceDeleteAny`/
 * `restoreAny`) tidak ada di Policy-nya -- bukan tolak seperti dugaan wajar.
 * Dibaca langsung dari `vendor/filament/filament/src/helpers.php`: kalau
 * `Gate::getPolicyFor($model)` mengembalikan policy tapi
 * `method_exists($policy, $action)` false, DAN proyek ini tidak punya
 * `Gate::before`, jawabannya `Response::allow()`.
 *
 * NOL dari 50 Policy di app ini sebelumnya punya method `deleteAny`/
 * `forceDeleteAny`/`restoreAny` -- artinya SEMUA 28 Resource yang memakai
 * `DeleteBulkAction`/`ForceDeleteBulkAction`/`RestoreBulkAction` bawaan
 * Filament mengizinkan siapa pun (bahkan tanpa izin apa pun) melewati
 * gerbang bulk-nya, terlepas dari `delete_*`/`view_deleted_*` yang
 * sebenarnya berlaku untuk aksi tunggal.
 *
 * Awalnya disangka gerbangnya bobol TAPI datanya "aman" (test
 * `Livewire::test()` awal tidak berhasil menghapus apa pun) -- itu SALAH.
 * Sebabnya: fixture `SalesOrder` waktu itu memakai `delivery_date` BESOK,
 * dan `SalesOrderResource` punya filter tanggal diam-diam di tabelnya
 * (`delivery_date <= hari ini`, aktif sejak render pertama walau tidak
 * disentuh pengguna) yang menyaring baris tanggal depan dari
 * `getSelectedTableRecords()` -- bukan otorisasi yang menahan, cuma filter
 * yang tidak berhubungan. Begitu diulang dengan `delivery_date` HARI INI
 * (kasus umum), `callTableBulkAction('delete', ...)` SUNGGUH menghapus
 * baris sebagai user yang cuma punya `view_sales_orders` -- lihat
 * `test_bulk_deleting_a_sales_order_no_longer_succeeds_for_an_unprivileged_user`
 * di bawah, yang membuktikan itu SUDAH DITAMBAL.
 */
class PolicyAnyMethodsTest extends TestCase
{
    use RefreshDatabase;

    /** @return \Generator<string> */
    private function berkasPolicy(): \Generator
    {
        foreach (glob(app_path('Policies/*.php')) as $berkas) {
            yield $berkas;
        }
    }

    /** @test */
    public function every_policy_defines_viewany_deleteany_forcedeleteany_and_restoreany(): void
    {
        $pelanggar = [];

        foreach ($this->berkasPolicy() as $berkas) {
            $namaKelas = 'App\\Policies\\'.basename($berkas, '.php');

            if (! class_exists($namaKelas)) {
                continue;
            }

            $refleksi = new \ReflectionClass($namaKelas);

            foreach (['viewAny', 'deleteAny', 'forceDeleteAny', 'restoreAny'] as $method) {
                if (! $refleksi->hasMethod($method)) {
                    $pelanggar[] = basename($berkas).":{$method}";
                }
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Policy berikut tidak punya salah satu dari viewAny/deleteAny/forceDeleteAny/restoreAny -- ".
            "tanpa deleteAny/forceDeleteAny/restoreAny, helper authorize() bawaan Filament JATUH KE ALLOW ".
            "(bukan tolak) untuk Bulk Delete/ForceDelete/Restore, karena proyek ini tidak punya Gate::before:\n"
            .implode("\n", $pelanggar),
        );
    }

    /** @test */
    public function gr_material_bulk_actions_are_no_longer_fail_open(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($user);

        $this->assertFalse($user->hasPermission('delete_gr_materials'));
        $this->assertFalse(GoodsReceiptMaterialResource::canDeleteAny());
        $this->assertFalse(GoodsReceiptMaterialResource::canForceDeleteAny());
        $this->assertFalse(GoodsReceiptMaterialResource::canRestoreAny());
    }

    /** @test */
    public function material_stock_take_bulk_actions_are_no_longer_fail_open(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($user);

        $this->assertFalse($user->hasPermission('delete_material_stock_takes'));
        $this->assertFalse(MaterialStockTakeResource::canDeleteAny());
        $this->assertFalse(MaterialStockTakeResource::canForceDeleteAny());
        $this->assertFalse(MaterialStockTakeResource::canRestoreAny());
    }

    /** @test */
    public function sales_order_bulk_actions_are_no_longer_fail_open(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($user);

        $this->assertFalse($user->hasPermission('delete_sales_orders'));
        $this->assertFalse(SalesOrderResource::canDeleteAny());
        $this->assertFalse(SalesOrderResource::canForceDeleteAny());
        $this->assertFalse(SalesOrderResource::canRestoreAny());
    }

    /**
     * Bukti dampak nyata yang diminta: bukan cuma gerbang `canDeleteAny()`,
     * tapi jalur bulk-select-lalu-hapus SUNGGUHAN lewat
     * `callTableBulkAction()`, persis simulasi klik tombol Delete di
     * daftar. `delivery_date` SENGAJA hari ini (bukan besok) -- lihat
     * catatan panjang di docblock kelas ini.
     */
    /** @test */
    public function bulk_deleting_a_sales_order_no_longer_succeeds_for_an_unprivileged_user(): void
    {
        $segment = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'FIXTURE', 'customer_segment_id' => $segment->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
        $so = SalesOrder::create([
            'customer_id' => $customer->id, 'delivery_date' => now()->format('Y-m-d'),
            'status' => SalesOrder::STATUS_WAITING,
        ]);

        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_sales_orders'], ['module_name' => 'x', 'description' => 'x'])->id
        );

        // `callTableBulkAction()` sendiri menganggap aksinya harus TERLIHAT
        // sebagai prasyarat (`assertTableBulkActionVisible` di dalamnya) --
        // gagal dengan derasnya di situ kalau sudah benar disembunyikan,
        // bukan simulasi "user mencoba lewat request langsung". Pasangan
        // `mountTableBulkAction()`+`callMountedTableBulkAction()` yang
        // dipakai di seluruh proyek ini untuk kasus "harus gagal diam-diam"
        // -- keduanya tidak menganggap apa pun soal visibilitas, cuma
        // menolak mengeksekusi kalau `isDisabled()`.
        Livewire::actingAs($user->fresh())
            ->test(ListSalesOrders::class)
            ->set('selectedTableRecords', [$so->getKey()])
            ->call('mountTableBulkAction', 'delete')
            ->call('callMountedTableBulkAction');

        $this->assertDatabaseHas('sales_orders', ['id' => $so->id, 'deleted_at' => null]);
    }
}
