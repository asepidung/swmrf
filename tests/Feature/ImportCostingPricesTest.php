<?php

namespace Tests\Feature;

use App\Models\CostingPriceDefault;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * `costing:import-prices` (issue #541): isi awal harga patokan costing dari sheet costing terbaru.
 */
class ImportCostingPricesTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = 'storage/framework/testing-costing-prices.xlsx';
        @mkdir(base_path('storage/framework'), 0777, true);

        $writer = new Writer();
        $writer->openToFile(base_path($this->file));
        $writer->getCurrentSheet()->setName('LJP 15 - 8 OKT');

        // Judul, lalu kepala kolom; kolom A..I = indeks 0..8 (E = produk, H = net, I = gross).
        $writer->addRow(Row::fromValues(['judul']));
        $writer->addRow(Row::fromValues(['', '', '', '', 'PRODUCTS', '', '', 'NET PRICE', 'GROSS PRICE']));
        foreach ([
            ['Topside', 138281.25, 147500],
            ['  tenderloin  ', 203437.5, 217000],
            ['Offal', 8000, 0],
            ['Tidak Dikenal', 1000, 1000],
            ['Tanpa Net', '', 50000],
        ] as [$name, $net, $gross]) {
            $writer->addRow(new Row([
                Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''),
                Cell::fromValue($name), Cell::fromValue(''), Cell::fromValue(''),
                Cell::fromValue($net), Cell::fromValue($gross),
            ]));
        }
        $writer->close();

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        foreach (['TOPSIDE' => 'MT001', 'TENDERLOIN' => 'MT002', 'OFFAL' => 'MT003', 'TANPA NET' => 'MT004'] as $name => $code) {
            Product::create(['name' => $name, 'code' => $code, 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        }
    }

    protected function tearDown(): void
    {
        @unlink(base_path($this->file));

        parent::tearDown();
    }

    /** @test */
    public function a_dry_run_saves_nothing(): void
    {
        $this->artisan('costing:import-prices', ['--source' => $this->file])->assertSuccessful();

        $this->assertSame(0, CostingPriceDefault::count());
    }

    /** @test */
    public function apply_stores_gross_and_net_per_product_matched_by_normalised_name(): void
    {
        $this->artisan('costing:import-prices', ['--source' => $this->file, '--apply' => true])
            ->expectsOutputToContain('Tidak ada di master produk')
            ->assertSuccessful();

        $topside = CostingPriceDefault::where('product_id', Product::where('name', 'TOPSIDE')->value('id'))->firstOrFail();
        $this->assertSame(147500.0, $topside->gross_price);
        $this->assertSame(138282.0, $topside->net_price, 'Net dibulatkan ke atas: 138.281,25 -> 138.282.');
        $this->assertSame('import', $topside->source);

        $tenderloin = CostingPriceDefault::where('product_id', Product::where('name', 'TENDERLOIN')->value('id'))->firstOrFail();
        $this->assertSame(203438.0, $tenderloin->net_price, '203.437,5 -> 203.438.');

        // Offal: hanya net diketik (gross 0).
        $offal = CostingPriceDefault::where('product_id', Product::where('name', 'OFFAL')->value('id'))->firstOrFail();
        $this->assertSame(0.0, $offal->gross_price);
        $this->assertSame(8000.0, $offal->net_price);

        // Tanpa net tetapi ada gross: net = gross.
        $noNet = CostingPriceDefault::where('product_id', Product::where('name', 'TANPA NET')->value('id'))->firstOrFail();
        $this->assertSame(50000.0, $noNet->net_price);

        $this->assertSame(4, CostingPriceDefault::count(), 'Produk yang tidak ada di master tidak dibuat.');
    }

    /** @test */
    public function a_missing_sheet_or_file_fails_cleanly(): void
    {
        $this->artisan('costing:import-prices', ['--source' => $this->file, '--sheet' => 'TIDAK ADA'])->assertFailed();
        $this->artisan('costing:import-prices', ['--source' => 'storage/framework/tidak-ada.xlsx'])->assertFailed();
    }
}
