<?php

namespace Tests\Feature;

use App\Filament\Admin\Widgets\PendingTaskWidget;
use App\Models\Customer;
use App\Models\BankAccount;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderReceipt;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Permission;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Apa yang terjadi pada dokumen di sekitar Invoice saat ia dibuat dan dihapus.
 *
 * Invoice tidak berdiri sendiri: membuatnya melahirkan piutang dan menandai
 * surat jalan beserta bukti terimanya sebagai sudah ditagih. Menghapusnya
 * dulu tidak membatalkan satu pun dari itu.
 */
class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Penagih', 'username' => 'penagih', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->customer = Customer::create([
            'name' => 'PELANGGAN UJI',
            'address' => 'Bogor',
            'top_days' => 30,
            'customer_segment_id' => CustomerSegment::firstOrCreate(['name' => 'RETAIL'])->id,
        ]);
    }

    private function beriIzin(string $name): void
    {
        $this->user->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => $name],
                ['module_name' => 'Invoices', 'description' => $name],
            )->id
        );

        $this->user->refresh();
    }

    /** Rangkaian dokumen sampai bukti terima, siap ditagihkan. */
    private function buatBuktiTerima(): DeliveryOrderReceipt
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'po_number' => 'PO-UJI',
            'created_by' => $this->user->id,
            'status' => 'on_delivery',
        ]);

        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'locked']);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'Approved',
            'created_by' => $this->user->id,
        ]);

        return DeliveryOrderReceipt::create([
            'receipt_number' => 'SWM-REC#'.$do->id,
            'delivery_order_id' => $do->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(),
            'total_box' => 1,
            'total_weight' => 100,
            'status' => 'Approved',
        ]);
    }

    private function buatInvoice(DeliveryOrderReceipt $receipt): Invoice
    {
        $invoice = Invoice::create([
            'delivery_order_receipt_id' => $receipt->id,
            'sales_order_id' => $receipt->sales_order_id,
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 30,
            'status' => 'Belum Dibayar',
            'subtotal' => 1000000,
            'balance' => 1000000,
            'created_by' => $this->user->id,
        ]);

        // Meniru apa yang dilakukan halaman CreateInvoice sesudah menyimpan.
        Receivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
        ]);

        $receipt->update(['status' => 'Invoiced']);
        $receipt->deliveryOrder->update(['status' => 'Invoiced']);

        return $invoice->fresh();
    }

    /**
     * Invoice yang sudah dibayar TIDAK boleh bisa dihapus.
     *
     * Pembayaran pelanggan tercatat menunjuk ke invoicenya. Kalau invoicenya
     * hilang, pembayaran itu menunjuk ke dokumen yang tidak ada lagi -- dan
     * uang yang sudah masuk lenyap dari jejaknya tanpa satu pun error.
     */
    public function test_an_invoice_with_a_customer_payment_cannot_be_deleted(): void
    {
        $invoice = $this->buatInvoice($this->buatBuktiTerima());

        $payment = Payment::create([
            'customer_group_id' => CustomerGroup::firstOrCreate(['name' => 'GRUP UJI'])->id,
            'bank_account_id' => BankAccount::create([
                'initial' => 'UJI',
                'bank_name' => 'BANK UJI',
                'account_number' => '0001',
                'account_holder' => 'WIJAYA MEAT',
                'is_active' => true,
            ])->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000000,
        ]);

        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_allocated' => 1000000,
        ]);

        $this->expectException(\Exception::class);

        $invoice->delete();
    }

    /** Yang belum dibayar tetap boleh dihapus. */
    public function test_an_unpaid_invoice_can_still_be_deleted(): void
    {
        $invoice = $this->buatInvoice($this->buatBuktiTerima());

        $invoice->delete();

        $this->assertSoftDeleted($invoice);
    }

    /**
     * Menghapus invoice ikut membatalkan piutangnya.
     *
     * Foreign key-nya memang cascadeOnDelete, tetapi hapus lunak adalah
     * UPDATE, bukan DELETE -- cascade itu tidak pernah jalan. Tanpa penanganan
     * sendiri, piutangnya tetap hidup dan tetap ditagihkan kepada pelanggan
     * untuk invoice yang sudah tidak ada.
     */
    public function test_deleting_an_invoice_also_withdraws_its_receivable(): void
    {
        $invoice = $this->buatInvoice($this->buatBuktiTerima());

        $receivable = Receivable::where('invoice_id', $invoice->id)->firstOrFail();

        $invoice->delete();

        $this->assertSoftDeleted($receivable);
    }

    /**
     * Dan mengembalikan surat jalannya ke keadaan belum ditagih.
     *
     * Kalau tidak, keduanya tetap bertanda 'Invoiced' padahal invoicenya sudah
     * tidak ada.
     */
    public function test_deleting_an_invoice_releases_the_delivery_documents(): void
    {
        $receipt = $this->buatBuktiTerima();
        $invoice = $this->buatInvoice($receipt);

        $invoice->delete();

        $this->assertSame('Approved', $receipt->fresh()->status);
        $this->assertSame('Approved', $receipt->deliveryOrder->fresh()->status);
    }

    /** Memulihkannya mengembalikan keduanya juga, bukan cuma invoicenya. */
    public function test_restoring_an_invoice_puts_everything_back(): void
    {
        $receipt = $this->buatBuktiTerima();
        $invoice = $this->buatInvoice($receipt);
        $receivable = Receivable::where('invoice_id', $invoice->id)->firstOrFail();

        $invoice->delete();
        $invoice->restore();

        $this->assertNull($receivable->fresh()->deleted_at);
        $this->assertSame('Invoiced', $receipt->fresh()->status);
        $this->assertSame('Invoiced', $receipt->deliveryOrder->fresh()->status);
    }

    /**
     * Dashboard memberitahu berapa bukti terima yang belum ditagihkan.
     *
     * Selama belum, uangnya tidak pernah diminta: barangnya sudah sampai,
     * pelanggannya sudah menandatangani, dan tidak ada tagihan yang berjalan.
     */
    public function test_the_dashboard_counts_receipts_that_have_not_been_invoiced(): void
    {
        $this->beriIzin('create_invoices');

        $belum = $this->buatBuktiTerima();
        $sudah = $this->buatBuktiTerima();
        $this->buatInvoice($sudah);

        $widget = new PendingTaskWidget();

        $this->assertSame(1, $widget->getDraftInvoiceCount());
        $this->assertNotNull($belum->fresh());
    }

    /** Dan hanya kepada yang memang membuat invoice. */
    public function test_the_draft_invoice_count_stays_hidden_from_everyone_else(): void
    {
        $this->buatBuktiTerima();

        $widget = new PendingTaskWidget();

        $this->assertSame(0, $widget->getDraftInvoiceCount());
    }

    /**
     * Invoice yang dihapus membuat bukti terimanya kembali menunggu tagihan.
     *
     * Ini yang membuat penghapusan bisa dibetulkan: dokumennya muncul lagi di
     * daftar Draft Invoice, bukan hilang selamanya.
     */
    public function test_a_deleted_invoice_puts_its_receipt_back_in_the_queue(): void
    {
        $this->beriIzin('create_invoices');

        $receipt = $this->buatBuktiTerima();
        $invoice = $this->buatInvoice($receipt);

        $widget = new PendingTaskWidget();
        $this->assertSame(0, $widget->getDraftInvoiceCount());

        $invoice->delete();

        $this->assertSame(1, $widget->getDraftInvoiceCount());
    }
}
