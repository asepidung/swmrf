<?php

namespace Tests\Feature;

use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kode gudang JONGGOL -> JGL dan PERUM -> PRM (keputusan Owner, 19 Oktober 2026); nama tidak berubah.
 */
class WarehouseCodeRenameTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_19_200000_rename_warehouse_codes_to_jgl_and_prm.php');
    }

    /** @test */
    public function the_migration_renames_the_codes_and_keeps_names_and_ids(): void
    {
        Warehouse::query()->delete();
        $jonggol = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $perum = Warehouse::create(['code' => 'PERUM', 'name' => 'PERUM', 'is_active' => true]);

        $this->migration()->up();

        $this->assertSame('JGL', $jonggol->fresh()->code);
        $this->assertSame('PRM', $perum->fresh()->code);
        $this->assertSame('JONGGOL', $jonggol->fresh()->name);
        $this->assertSame('PERUM', $perum->fresh()->name);
    }

    /** @test */
    public function running_it_twice_is_harmless_and_it_never_takes_a_code_another_warehouse_already_has(): void
    {
        Warehouse::query()->delete();
        Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        Warehouse::create(['code' => 'JGL', 'name' => 'GUDANG LAIN', 'is_active' => true]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('JONGGOL', Warehouse::where('name', 'JONGGOL')->value('code'), 'Kode JGL sudah dipakai gudang lain: tidak diganti.');
        $this->assertSame('JGL', Warehouse::where('name', 'GUDANG LAIN')->value('code'));
    }

    /** @test */
    public function the_migration_can_be_reversed(): void
    {
        Warehouse::query()->delete();
        Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);

        $this->migration()->down();

        $this->assertSame('JONGGOL', Warehouse::where('name', 'JONGGOL')->value('code'));
    }

    /** @test */
    public function the_seeder_uses_the_new_codes_and_never_duplicates_a_warehouse_whatever_its_code(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['JGL', 'PRM'], Warehouse::orderBy('code')->pluck('code')->all());

        // Basis data yang masih memakai kode lama: seeder memperbaruinya, tidak menambah gudang kembar.
        Warehouse::where('name', 'JONGGOL')->update(['code' => 'JONGGOL']);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, Warehouse::count());
        $this->assertSame('JGL', Warehouse::where('name', 'JONGGOL')->value('code'));
    }
}
