<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `access:export` dan `access:import` (13 Oktober 2026): memindahkan users, permissions,
 * pivot user-permission, dan push subscriptions ke basis data baru yang bersih -- gladi
 * resik DB produksi VPS dan pembuatan DB baru untuk coba./app.
 */
class AccessDataTransferTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'access').'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    /** Dua pengguna, satu izin buatan sendiri, pivot, dan satu langganan push. */
    private function seedAccess(): void
    {
        DB::table('users')->insert([
            ['id' => 5, 'name' => 'Budi', 'username' => 'budi', 'password' => '$2y$12$hashbudi', 'gender' => 'L', 'role' => 'employee', 'is_active' => true, 'remember_token' => 'tokbudi', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'Sari', 'username' => 'sari', 'password' => '$2y$12$hashsari', 'gender' => 'P', 'role' => 'employee', 'is_active' => true, 'remember_token' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $custom = Permission::create(['name' => 'izin_buatan_sendiri', 'module_name' => 'Uji', 'description' => 'izin uji']);
        $view = Permission::firstOrCreate(['name' => 'view_costings'], ['module_name' => 'Costings', 'description' => 'View costings']);

        DB::table('user_permissions')->insert([
            ['user_id' => 5, 'permission_id' => $custom->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 5, 'permission_id' => $view->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 9, 'permission_id' => $view->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('push_subscriptions')->insert([
            'id' => 3, 'subscribable_type' => User::class, 'subscribable_id' => 5, 'endpoint' => 'https://push.example/abc',
            'public_key' => 'pk', 'auth_token' => 'at', 'content_encoding' => 'aesgcm', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Meniru basis data baru: yang diekspor hilang, izin buatan migrasi tetap ada. */
    private function wipe(): void
    {
        DB::table('push_subscriptions')->delete();
        DB::table('user_permissions')->delete();
        DB::table('users')->delete();
        DB::table('permissions')->where('name', 'izin_buatan_sendiri')->delete();
    }

    private function export(): void
    {
        $this->artisan('access:export', ['--file' => $this->file])->assertExitCode(0);
    }

    private function import(array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('access:import', ['--file' => $this->file] + $options);
    }

    /** @test */
    public function export_writes_the_four_kinds_of_data_with_permission_names_in_the_pivot(): void
    {
        $this->seedAccess();

        $this->export();

        $data = json_decode(file_get_contents($this->file), true);
        $this->assertCount(2, $data['users']);
        $this->assertCount(3, $data['user_permissions']);
        $this->assertCount(1, $data['push_subscriptions']);
        $this->assertContains('izin_buatan_sendiri', array_column($data['user_permissions'], 'permission'));
        $this->assertContains('view_costings', array_column($data['permissions'], 'name'));
    }

    /** @test */
    public function the_dry_run_writes_nothing(): void
    {
        $this->seedAccess();
        $this->export();
        $this->wipe();

        $this->import()->expectsOutputToContain('Dry-run')->assertExitCode(0);

        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('user_permissions')->count());
        $this->assertSame(0, DB::table('push_subscriptions')->count());
        $this->assertSame(0, Permission::where('name', 'izin_buatan_sendiri')->count());
    }

    /** @test */
    public function apply_restores_users_with_their_ids_and_password_hashes(): void
    {
        $this->seedAccess();
        $this->export();
        $this->wipe();

        $this->import(['--apply' => true])->assertExitCode(0);

        $this->assertSame('budi', User::find(5)->username);
        $this->assertSame('sari', User::find(9)->username);
        $this->assertSame('$2y$12$hashbudi', DB::table('users')->where('id', 5)->value('password'), 'Hash dibawa apa adanya.');
        $this->assertSame('tokbudi', DB::table('users')->where('id', 5)->value('remember_token'));
    }

    /** @test */
    public function permissions_are_matched_by_name_and_the_pivot_follows_the_names_not_the_old_ids(): void
    {
        $this->seedAccess();
        $this->export();
        $this->wipe();

        // Di DB baru izin yang dibuat migrasi bisa berbeda id-nya; yang dipakai pivot adalah namanya.
        $this->import(['--apply' => true])->assertExitCode(0);

        $custom = Permission::where('name', 'izin_buatan_sendiri')->first();
        $view = Permission::where('name', 'view_costings')->first();

        $this->assertNotNull($custom, 'Izin yang hanya ada di DB lama dibuat.');
        $this->assertTrue(User::find(5)->permissions()->where('permissions.id', $custom->id)->exists());
        $this->assertTrue(User::find(5)->permissions()->where('permissions.id', $view->id)->exists());
        $this->assertTrue(User::find(9)->permissions()->where('permissions.id', $view->id)->exists());
        $this->assertSame(3, DB::table('user_permissions')->count());
        $this->assertSame(1, Permission::where('name', 'view_costings')->count(), 'Izin yang sudah ada tidak digandakan.');
    }

    /** @test */
    public function push_subscriptions_keep_their_ids_and_point_to_the_same_users(): void
    {
        $this->seedAccess();
        $this->export();
        $this->wipe();

        $this->import(['--apply' => true])->assertExitCode(0);

        $subscription = DB::table('push_subscriptions')->where('id', 3)->first();
        $this->assertSame('https://push.example/abc', $subscription->endpoint);
        $this->assertSame(5, (int) $subscription->subscribable_id);
        $this->assertSame('pk', $subscription->public_key);
    }

    /** @test */
    public function running_apply_twice_does_not_duplicate_anything(): void
    {
        $this->seedAccess();
        $this->export();
        $this->wipe();

        $this->import(['--apply' => true])->assertExitCode(0);
        $this->import(['--apply' => true])->expectsOutputToContain('sama (dilewati)')->assertExitCode(0);

        $this->assertSame(2, User::count());
        $this->assertSame(3, DB::table('user_permissions')->count());
        $this->assertSame(1, DB::table('push_subscriptions')->count());
    }

    /** @test */
    public function a_user_id_already_used_by_someone_else_is_refused_and_nothing_is_written(): void
    {
        $this->seedAccess();
        $this->export();
        $this->wipe();
        DB::table('users')->insert(['id' => 5, 'name' => 'Orang Lain', 'username' => 'orang_lain', 'password' => 'x', 'gender' => 'L', 'role' => 'employee', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->import(['--apply' => true])
            ->expectsOutputToContain('DIBATALKAN')
            ->expectsOutputToContain('users id 5: sudah berisi "orang_lain"')
            ->assertExitCode(1);

        $this->assertSame('orang_lain', User::find(5)->username, 'Tidak ditimpa.');
        $this->assertNull(User::find(9), 'Tidak ada yang ditulis.');
        $this->assertSame(0, DB::table('push_subscriptions')->count());
    }

    /** @test */
    public function a_missing_or_invalid_file_fails_cleanly(): void
    {
        $this->artisan('access:import', ['--file' => '/tidak/ada.json'])->expectsOutputToContain('Berkas tidak ditemukan')->assertExitCode(1);

        file_put_contents($this->file, '{"bukan":"hasil export"}');
        $this->import()->expectsOutputToContain('bukan hasil access:export yang sah')->assertExitCode(1);
    }

    /** @test */
    public function the_default_export_file_stays_outside_git(): void
    {
        $this->assertStringContainsString('*', (string) @file_get_contents(base_path('storage/app/.gitignore')), 'storage/app tidak masuk repositori.');
    }
}
