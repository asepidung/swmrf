<?php

namespace Tests\Feature;

use App\Console\Commands\ResetTrainingData;
use App\Models\BankAccount;
use App\Models\CattleClass;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `app:reset-training-data` (#535): kosongkan data pelatihan, pertahankan akses dan data dasar.
 */
class ResetTrainingDataTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function every_table_is_classified_as_keep_or_wipe_so_a_new_migration_forces_a_decision(): void
    {
        $unclassified = collect(Schema::getTables())->pluck('name')
            ->reject(fn ($t) => isset(ResetTrainingData::KEEP[$t]) || in_array($t, ResetTrainingData::WIPE, true))
            ->values()->all();

        $this->assertSame([], $unclassified, 'Tabel baru harus didaftarkan di KEEP atau WIPE pada ResetTrainingData.');

        $this->assertSame([], array_values(array_intersect(array_keys(ResetTrainingData::KEEP), ResetTrainingData::WIPE)), 'Tidak boleh di kedua daftar.');
    }

    private function trainingData(): void
    {
        $category = ProductCategory::create(['name' => 'PRIME CUT', 'prefix' => 1, 'is_active' => true]);
        Product::create(['name' => 'TOPSIDE', 'code' => 'MT001', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        CattleClass::create(['name' => 'STEER']);
        Grade::create(['name' => 'CHILL', 'is_active' => true]);
        Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        BankAccount::create(['account_holder' => 'KAS', 'account_number' => '1', 'bank_name' => 'KAS', 'initial' => 5000000, 'is_active' => true]);
        User::factory()->create(['username' => 'saepullrock']);
        DB::table('bank_transactions')->count();
    }

    /** @test */
    public function a_dry_run_reports_per_table_and_deletes_nothing(): void
    {
        $this->trainingData();

        $this->artisan('app:reset-training-data')
            ->expectsOutputToContain('Dry-run')
            ->expectsOutputToContain('DIHAPUS')
            ->expectsOutputToContain('DIPERTAHANKAN')
            ->assertSuccessful();

        $this->assertSame(1, Product::count());
        $this->assertSame(1, CattleClass::count());
    }

    /** @test */
    public function apply_needs_the_database_name_typed_and_a_wrong_answer_cancels(): void
    {
        $this->trainingData();
        $database = DB::connection()->getDatabaseName();

        $this->artisan('app:reset-training-data', ['--apply' => true])
            ->expectsQuestion("Ketik nama database ({$database}) untuk melanjutkan; kosongkan untuk batal", 'salah')
            ->expectsOutputToContain('DIBATALKAN')
            ->assertFailed();

        $this->assertSame(1, Product::count(), 'Tidak ada yang dihapus.');
    }

    /** @test */
    public function apply_wipes_training_data_keeps_access_and_base_data_and_restarts_the_ids(): void
    {
        $this->trainingData();
        $database = DB::connection()->getDatabaseName();
        $permissions = Permission::count();
        $users = User::count();

        $this->artisan('app:reset-training-data', ['--apply' => true])
            ->expectsQuestion("Ketik nama database ({$database}) untuk melanjutkan; kosongkan untuk batal", $database)
            ->assertSuccessful();

        // Dihapus.
        $this->assertSame(0, Product::count());
        $this->assertSame(0, ProductCategory::count());
        $this->assertSame(0, CattleClass::count());

        // Dipertahankan.
        $this->assertSame($users, User::count());
        $this->assertSame($permissions, Permission::count());
        $this->assertSame(1, Grade::count());
        $this->assertSame(1, Warehouse::count());
        $this->assertSame(1, BankAccount::count());
        $this->assertSame(0.0, (float) BankAccount::first()->initial, 'Akun kas ada, saldo awalnya 0.');

        // id mulai dari 1 lagi.
        $first = CattleClass::create(['name' => 'BULL']);
        $this->assertSame(1, $first->id);
    }
}
