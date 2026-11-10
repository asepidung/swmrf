<?php

namespace Tests\Feature;

use App\Filament\Admin\Widgets\PendingTaskWidget;
use App\Models\BeefStock;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kartu "stok lebih dari 60 hari" di dashboard hanya untuk pemegang `view_beef_stock_aging`
 * (Owner, 10 November 2026): halaman tujuannya terlarang bagi yang lain, jadi kartunya tidak boleh tampil.
 */
class Aging60DaysCardPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(array $izin = [], string $peran = 'employee'): User
    {
        $user = User::factory()->create(['role' => $peran, 'is_active' => true]);

        foreach ($izin as $nama) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $nama], ['module_name' => 'Beef Stock', 'description' => $nama])->id);
        }

        return $user->fresh();
    }

    private function stokLama(): void
    {
        $kategori = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $produk = Product::create(['name' => 'STRIPLOIN', 'code' => 'MT00100', 'category_id' => $kategori->id, 'structure_type' => 'main', 'is_active' => true]);

        BeefStock::create([
            'barcode' => 'OLD-1', 'product_id' => $produk->id,
            'warehouse_id' => Warehouse::create(['code' => 'PERUM', 'name' => 'PERUM', 'is_active' => true])->id,
            'grade_id' => Grade::create(['name' => 'CHILL', 'is_active' => true])->id,
            'weight' => 10, 'qty_pcs' => 1, 'pack_date' => now()->subDays(90)->toDateString(), 'origin' => '1', 'status' => 'IN_STOCK',
        ]);
    }

    public function test_only_a_holder_of_the_permission_gets_the_card(): void
    {
        $this->stokLama();

        $this->actingAs($this->pengguna());
        $this->assertSame(0, (new PendingTaskWidget)->getAging60DaysCount(), 'Tanpa izin tidak diberi kartu.');

        $this->actingAs($this->pengguna(['view_beef_stock_aging']));
        $this->assertSame(1, (new PendingTaskWidget)->getAging60DaysCount());
    }

    public function test_a_programmer_still_sees_it(): void
    {
        $this->stokLama();

        $this->actingAs($this->pengguna([], 'programmer'));
        $this->assertSame(1, (new PendingTaskWidget)->getAging60DaysCount());
    }
}
