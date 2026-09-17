<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CattleClassResource\Pages\ListCattleClasses;
use App\Filament\Admin\Resources\DriverResource;
use App\Filament\Admin\Resources\MaterialCategoryResource\Pages\ListMaterialCategories;
use App\Filament\Admin\Resources\MaterialResource\Pages\ListMaterials;
use App\Filament\Admin\Resources\MaterialUnitResource\Pages\ListMaterialUnits;
use App\Filament\Admin\Resources\VehicleResource;
use App\Filament\Clusters\ProductsCluster\Resources\ProductCategoryResource\Pages\ListProductCategories;
use App\Filament\Clusters\ProductsCluster\Resources\ProductResource\Pages\ListProducts;
use App\Models\CattleClass;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Menghapus Kategori/Satuan yang masih dipakai TIDAK BOLEH ikut menghapus
 * Material/Product-nya diam-diam.
 *
 * MaterialCategoryResource, MaterialUnitResource, dan ProductCategoryResource
 * sudah "diperbaiki" dari galat SQL mentah lewat MasterDataDeletion::attempt()
 * (pola yang sama dengan EditWarehouse) -- tapi FK sebenarnya di ketiga tabel
 * ini CASCADE, bukan restrict, jadi perbaikan itu tidak pernah tergigit:
 * delete() "berhasil" tanpa Exception sambil diam-diam menghapus PERMANEN
 * Material/Product-nya (keduanya tidak soft-delete). Sekarang FK-nya restrict
 * (migrasi 2026_09_17_180000, sudah diuji di MySQL lokal), dan guard
 * `deleting()` di model menegakkannya juga di SQLite/test serta jalur bulk.
 */
class MasterDataRestrictDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer', 'username' => 'restrict_del_programmer', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'programmer', 'is_active' => true,
        ]);
        $this->actingAs($this->user);
    }

    // =========================================================================
    // MaterialCategory
    // =========================================================================

    /** @test */
    public function a_material_category_still_used_cannot_be_deleted_and_its_material_survives()
    {
        $category = MaterialCategory::create(['name' => 'KEMASAN']);
        $unit = MaterialUnit::create(['name' => 'PCS']);
        $material = Material::create([
            'code' => 'MTR001', 'name' => 'PLASTIK', 'material_category_id' => $category->id,
            'material_unit_id' => $unit->id, 'is_active' => true,
        ]);

        $this->expectException(\Exception::class);

        $category->delete();

        $this->assertNotNull(Material::find($material->id));
    }

    /** @test */
    public function an_unused_material_category_can_still_be_deleted()
    {
        $category = MaterialCategory::create(['name' => 'TIDAK DIPAKAI']);

        $category->delete();

        $this->assertNull(MaterialCategory::find($category->id));
    }

    /** @test */
    public function bulk_deleting_material_categories_skips_the_used_ones_and_deletes_the_rest()
    {
        $unit = MaterialUnit::create(['name' => 'PCS']);
        $used = MaterialCategory::create(['name' => 'DIPAKAI']);
        $unused = MaterialCategory::create(['name' => 'TIDAK DIPAKAI']);
        $material = Material::create([
            'code' => 'MTR002', 'name' => 'PLASTIK 2', 'material_category_id' => $used->id,
            'material_unit_id' => $unit->id, 'is_active' => true,
        ]);

        Livewire::test(ListMaterialCategories::class)
            ->callTableBulkAction('delete', [$used->id, $unused->id]);

        $this->assertNotNull(MaterialCategory::find($used->id), 'Kategori yang masih dipakai ikut terhapus.');
        $this->assertNull(MaterialCategory::find($unused->id), 'Kategori yang tidak dipakai tidak ikut terhapus.');
        $this->assertNotNull(Material::find($material->id));
    }

    // =========================================================================
    // MaterialUnit
    // =========================================================================

    /** @test */
    public function a_material_unit_still_used_cannot_be_deleted_and_its_material_survives()
    {
        $category = MaterialCategory::create(['name' => 'KEMASAN']);
        $unit = MaterialUnit::create(['name' => 'PCS']);
        $material = Material::create([
            'code' => 'MTR003', 'name' => 'PLASTIK 3', 'material_category_id' => $category->id,
            'material_unit_id' => $unit->id, 'is_active' => true,
        ]);

        $this->expectException(\Exception::class);

        $unit->delete();

        $this->assertNotNull(Material::find($material->id));
    }

    /** @test */
    public function bulk_deleting_material_units_skips_the_used_ones_and_deletes_the_rest()
    {
        $category = MaterialCategory::create(['name' => 'KEMASAN']);
        $used = MaterialUnit::create(['name' => 'PCS']);
        $unused = MaterialUnit::create(['name' => 'ROLL']);
        $material = Material::create([
            'code' => 'MTR004', 'name' => 'PLASTIK 4', 'material_category_id' => $category->id,
            'material_unit_id' => $used->id, 'is_active' => true,
        ]);

        Livewire::test(ListMaterialUnits::class)
            ->callTableBulkAction('delete', [$used->id, $unused->id]);

        $this->assertNotNull(MaterialUnit::find($used->id));
        $this->assertNull(MaterialUnit::find($unused->id));
        $this->assertNotNull(Material::find($material->id));
    }

    // =========================================================================
    // ProductCategory
    // =========================================================================

    /** @test */
    public function a_product_category_still_used_cannot_be_deleted_and_its_product_survives()
    {
        $category = ProductCategory::create(['name' => 'PRIMARY CUTS', 'prefix' => 1]);
        $product = Product::create([
            'name' => 'CUBEROLL', 'code' => '100100', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);

        $this->expectException(\Exception::class);

        $category->delete();

        $this->assertNotNull(Product::find($product->id));
    }

    /** @test */
    public function bulk_deleting_product_categories_skips_the_used_ones_and_deletes_the_rest()
    {
        $used = ProductCategory::create(['name' => 'PRIMARY CUTS', 'prefix' => 1]);
        $unused = ProductCategory::create(['name' => 'SECONDARY CUTS', 'prefix' => 2]);
        $product = Product::create([
            'name' => 'CUBEROLL', 'code' => '100101', 'category_id' => $used->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);

        Livewire::test(ListProductCategories::class)
            ->callTableBulkAction('delete', [$used->id, $unused->id]);

        $this->assertNotNull(ProductCategory::find($used->id));
        $this->assertNull(ProductCategory::find($unused->id));
        $this->assertNotNull(Product::find($product->id));
    }

    // =========================================================================
    // Bulk delete Material/Product/CattleClass tetap dibungkus MasterDataDeletion
    // (regresi belum ditutup sebelumnya -- tidak ada relasi tunggal yang
    // murah diperiksa seperti kategori, jadi cukup dibuktikan yang TIDAK
    // dipakai tetap bisa dihapus lewat bulk tanpa galat).
    // =========================================================================

    /** @test */
    public function bulk_deleting_an_unused_material_still_works()
    {
        $category = MaterialCategory::create(['name' => 'KEMASAN']);
        $unit = MaterialUnit::create(['name' => 'PCS']);
        $material = Material::create([
            'code' => 'MTR005', 'name' => 'PLASTIK 5', 'material_category_id' => $category->id,
            'material_unit_id' => $unit->id, 'is_active' => true,
        ]);

        Livewire::test(ListMaterials::class)
            ->callTableBulkAction('delete', [$material->id]);

        $this->assertNull(Material::find($material->id));
    }

    /** @test */
    public function bulk_deleting_an_unused_product_still_works()
    {
        $category = ProductCategory::create(['name' => 'PRIMARY CUTS', 'prefix' => 1]);
        $product = Product::create([
            'name' => 'CUBEROLL', 'code' => '100102', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('delete', [$product->id]);

        $this->assertNull(Product::find($product->id));
    }

    /** @test */
    public function bulk_deleting_an_unused_cattle_class_still_works()
    {
        $class = CattleClass::create(['name' => 'BALI', 'is_active' => true]);

        Livewire::test(ListCattleClasses::class)
            ->callTableBulkAction('delete', [$class->id]);

        $this->assertNull(CattleClass::find($class->id));
    }

    // =========================================================================
    // Driver & Vehicle: keputusan Owner "tidak ada hapus" berlaku di SEMUA
    // jalur, bukan cuma tombol satu baris di halaman Edit.
    // =========================================================================

    /** @test */
    public function driver_resource_offers_no_bulk_delete_at_all()
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/DriverResource.php'));

        $this->assertStringNotContainsString('DeleteBulkAction', $source);
    }

    /** @test */
    public function vehicle_resource_offers_no_bulk_delete_at_all()
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/VehicleResource.php'));

        $this->assertStringNotContainsString('DeleteBulkAction', $source);
    }
}
