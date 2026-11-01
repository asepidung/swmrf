<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BeefStockMovementResource;
use App\Filament\Admin\Resources\BeefStockResource;
use App\Filament\Admin\Resources\MeatProcessingMovementResource;
use App\Filament\Admin\Resources\MeatProcessingMovementResource\Pages\ListMeatProcessingMovements;
use App\Filament\Admin\Resources\MeatProcessingStockResource;
use App\Filament\Admin\Resources\MeatProcessingStockResource\Pages\ListMeatProcessingStocks;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Meat Processing (permintaan Owner, 1 November 2026): stok dan pergerakan produk kategori Processing dipisah dari Beef Stock,
 * dengan hak akses sendiri.
 */
class MeatProcessingMenuTest extends TestCase
{
    use RefreshDatabase;

    private Product $daging;

    private Product $olahan;

    private Warehouse $gudang;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gudang = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $this->daging = $this->produk('SIRLOIN', 'B001', 'MEAT');
        $this->olahan = $this->produk('BAKSO SAPI 30 PCS', '600500', ProductCategory::PROCESSING);
    }

    private function produk(string $nama, string $kode, string $kategori): Product
    {
        return Product::create([
            'name' => $nama,
            'code' => $kode,
            'category_id' => ProductCategory::firstOrCreate(['name' => $kategori], ['prefix' => substr($kode, 0, 2), 'is_active' => true])->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    private function stokDanGerak(Product $produk, string $barcode, ?Warehouse $gudang = null): void
    {
        $gudang ??= $this->gudang;

        BeefStock::create([
            'barcode' => $barcode, 'product_id' => $produk->id, 'warehouse_id' => $gudang->id, 'grade_id' => $this->grade->id,
            'weight' => 10, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'status' => 'IN_STOCK',
        ]);

        BeefStockMovement::create([
            'product_id' => $produk->id, 'warehouse_id' => $gudang->id, 'condition' => $this->grade->id,
            'barcode' => $barcode, 'transaction_type' => 'IN_FROM_TALLY', 'weight_in' => 10, 'pcs_in' => 1,
        ]);
    }

    private function pegawai(array $izin): User
    {
        $user = User::create([
            'name' => 'Gudang', 'username' => 'g_' . uniqid(), 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        foreach ($izin as $nama) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $nama], ['module_name' => 'X', 'description' => $nama])->id);
        }

        return $user->fresh();
    }

    public function test_beef_stock_no_longer_lists_processing_products_and_meat_processing_lists_only_them(): void
    {
        $this->stokDanGerak($this->daging, 'BC-DAGING');
        $this->stokDanGerak($this->olahan, 'BC-OLAHAN');

        $this->assertSame([$this->daging->id], BeefStockResource::getEloquentQuery()->pluck('products.id')->all());
        $this->assertSame([$this->olahan->id], MeatProcessingStockResource::getEloquentQuery()->pluck('products.id')->all());
    }

    public function test_the_warehouse_grade_columns_only_come_from_the_products_of_that_menu(): void
    {
        $perum = Warehouse::create(['code' => 'PRM', 'name' => 'PERUM', 'is_active' => true]);

        $this->stokDanGerak($this->daging, 'BC-DAGING');
        $this->stokDanGerak($this->olahan, 'BC-OLAHAN', $perum);

        $gudangBeef = array_column(BeefStockResource::stockBuckets(), 'warehouse');
        BeefStockResource::forgetCachedPosition();
        $gudangOlahan = array_column(MeatProcessingStockResource::stockBuckets(), 'warehouse');

        $this->assertSame(['JONGGOL'], $gudangBeef);
        $this->assertSame(['PERUM'], $gudangOlahan);
    }

    public function test_movements_are_split_the_same_way(): void
    {
        $this->stokDanGerak($this->daging, 'BC-DAGING');
        $this->stokDanGerak($this->olahan, 'BC-OLAHAN');

        $this->assertSame(['BC-DAGING'], BeefStockMovementResource::getEloquentQuery()->pluck('barcode')->all());
        $this->assertSame(['BC-OLAHAN'], MeatProcessingMovementResource::getEloquentQuery()->pluck('barcode')->all());
    }

    public function test_beef_stock_permissions_do_not_open_meat_processing(): void
    {
        $this->actingAs($this->pegawai(['view_beef_stocks', 'view_beef_stock_movements']));

        $this->assertTrue(BeefStockResource::canViewAny());
        $this->assertFalse(MeatProcessingStockResource::canViewAny());
        $this->assertFalse(MeatProcessingMovementResource::canViewAny());
        $this->assertFalse(\App\Filament\Clusters\MeatProcessing::canViewAny());
    }

    public function test_the_meat_processing_permissions_open_only_meat_processing(): void
    {
        $this->actingAs($this->pegawai(['view_meat_processing_stocks']));

        $this->assertTrue(MeatProcessingStockResource::canViewAny());
        $this->assertTrue(\App\Filament\Clusters\MeatProcessing::canViewAny());
        $this->assertFalse(MeatProcessingMovementResource::canViewAny());
        $this->assertFalse(BeefStockResource::canViewAny());
    }

    public function test_the_list_pages_render_for_the_right_permission(): void
    {
        $this->stokDanGerak($this->olahan, 'BC-OLAHAN');

        $user = $this->pegawai(['view_meat_processing_stocks', 'view_meat_processing_movements']);

        Livewire::actingAs($user)->test(ListMeatProcessingStocks::class)->assertSee('BAKSO SAPI 30 PCS');
        Livewire::actingAs($user)->test(ListMeatProcessingMovements::class)->assertSee('BC-OLAHAN');
    }

    public function test_a_processing_product_cannot_be_opened_from_beef_stock(): void
    {
        $this->assertNull(BeefStockResource::getEloquentQuery()->find($this->olahan->id));
        $this->assertNotNull(MeatProcessingStockResource::getEloquentQuery()->find($this->olahan->id));
    }

    public function test_the_permissions_exist_after_migrating_and_in_the_seeder(): void
    {
        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        foreach (['view_meat_processing_stocks', 'view_meat_processing_movements'] as $izin) {
            $this->assertStringContainsString($izin, $seeder);
            $this->assertTrue(Permission::where('name', $izin)->exists());
        }
    }
}
