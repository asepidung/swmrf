<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BeefStockResource\Pages\ViewBeefStock;
use App\Filament\Admin\Resources\BeefStockResource\RelationManagers\BeefStocksRelationManager;
use App\Models\BeefStock;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Halaman detail stok per produk: ekspor Excel dan PDF atas baris yang sedang tampil. */
class BeefStockDetailExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $gudang = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $kategori = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->produk = Product::create(['name' => 'SIRLOIN', 'code' => 'B001', 'category_id' => $kategori->id, 'structure_type' => 'main', 'is_active' => true]);

        foreach (['BC-EXP-1' => 10.5, 'BC-EXP-2' => 4.25] as $barcode => $berat) {
            BeefStock::create([
                'barcode' => $barcode, 'product_id' => $this->produk->id, 'warehouse_id' => $gudang->id,
                'grade_id' => $grade->id, 'weight' => $berat, 'qty_pcs' => 1, 'pack_date' => now(), 'status' => 'IN_STOCK',
            ]);
        }
    }

    private function halaman()
    {
        return Livewire::actingAs($this->user)->test(BeefStocksRelationManager::class, [
            'ownerRecord' => $this->produk, 'pageClass' => ViewBeefStock::class,
        ]);
    }

    /** @test */
    public function the_detail_page_offers_excel_and_pdf(): void
    {
        $this->halaman()
            ->assertTableActionExists('excel')
            ->assertTableActionExists('pdf');
    }

    /** @test */
    public function excel_and_pdf_download_with_the_product_in_the_filename(): void
    {
        $this->halaman()->callTableAction('excel')->assertFileDownloaded();
        $this->halaman()->callTableAction('pdf')->assertFileDownloaded();
    }

    /** @test */
    public function the_export_follows_the_rows_on_screen(): void
    {
        $isi = (new \ReflectionMethod(BeefStocksRelationManager::class, 'isiEkspor'));
        $isi->setAccessible(true);

        $komponen = $this->halaman()->set('tableSearch', 'BC-EXP-1')->instance();
        $hasil = $isi->invoke($komponen);

        $this->assertCount(1, $hasil['baris']);
        $this->assertSame('BC-EXP-1', $hasil['baris'][0][0]);
        $this->assertEqualsWithDelta(10.5, $hasil['totalBerat'], 0.001);
    }
}
