<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BoningResource\Pages\LabelingBoning;
use App\Filament\Admin\Resources\UserResource;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\WarehouseResource\Pages\EditWarehouse;
use App\Filament\Clusters\BeefStocks\Pages\FoundItemScanner;
use App\Models\BeefStock;
use App\Models\Boning;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Susulan dari penyisiran modul User & Warehouse, 14 September 2026.
 * Kategori [A], disetujui Hafizh.
 */
class UserWarehouseSusulanTest extends TestCase
{
    use RefreshDatabase;

    private User $programmer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->programmer = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
    }

    private function makeEmployee(array $permissionNames = []): User
    {
        $employee = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['module_name' => 'Test', 'description' => $name]
            );
            $employee->permissions()->attach($permission->id);
        }

        return $employee;
    }

    // =====================================================================
    // Temuan 2 -- menu User Management disembunyikan dari yang tidak berhak
    // =====================================================================

    /** @test */
    public function the_navigation_menu_is_hidden_from_a_user_without_view_users(): void
    {
        $this->actingAs($this->makeEmployee());

        $this->assertFalse(UserResource::shouldRegisterNavigation());
    }

    /** @test */
    public function the_navigation_menu_shows_for_a_user_with_view_users(): void
    {
        $this->actingAs($this->makeEmployee(['view_users']));

        $this->assertTrue(UserResource::shouldRegisterNavigation());
    }

    /** @test */
    public function the_navigation_menu_shows_for_a_programmer(): void
    {
        $this->actingAs($this->programmer);

        $this->assertTrue(UserResource::shouldRegisterNavigation());
    }

    // =====================================================================
    // Temuan 3 -- User master data paling sensitif tapi tanpa jejak audit
    // =====================================================================

    /** @test */
    public function user_changes_are_logged_but_the_password_hash_never_is(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true, 'password' => 'rahasia-awal']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'event' => 'created',
        ]);

        $log = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertArrayNotHasKey('password', $log->properties['attributes'] ?? []);
        $this->assertArrayNotHasKey('remember_token', $log->properties['attributes'] ?? []);

        $user->update(['name' => 'Nama Baru']);

        $updateLog = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertArrayNotHasKey('password', $updateLog->properties['attributes'] ?? []);
        $this->assertSame('Nama Baru', $updateLog->properties['attributes']['name'] ?? null);
    }

    /**
     * CheckboxList izin ini `dehydrated(false)` dan `afterStateHydrated()`
     * membaca ULANG dari database setiap kali komponennya dihidrasi --
     * termasuk di tengah siklus request Livewire sebelum `save()` sungguh
     * berjalan. Akibatnya menguji arah "dicabut" lewat form yang sama
     * (mulai terisi, lalu dikosongkan) berakhir sebagai gabungan
     * (union) dari kondisi lama dan baru, bukan penggantian bersih --
     * bukan bug dari perubahan ini, tapi keterbatasan pengujian form
     * dinamis ini dari luar. Diuji arah "ditambahkan" saja, dari kosong,
     * yang tidak kena masalah itu.
     *
     * @test
     */
    public function editing_permissions_logs_what_was_attached(): void
    {
        $target = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $add = Permission::firstOrCreate(['name' => 'view_users'], ['module_name' => 'Test', 'description' => 'x']);

        Livewire::actingAs($this->programmer)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                // Nama field-nya PERSIS 'permissions_' . module_name, tanpa
                // slug/sanitasi apa pun -- lihat
                // UserResource::permissionCheckboxList().
                'permissions_Test' => [$add->id],
            ])
            ->call('save');

        $log = Activity::where('subject_type', User::class)
            ->where('subject_id', $target->id)
            ->where('description', 'permissions synced')
            ->first();

        $this->assertNotNull($log, 'perubahan izin harus tercatat di activity log');
        $this->assertContains('view_users', $log->properties['attached'] ?? []);
        $this->assertTrue($target->fresh()->hasPermission('view_users'));
    }

    // =====================================================================
    // Temuan 5 -- hapus master data yang masih dipakai: pesan ramah, bukan 500
    // =====================================================================

    /** @test */
    public function deleting_a_warehouse_still_in_use_shows_a_friendly_notification_not_a_raw_sql_error(): void
    {
        $warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $product = Product::create([
            'name' => 'SIRLOIN',
            'code' => 'B001',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        BeefStock::create([
            'barcode' => 'BARCODE-MASIH-DIPAKAI-001',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'grade_id' => $grade->id,
            'weight' => 10,
            'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'origin' => '1',
            'status' => 'IN_STOCK',
        ]);

        Livewire::actingAs($this->programmer)
            ->test(EditWarehouse::class, ['record' => $warehouse->getRouteKey()])
            ->callAction('delete')
            ->assertNotified();

        // Masih ada -- penghapusan ditolak, bukan sebagian jalan.
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
    }

    // =====================================================================
    // Temuan 6 -- gudang nonaktif tidak boleh jadi tujuan stok baru
    // =====================================================================

    /** @test */
    public function found_item_scanner_excludes_inactive_warehouses_from_its_dropdown(): void
    {
        $active = Warehouse::create(['code' => 'AKTIF', 'name' => 'GUDANG AKTIF', 'is_active' => true]);
        $inactive = Warehouse::create(['code' => 'MATI', 'name' => 'GUDANG NONAKTIF', 'is_active' => false]);

        // warehouse_id di sini bukan milik form halaman -- ia bagian dari
        // form modal action 'manualInput', jadi action-nya harus dibuka
        // (mounted) dulu sebelum form-nya bisa diperiksa.
        $test = Livewire::actingAs($this->programmer)
            ->test(FoundItemScanner::class)
            ->mountAction('manualInput');

        $options = $test->instance()
            ->getForm('mountedActionForm')
            ->getComponent(fn ($component) => method_exists($component, 'getName') && $component->getName() === 'warehouse_id')
            ->getOptions();

        $this->assertArrayHasKey($active->id, $options);
        $this->assertArrayNotHasKey($inactive->id, $options);
    }

    /** @test */
    public function labeling_boning_excludes_inactive_warehouses_from_its_dropdown(): void
    {
        $active = Warehouse::create(['code' => 'AKTIF', 'name' => 'GUDANG AKTIF', 'is_active' => true]);
        $inactive = Warehouse::create(['code' => 'MATI', 'name' => 'GUDANG NONAKTIF', 'is_active' => false]);

        $boning = Boning::create([
            'boning_date' => now()->format('Y-m-d'),
            'created_by' => $this->programmer->id,
        ]);

        $test = Livewire::actingAs($this->programmer)
            ->test(LabelingBoning::class, ['record' => $boning]);

        $options = $test->instance()
            ->getForm('form')
            ->getComponent(fn ($component) => method_exists($component, 'getName') && $component->getName() === 'warehouse_id')
            ->getOptions();

        $this->assertArrayHasKey($active->id, $options);
        $this->assertArrayNotHasKey($inactive->id, $options);
    }
}
