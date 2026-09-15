<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RepackResource;
use App\Models\BeefStock;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Repack;
use App\Models\RepackResult;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Susulan penyisiran Repack, 15 September 2026.
 */
class RepackSusulanTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Warehouse $warehouse;
    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->warehouse = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (array_unique([...$permissionNames, 'view_repacks']) as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Repack', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function repack(): Repack
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        return Repack::create(['repack_date' => now()->format('Y-m-d'), 'created_by' => $user->id]);
    }

    // =========================================================================
    // InputBahanRepack & InputHasilRepack butuh edit_repacks -- HTTP sungguhan
    // =========================================================================

    /** @test */
    public function input_bahan_is_closed_over_http_without_edit_repacks(): void
    {
        $repack = $this->repack();
        $user = $this->employee(); // tanpa edit_repacks

        $this->actingAs($user)
            ->get(RepackResource::getUrl('input-bahan', ['record' => $repack->getKey()]))
            ->assertForbidden();

        $user->permissions()->attach(
            Permission::firstOrCreate(['name' => 'edit_repacks'], ['module_name' => 'Repack', 'description' => 'x'])->id
        );

        $this->actingAs($user->fresh())
            ->get(RepackResource::getUrl('input-bahan', ['record' => $repack->getKey()]))
            ->assertSuccessful();
    }

    /** @test */
    public function input_hasil_is_closed_over_http_without_edit_repacks(): void
    {
        $repack = $this->repack();
        $user = $this->employee(); // tanpa edit_repacks

        $this->actingAs($user)
            ->get(RepackResource::getUrl('input-hasil', ['record' => $repack->getKey()]))
            ->assertForbidden();

        $user->permissions()->attach(
            Permission::firstOrCreate(['name' => 'edit_repacks'], ['module_name' => 'Repack', 'description' => 'x'])->id
        );

        $this->actingAs($user->fresh())
            ->get(RepackResource::getUrl('input-hasil', ['record' => $repack->getKey()]))
            ->assertSuccessful();
    }

    // =========================================================================
    // Route cetak: label & ringkasan Repack butuh view_repacks
    // =========================================================================

    /** @test */
    public function the_repack_print_routes_require_view_repacks(): void
    {
        $repack = $this->repack();
        $result = RepackResult::create([
            'repack_id' => $repack->id, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1,
            'pack_date' => now(), 'barcode' => '2'.now()->format('dmy').'MT001110500255001',
        ]);

        $orangLuar = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        $this->actingAs($orangLuar)
            ->get(route('repack.label', ['id' => $result->id]))
            ->assertForbidden();
        $this->actingAs($orangLuar)
            ->get(route('repack.summary', ['id' => $repack->id]))
            ->assertForbidden();

        $orangLuar->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_repacks'], ['module_name' => 'x', 'description' => 'x'])->id
        );

        $this->actingAs($orangLuar->fresh())
            ->get(route('repack.label', ['id' => $result->id]))
            ->assertSuccessful();
        $this->actingAs($orangLuar->fresh())
            ->get(route('repack.summary', ['id' => $repack->id]))
            ->assertSuccessful();
    }

    // =========================================================================
    // Asumsi di balik catch(UniqueConstraintViolationException) di
    // InputHasilRepack::create() -- kalau kelasnya salah, catch itu tidak
    // pernah menggigit.
    // =========================================================================

    /** @test */
    public function a_duplicate_barcode_on_beef_stocks_throws_the_exact_exception_class_the_action_catches(): void
    {
        $barcode = '2'.now()->format('dmy').'MT001110500255001';

        BeefStock::create([
            'barcode' => $barcode, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1,
            'pack_date' => now(), 'origin' => 'REPACK', 'status' => 'IN_STOCK',
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        BeefStock::create([
            'barcode' => $barcode, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 5.0, 'qty_pcs' => 1,
            'pack_date' => now(), 'origin' => 'REPACK', 'status' => 'IN_STOCK',
        ]);
    }
}
