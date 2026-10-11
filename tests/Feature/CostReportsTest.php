<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\SalesCostReport;
use App\Filament\Admin\Pages\StockValueReport;
use App\Models\BarcodeCost;
use App\Models\BeefStock;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderReceipt;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CostReports;
use App\Support\BarcodeCosts;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HPP tahap 2, PR 4 (issue #522): laporan Nilai Stok dan HPP Penjualan.
 *
 * Yang dijaga: barcode belum bernilai dihitung TERPISAH (tidak pernah nol
 * diam-diam); HPP penjualan dibaca saat laporan dibuat (pengisian mundur
 * langsung tercermin); baris yang belum ditagih atau belum lengkap nilainya
 * tidak punya margin; filter dan izin.
 */
class CostReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $jonggol;

    private Warehouse $bogor;

    private Grade $grade;

    private Product $tl;

    private Product $cr;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($this->user);

        $this->jonggol = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->bogor = Warehouse::create(['code' => 'BGR', 'name' => 'BOGOR', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        $this->tl = Product::create(['name' => 'TENDERLOIN', 'code' => 'MT001', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        $this->cr = Product::create(['name' => 'CUBE ROLL', 'code' => 'MT002', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        $this->customer = Customer::create(['name' => 'PELANGGAN UJI', 'address' => 'Bogor', 'top_days' => 30, 'customer_segment_id' => CustomerSegment::firstOrCreate(['name' => 'RETAIL'])->id]);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach ($permissions as $permission) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $permission], ['module_name' => 'Test', 'description' => $permission])->id);
        }

        return $user->fresh();
    }

    private function stock(string $barcode, Product $product, Warehouse $warehouse, float $kg, ?float $cost, string $status = 'IN_STOCK'): void
    {
        BeefStock::create([
            'barcode' => $barcode, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'exp_date' => now()->addYear()->toDateString(),
            'origin' => '1', 'status' => $status,
        ]);
        BarcodeCost::create(['barcode' => $barcode, 'product_id' => $product->id, 'cost_per_kg' => $cost, 'source_type' => 'boning']);
    }

    // ---------------------------------------------------------------------
    // Nilai Stok
    // ---------------------------------------------------------------------

    /** @test */
    public function stock_value_sums_kg_times_cost_per_warehouse_and_product(): void
    {
        $this->stock('A1', $this->tl, $this->jonggol, 10, 100000);
        $this->stock('A2', $this->tl, $this->jonggol, 5, 80000);
        $this->stock('B1', $this->cr, $this->bogor, 20, 50000);

        $report = CostReports::stockValue();

        $this->assertCount(2, $report['rows']);
        $tl = collect($report['rows'])->firstWhere('product', 'TENDERLOIN');
        $this->assertSame(15.0, $tl['kg']);
        $this->assertSame(1400000.0, $tl['value'], '10 x 100.000 + 5 x 80.000.');
        $this->assertSame(1400000.0 + 1000000.0, $report['total']['value']);
        $this->assertSame(35.0, $report['total']['kg']);
    }

    /** @test */
    public function unvalued_barcodes_are_reported_separately_and_never_counted_as_zero_value(): void
    {
        $this->stock('A1', $this->tl, $this->jonggol, 10, 100000);
        $this->stock('A2', $this->tl, $this->jonggol, 7, null);
        $this->stock('A3', $this->tl, $this->jonggol, 3, null);

        $row = CostReports::stockValue()['rows'][0];

        $this->assertSame(3, $row['barcodes']);
        $this->assertSame(20.0, $row['kg']);
        $this->assertSame(10.0, $row['valued_kg']);
        $this->assertSame(1000000.0, $row['value'], 'Hanya yang bernilai yang dijumlahkan.');
        $this->assertSame(2, $row['unvalued_barcodes']);
        $this->assertSame(10.0, $row['unvalued_kg']);
    }

    /** @test */
    public function a_barcode_with_no_cost_row_at_all_counts_as_unvalued(): void
    {
        BeefStock::create([
            'barcode' => 'NOROW', 'product_id' => $this->tl->id, 'warehouse_id' => $this->jonggol->id, 'grade_id' => $this->grade->id,
            'weight' => 4, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1', 'status' => 'IN_STOCK',
        ]);

        $row = CostReports::stockValue()['rows'][0];

        $this->assertSame(1, $row['unvalued_barcodes']);
        $this->assertSame(0.0, $row['value']);
    }

    /** @test */
    public function stock_that_is_gone_is_left_out_and_the_filters_narrow_the_report(): void
    {
        $this->stock('A1', $this->tl, $this->jonggol, 10, 100000);
        $this->stock('GONE', $this->tl, $this->jonggol, 99, 100000, 'SHIPPED');
        $this->stock('B1', $this->cr, $this->bogor, 20, 50000);

        $this->assertSame(30.0, CostReports::stockValue()['total']['kg']);
        $this->assertSame(10.0, CostReports::stockValue($this->jonggol->id)['total']['kg']);
        $this->assertSame(20.0, CostReports::stockValue(null, $this->cr->id)['total']['kg']);
        $this->assertSame(0.0, CostReports::stockValue($this->bogor->id, $this->tl->id)['total']['kg']);
    }

    // ---------------------------------------------------------------------
    // HPP Penjualan
    // ---------------------------------------------------------------------

    /**
     * Surat jalan dengan barcode di tally-nya; $invoiceAmount null = belum ditagih.
     *
     * @param  array<int, array{0: string, 1: Product, 2: float}>  $items  [barcode, produk, kg]
     */
    private function deliveryOrder(array $items, ?float $invoiceAmount, ?string $date = null, ?Customer $customer = null, ?Product $invoiceProduct = null): DeliveryOrder
    {
        $customer ??= $this->customer;
        $date ??= now()->toDateString();

        $so = SalesOrder::create(['customer_id' => $customer->id, 'delivery_date' => $date, 'po_number' => 'PO-UJI', 'created_by' => $this->user->id, 'status' => 'on_delivery']);
        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'locked']);

        foreach ($items as [$barcode, $product, $kg]) {
            TallyItem::create([
                'tally_id' => $tally->id, 'barcode' => $barcode, 'product_id' => $product->id, 'warehouse_id' => $this->jonggol->id,
                'grade_id' => $this->grade->id, 'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1',
            ]);
        }

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id, 'sales_order_id' => $so->id, 'customer_id' => $customer->id,
            'delivery_date' => $date, 'status' => 'Approved', 'created_by' => $this->user->id,
        ]);

        $receipt = DeliveryOrderReceipt::create([
            'receipt_number' => 'SWM-REC#'.$do->id, 'delivery_order_id' => $do->id, 'sales_order_id' => $so->id, 'customer_id' => $customer->id,
            'delivery_date' => $date, 'total_box' => count($items), 'total_weight' => 1, 'status' => 'Approved',
        ]);

        if ($invoiceAmount !== null) {
            $invoice = Invoice::create([
                'delivery_order_receipt_id' => $receipt->id, 'sales_order_id' => $so->id, 'customer_id' => $customer->id,
                'invoice_date' => $date, 'term_of_payment' => 30, 'status' => 'Belum Dibayar', 'subtotal' => $invoiceAmount,
                'balance' => $invoiceAmount, 'created_by' => $this->user->id,
            ]);
            InvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => ($invoiceProduct ?? $items[0][1])->id, 'box' => 1, 'weight' => 1, 'price' => $invoiceAmount, 'amount' => $invoiceAmount]);
        }

        return $do;
    }

    private function salesCost(?string $from = null, ?string $until = null, ?int $customer = null, ?int $product = null): array
    {
        return CostReports::salesCost(Carbon::parse($from ?? now()->startOfMonth()), Carbon::parse($until ?? now()), $customer, $product);
    }

    /** @test */
    public function sales_cost_is_kg_of_the_shipped_barcodes_times_their_own_cost(): void
    {
        BarcodeCost::create(['barcode' => 'S1', 'product_id' => $this->tl->id, 'cost_per_kg' => 100000, 'source_type' => 'boning']);
        BarcodeCost::create(['barcode' => 'S2', 'product_id' => $this->tl->id, 'cost_per_kg' => 80000, 'source_type' => 'boning']);
        $this->deliveryOrder([['S1', $this->tl, 10], ['S2', $this->tl, 5]], 1900000);

        $report = $this->salesCost();

        $this->assertCount(1, $report['rows']);
        $row = $report['rows'][0];
        $this->assertSame(15.0, $row['kg']);
        $this->assertSame(1400000.0, $row['hpp'], 'Identifikasi khusus: tiap barcode memakai harga pokoknya sendiri, bukan rata-rata.');
        $this->assertSame(1900000.0, $row['sales']);
        $this->assertSame(500000.0, $row['margin']);
        $this->assertSame(0, $row['unvalued_barcodes']);
        $this->assertNotNull($row['invoice']);
    }

    /** @test */
    public function the_cost_is_read_when_the_report_is_made_so_a_backfill_shows_up_at_once(): void
    {
        BarcodeCost::create(['barcode' => 'S1', 'product_id' => $this->tl->id, 'cost_per_kg' => null, 'source_type' => 'boning']);
        $this->deliveryOrder([['S1', $this->tl, 10]], 1500000);

        $before = $this->salesCost()['rows'][0];
        $this->assertSame(0.0, $before['hpp']);
        $this->assertSame(1, $before['unvalued_barcodes']);
        $this->assertNull($before['margin'], 'HPP belum lengkap: tidak ada margin.');

        BarcodeCosts::set('S1', 100000.0, 'boning');

        $after = $this->salesCost()['rows'][0];
        $this->assertSame(1000000.0, $after['hpp']);
        $this->assertSame(500000.0, $after['margin']);
    }

    /** @test */
    public function a_delivery_order_not_invoiced_yet_has_no_sales_and_no_margin(): void
    {
        BarcodeCost::create(['barcode' => 'S1', 'product_id' => $this->tl->id, 'cost_per_kg' => 100000, 'source_type' => 'boning']);
        $this->deliveryOrder([['S1', $this->tl, 10]], null);

        $report = $this->salesCost();
        $row = $report['rows'][0];

        $this->assertNull($row['sales']);
        $this->assertNull($row['margin']);
        $this->assertSame(1000000.0, $row['hpp']);
        $this->assertSame(1, $report['total']['not_invoiced_rows']);
        $this->assertSame(0, $report['total']['margin_rows']);
    }

    /** @test */
    public function the_period_includes_both_boundary_days_and_leaves_the_rest_out(): void
    {
        BarcodeCost::create(['barcode' => 'S1', 'product_id' => $this->tl->id, 'cost_per_kg' => 1000, 'source_type' => 'boning']);
        BarcodeCost::create(['barcode' => 'S2', 'product_id' => $this->tl->id, 'cost_per_kg' => 1000, 'source_type' => 'boning']);
        BarcodeCost::create(['barcode' => 'S3', 'product_id' => $this->tl->id, 'cost_per_kg' => 1000, 'source_type' => 'boning']);
        $this->deliveryOrder([['S1', $this->tl, 1]], 10, '2026-10-01');
        $this->deliveryOrder([['S2', $this->tl, 1]], 10, '2026-10-31');
        $this->deliveryOrder([['S3', $this->tl, 1]], 10, '2026-11-01');

        $this->assertCount(2, $this->salesCost('2026-10-01', '2026-10-31')['rows']);
    }

    /** @test */
    public function customer_and_product_filters_narrow_the_rows_and_the_groupings_add_up(): void
    {
        $other = Customer::create(['name' => 'PELANGGAN LAIN', 'address' => 'Bogor', 'top_days' => 30, 'customer_segment_id' => $this->customer->customer_segment_id]);
        foreach (['S1' => $this->tl, 'S2' => $this->cr, 'S3' => $this->tl] as $barcode => $product) {
            BarcodeCost::create(['barcode' => $barcode, 'product_id' => $product->id, 'cost_per_kg' => 1000, 'source_type' => 'boning']);
        }
        $this->deliveryOrder([['S1', $this->tl, 10], ['S2', $this->cr, 4]], 100, null, $this->customer, $this->tl);
        $this->deliveryOrder([['S3', $this->tl, 6]], 50, null, $other);

        $all = $this->salesCost();
        $this->assertCount(3, $all['rows']);
        $this->assertSame(20.0, $all['total']['kg']);
        $this->assertSame(20000.0, $all['total']['hpp']);
        $this->assertCount(2, $all['by_customer']);
        $this->assertCount(2, $all['by_product']);
        $this->assertSame(20.0, collect($all['by_customer'])->sum('kg'));

        $this->assertCount(2, $this->salesCost(null, null, $this->customer->id)['rows']);
        $this->assertSame(16.0, $this->salesCost(null, null, null, $this->tl->id)['total']['kg']);
    }

    // ---------------------------------------------------------------------
    // Halaman, izin, ekspor
    // ---------------------------------------------------------------------

    /** @test */
    public function the_permission_exists_after_migrating(): void
    {
        $this->assertTrue(Permission::where('name', 'view_cost_reports')->exists());
    }

    /** @test */
    public function both_pages_need_the_permission(): void
    {
        $this->actingAs($this->userWith());
        $this->get('/admin/stock-value-report')->assertForbidden();
        $this->get('/admin/sales-cost-report')->assertForbidden();

        $this->actingAs($this->userWith('view_cost_reports'));
        $this->get('/admin/stock-value-report')->assertOk();
        $this->get('/admin/sales-cost-report')->assertOk();
    }

    /** @test */
    public function the_pages_render_the_numbers(): void
    {
        $this->actingAs($this->userWith('view_cost_reports'));
        $this->stock('A1', $this->tl, $this->jonggol, 10, 100000);
        BarcodeCost::create(['barcode' => 'S1', 'product_id' => $this->tl->id, 'cost_per_kg' => 100000, 'source_type' => 'boning']);
        $this->deliveryOrder([['S1', $this->tl, 10]], 1500000);

        Livewire::test(StockValueReport::class)->assertSee('Rp 1.000.000')->assertSee('TENDERLOIN');
        Livewire::test(SalesCostReport::class)->assertSee('Rp 1.500.000')->assertSee('Rp 500.000')->assertSee('PELANGGAN UJI');
    }

    /** @test */
    public function excel_and_pdf_download_for_both_reports_and_follow_the_filter(): void
    {
        $this->actingAs($this->userWith('view_cost_reports'));
        $this->stock('A1', $this->tl, $this->jonggol, 10, 100000);
        BarcodeCost::create(['barcode' => 'S1', 'product_id' => $this->tl->id, 'cost_per_kg' => 100000, 'source_type' => 'boning']);
        $this->deliveryOrder([['S1', $this->tl, 10]], 1500000);

        foreach ([StockValueReport::class, SalesCostReport::class] as $page) {
            Livewire::test($page)->call('downloadExcel')->assertFileDownloaded();
            Livewire::test($page)->call('downloadPdf')->assertFileDownloaded();
        }

        Livewire::test(StockValueReport::class)
            ->set('warehouseId', $this->bogor->id)
            ->assertSee(__('No stock found.'));
    }
}
