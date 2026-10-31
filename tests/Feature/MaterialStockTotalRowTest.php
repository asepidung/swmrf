<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialStockResource\Pages\ListMaterialStocks;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStock;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Daftar Stok Material punya baris TOTAL di bawahnya (permintaan Ayah, 31 Oktober 2026): angka di layar harus bisa
 * dijumlah dan dicocokkan dengan legacy tanpa kalkulator.
 */
class MaterialStockTotalRowTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::create([
            'name' => 'Gudang', 'username' => 'gudang_'.uniqid(), 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_material_stocks'], ['module_name' => 'Material Stocks', 'description' => 'view'])->id
        );

        return $user->fresh();
    }

    private function material(string $code, string $name, float $qty): Material
    {
        $material = Material::create([
            'code' => $code, 'name' => $name,
            'material_category_id' => MaterialCategory::firstOrCreate(['name' => 'KARTON'])->id,
            'material_unit_id' => MaterialUnit::firstOrCreate(['name' => 'BOX'])->id,
            'min_stock' => 0, 'show_in_stock' => true,
        ]);
        MaterialStock::create(['material_id' => $material->id, 'qty' => $qty]);

        return $material;
    }

    public function test_the_list_shows_the_sum_of_all_listed_stock(): void
    {
        $this->material('MTR-01', 'KARTON A', 1200);
        $this->material('MTR-02', 'KARTON B', 345);
        $this->actingAs($this->user());

        Livewire::test(ListMaterialStocks::class)
            ->assertSee('Total')
            ->assertSee('1.545');
    }

    public function test_the_total_follows_the_search(): void
    {
        $this->material('MTR-01', 'KARTON A', 1200);
        $this->material('MTR-02', 'KARTON B', 345);
        $this->actingAs($this->user());

        Livewire::test(ListMaterialStocks::class)
            ->searchTable('KARTON B')
            ->assertSee('345')
            ->assertDontSee('1.545');
    }
}
