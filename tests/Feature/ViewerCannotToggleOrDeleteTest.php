<?php

namespace Tests\Feature;

use App\Filament\Clusters\ProductsCluster\Resources\ProductResource\Pages\ListProducts;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * PRD-01 (sakelar aktif produk) dan LV-01 (hapus berkas log): izin LIHAT tidak boleh menjadi izin UBAH/HAPUS.
 */
class ViewerCannotToggleOrDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(array $izin, string $role = 'employee'): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

        foreach ($izin as $nama) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $nama], ['module_name' => 'x', 'description' => 'x'])->id);
        }

        return $user->fresh();
    }

    /** @test */
    public function a_user_who_can_only_view_products_cannot_flip_the_active_switch(): void
    {
        $kategori = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $produk = Product::create(['name' => 'SIRLOIN', 'code' => 'MT00100', 'category_id' => $kategori->id, 'structure_type' => 'main', 'is_active' => true]);

        Livewire::actingAs($this->pengguna(['view_products']))
            ->test(ListProducts::class)
            ->call('updateTableColumnState', 'is_active', (string) $produk->id, false);

        $this->assertTrue((bool) $produk->fresh()->is_active);
    }

    /** @test */
    public function only_a_programmer_may_delete_log_files(): void
    {
        $pelihat = $this->pengguna(['view_activity_logs']);
        $programmer = $this->pengguna([], 'programmer');

        $this->assertTrue(Gate::forUser($pelihat)->allows('viewLogViewer'));
        $this->assertFalse(Gate::forUser($pelihat)->allows('deleteLogFile', 'laravel.log'));
        $this->assertFalse(Gate::forUser($pelihat)->allows('deleteLogFolder', 'folder'));
        $this->assertTrue(Gate::forUser($programmer)->allows('deleteLogFile', 'laravel.log'));
    }
}
