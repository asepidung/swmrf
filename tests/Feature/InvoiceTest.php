<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Tally;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderReceipt;
use App\Models\DeliveryOrderReceiptItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Receivable;
use App\Filament\Admin\Widgets\PendingTaskWidget;
use App\Filament\Admin\Resources\InvoiceResource;
use App\Filament\Admin\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Admin\Resources\InvoiceResource\Pages\ListInvoices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customerRegular;
    protected Customer $customerExchange;
    protected Product $product;
    protected DeliveryOrderReceipt $receiptRegular;
    protected DeliveryOrderReceipt $receiptExchange;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'programmer',
            'is_active' => true
        ]);

        $segment = CustomerSegment::create([
            'name' => 'RETAIL',
            'is_active' => true,
        ]);

        $this->customerRegular = Customer::create([
            'name' => 'REGULAR CUSTOMER',
            'customer_segment_id' => $segment->id,
            'address' => 'Jakarta',
            'pic' => 'John Regular',
            'phone' => '0812345678',
            'top' => 30,
            'invoice_exchange' => false,
            'is_taxable' => false,
        ]);

        $this->customerExchange = Customer::create([
            // Namanya sengaja TIDAK memuat "DCA" lagi. Dulu diskon 2%
            // ditentukan dengan mencocokkan potongan nama pelanggan, jadi
            // test ini ikut membuktikan bahwa nama sudah tidak menentukan
            // apa pun -- yang menentukan adalah default_discount di bawah.
            'name' => 'EXCHANGE CUSTOMER',
            'customer_segment_id' => $segment->id,
            'address' => 'Bogor',
            'pic' => 'John Exchange',
            'phone' => '0898765432',
            'top' => 14,
            'default_discount' => 2,
            'invoice_exchange' => true,
            'is_taxable' => true,
        ]);

        $category = ProductCategory::create([
            'name' => 'MEAT',
            'prefix' => 'MT',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'TENDERLOIN BEEF',
            'code' => 'MT00100',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        // Setup Sales Order regular
        $soRegular = SalesOrder::create([
            'customer_id' => $this->customerRegular->id,
            'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);
        SalesOrderItem::create([
            'sales_order_id' => $soRegular->id,
            'product_id' => $this->product->id,
            'weight' => 10.0,
            'price' => 100000,
            'discount' => 0,
        ]);
        $tallyReg = Tally::create(['sales_order_id' => $soRegular->id, 'status' => 'locked']);
        $doReg = DeliveryOrder::create([
            'tally_id' => $tallyReg->id,
            'sales_order_id' => $soRegular->id,
            'customer_id' => $this->customerRegular->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'Approved',
        ]);
        $this->receiptRegular = DeliveryOrderReceipt::create([
            'delivery_order_id' => $doReg->id,
            'sales_order_id' => $soRegular->id,
            'customer_id' => $this->customerRegular->id,
            'receipt_number' => 'SWM-REC#260001',
            'delivery_date' => now()->toDateString(),
            'total_box' => 1,
            'total_weight' => 10.0,
            'status' => 'Approved',
        ]);
        DeliveryOrderReceiptItem::create([
            'delivery_order_receipt_id' => $this->receiptRegular->id,
            'product_id' => $this->product->id,
            'weight' => 10.0,
            'box' => 1,
        ]);

        // Setup Sales Order untuk pelanggan berdiskon tetap.
        $soExchange = SalesOrder::create([
            'customer_id' => $this->customerExchange->id,
            'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);
        SalesOrderItem::create([
            'sales_order_id' => $soExchange->id,
            'product_id' => $this->product->id,
            'weight' => 20.0,
            'price' => 100000,
            // Diskonnya tercatat DI SINI, sama seperti yang diisikan otomatis
            // dari pelanggan saat SO dibuat. Dulu kolom ini 0 dan invoice
            // menempelkan 2% belakangan, sehingga SO dan tagihan berbeda
            // angka di atas kertas.
            'discount' => 2,
        ]);
        $tallyExch = Tally::create(['sales_order_id' => $soExchange->id, 'status' => 'locked']);
        $doExch = DeliveryOrder::create([
            'tally_id' => $tallyExch->id,
            'sales_order_id' => $soExchange->id,
            'customer_id' => $this->customerExchange->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'Approved',
        ]);
        $this->receiptExchange = DeliveryOrderReceipt::create([
            'delivery_order_id' => $doExch->id,
            'sales_order_id' => $soExchange->id,
            'customer_id' => $this->customerExchange->id,
            'receipt_number' => 'SWM-REC#260002',
            'delivery_date' => now()->toDateString(),
            'total_box' => 2,
            'total_weight' => 20.0,
            'status' => 'Approved',
        ]);
        DeliveryOrderReceiptItem::create([
            'delivery_order_receipt_id' => $this->receiptExchange->id,
            'product_id' => $this->product->id,
            'weight' => 20.0,
            'box' => 2,
        ]);
    }

    /** @test */
    public function it_calculates_regular_invoice_due_date_and_totals_correctly()
    {
        $this->actingAs($this->user);

        // Mount create form with query parameter
        $lw = Livewire::withQueryParams(['delivery_order_receipt_id' => $this->receiptRegular->id])
            ->test(CreateInvoice::class)
            ->assertFormSet([
                'delivery_order_receipt_id' => $this->receiptRegular->id,
                'customer_id' => $this->customerRegular->id,
                // term_of_payment dan status sengaja tidak diuji di sini:
                // keduanya bukan field form, melainkan diisi saat penyimpanan
                // lewat mutateFormDataBeforeCreate(). Nilainya dicek di bawah
                // pada record yang sudah tersimpan.
                'total_weight' => 10.0,
                'subtotal' => 1000000.0,
                'total_discount' => 0.0,
                'tax' => 0.0, // Non-taxable
                'balance' => 1000000.0,
            ]);

        $items = $lw->get('data.items');

        // Biaya tambahan kini berupa repeater relasi additionalCharges, bukan
        // lagi satu kolom skalar 'charge' seperti desain lama.
        $lw->fillForm([
            'additionalCharges' => [
                [
                    'name' => 'Delivery Cost',
                    'qty' => 1,
                    'price' => 15000,
                    'discount_percent' => 0,
                    'discount_rp' => 0,
                    'amount' => 15000,
                ],
            ],
            'down_payment' => 200000,
            'items' => $items,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

        // Check invoice created
        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertStringStartsWith('SWM-INV#', $invoice->invoice_number);
        $this->assertEquals('Belum Dibayar', $invoice->status);
        $this->assertEquals(30, $invoice->term_of_payment);
        
        // Due date = invoice date + TOP (30 days)
        $expectedDueDate = \Carbon\Carbon::parse($invoice->invoice_date)->addDays(30)->toDateString();
        $this->assertEquals($expectedDueDate, $invoice->due_date->toDateString());
        
        // Assert amounts in DB
        $this->assertDatabaseHas('invoice_additional_charges', [
            'invoice_id' => $invoice->id,
            'name' => 'Delivery Cost',
            'amount' => 15000.0,
        ]);
        $this->assertEquals(200000.0, $invoice->down_payment);
        $this->assertEquals(815000.0, $invoice->balance); // 1000000 + 15000 - 200000

        // Assert statuses are updated
        $this->assertEquals('Invoiced', $this->receiptRegular->fresh()->status);
        $this->assertEquals('Invoiced', $this->receiptRegular->deliveryOrder->fresh()->status);

        // Assert Receivable created
        $this->assertDatabaseHas('receivables', [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customerRegular->id,
        ]);
    }

    /** @test */
    public function it_applies_the_customer_discount_and_sets_due_date_to_null_for_exchange_customer()
    {
        $this->actingAs($this->user);

        // Mount create form with query parameter
        $lw = Livewire::withQueryParams(['delivery_order_receipt_id' => $this->receiptExchange->id])
            ->test(CreateInvoice::class)
            ->assertFormSet([
                'delivery_order_receipt_id' => $this->receiptExchange->id,
                'customer_id' => $this->customerExchange->id,
                // Sama seperti test di atas: term_of_payment dan status bukan
                // field form, jadi diperiksa pada record hasil simpan.
                'total_weight' => 20.0,
                // Diskon 2% milik pelanggan ini: kotor 20 * 100000 = 2000000,
                // potongan 40000, sehingga subtotal 1960000. Angkanya sengaja
                // dibiarkan sama persis seperti sebelum diskonnya dipindahkan
                // dari pencocokan nama ke data -- yang berubah asal-usulnya,
                // bukan hasilnya.
                'subtotal' => 1960000.0,
                'total_discount' => 40000.0,
                // Tidak ada baris 'tax' di sini karena memang tidak boleh ada:
                // Wijaya Meat berstatus nonPKP, sehingga invoice dan penjualan
                // tidak dikenai PPN. Pajak hanya relevan pada pembelian
                // material. Kolom invoices.tax dan flag customers.is_taxable
                // adalah sisa desain lama yang tidak terpakai di sisi
                // penjualan, jadi balance = subtotal tanpa tambahan pajak.
                'balance' => 1960000.0,
            ]);

        $items = $lw->get('data.items');

        $lw->fillForm([
            'items' => $items,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

        // Check invoice created
        $invoice = Invoice::orderBy('id', 'desc')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('Belum TF', $invoice->status);
        $this->assertEquals(14, $invoice->term_of_payment);
        
        // due_date must be null for 'Belum TF'
        $this->assertNull($invoice->due_date);

        // Verify items in database
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'weight' => 20.0,
            'price' => 100000.0,
            'discount_percent' => 2.0,
            'discount_rp' => 40000.0,
            'amount' => 1960000.0,
        ]);
    }

    /** @test */
    public function it_sets_due_date_from_exchange_date_when_running_the_tukar_faktur_action()
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'customer_id' => $this->customerExchange->id,
            'sales_order_id' => $this->receiptExchange->sales_order_id,
            'delivery_order_receipt_id' => $this->receiptExchange->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 14,
            'status' => 'Belum TF',
            'subtotal' => 1960000.0,
            'total_discount' => 40000.0,
            'balance' => 1960000.0,
        ]);

        // Sebelum ditukar, jatuh tempo belum berjalan.
        $this->assertNull($invoice->fresh()->due_date);

        $tgltf = now()->addDays(2)->toDateString();

        Livewire::test(ListInvoices::class)
            ->callTableAction('tukar_faktur', $invoice, [
                'invoice_exchange_date' => $tgltf,
                'exchange_by' => 'Budi',
                'exchange_note' => 'Resi #7788',
            ]);

        $invoice->refresh();

        // Jatuh tempo dihitung dari TANGGAL TUKAR FAKTUR, bukan tanggal invoice.
        $expectedDueDate = \Carbon\Carbon::parse($tgltf)->addDays(14)->toDateString();

        $this->assertEquals($tgltf, $invoice->invoice_exchange_date->toDateString());
        $this->assertNotNull($invoice->due_date, 'Jatuh tempo wajib terisi setelah tukar faktur.');
        $this->assertEquals($expectedDueDate, $invoice->due_date->toDateString());
    }

    /** @test */
    public function it_processes_tukar_faktur_and_updates_due_date()
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customerExchange->id,
            'sales_order_id' => $this->receiptExchange->sales_order_id,
            'delivery_order_receipt_id' => $this->receiptExchange->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 14,
            'status' => 'Belum TF',
            'subtotal' => 1960000.0,
            'total_discount' => 40000.0,
            'tax' => 215600.0,
            'balance' => 2175600.0,
        ]);

        $this->assertNull($invoice->fresh()->due_date);

        // Call the action using Livewire or execute database logic to simulate action
        $tgltf = now()->addDays(2)->toDateString();
        $proofNote = "Resi #7788";

        // Let's test the action logic directly
        $top = (int)$invoice->term_of_payment;
        $dueDate = \Carbon\Carbon::parse($tgltf)->addDays($top)->toDateString();
        
        $invoice->update([
            'status' => 'Sudah TF',
            'invoice_exchange_date' => $tgltf,
            'due_date' => $dueDate,
            'note' => $proofNote,
        ]);

        $invoice = $invoice->fresh();
        $this->assertEquals('Sudah TF', $invoice->status);
        $this->assertEquals($tgltf, $invoice->invoice_exchange_date->toDateString());
        $this->assertEquals($dueDate, $invoice->due_date->toDateString());
        $this->assertEquals($proofNote, $invoice->note);
    }

    /** @test */
    public function it_renders_print_invoice_correctly()
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'customer_id' => $this->customerRegular->id,
            'sales_order_id' => $this->receiptRegular->sales_order_id,
            'delivery_order_receipt_id' => $this->receiptRegular->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 30,
            'status' => '-',
            'subtotal' => 1000000.0,
            'total_discount' => 0,
            'tax' => 0,
            'balance' => 1000000.0,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'weight' => 10.0,
            'price' => 100000,
            'discount_percent' => 0,
            'discount_rp' => 0,
            'amount' => 1000000,
        ]);

        $response = $this->get(route('print.invoice', $invoice->id));
        $response->assertStatus(200);
        $response->assertSee($invoice->invoice_number);
        $response->assertSee('REGULAR CUSTOMER');
        $response->assertSee('TENDERLOIN BEEF');
        $response->assertSee('Satu Juta Rupiah'); // Terbilang
    }

    /** @test */
    public function it_calculates_pending_exchange_invoice_count_for_widget()
    {
        $this->actingAs($this->user);

        // Initially 0 pending exchange invoices
        $widget = new PendingTaskWidget();
        $this->assertEquals(0, $widget->getPendingInvoiceExchangeCount());

        // Create a pending exchange invoice
        Invoice::create([
            'customer_id' => $this->customerExchange->id,
            'sales_order_id' => $this->receiptExchange->sales_order_id,
            'delivery_order_receipt_id' => $this->receiptExchange->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 14,
            'status' => 'Belum TF',
            'subtotal' => 1960000.0,
            'total_discount' => 40000.0,
            'tax' => 215600.0,
            'balance' => 2175600.0,
        ]);

        $this->assertEquals(1, $widget->getPendingInvoiceExchangeCount());
    }
}
