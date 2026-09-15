<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\InvoiceResource\Pages\EditInvoice;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran Invoice, 15 September 2026.
 *
 * Alur yang dulu meledak: bayar invoice -> batalkan pembayarannya
 * (Payment::cancel() cuma menandai cancelled_at, TIDAK menghapus baris
 * payment_allocations) -> hapus lunak invoicenya (lolos, guard hanya
 * mengecek alokasi yang BELUM dibatalkan) -> hapus permanen -- dan
 * `payment_allocations.invoice_id` RESTRICT membuat query mentah gagal
 * tanpa notifikasi ramah.
 */
class InvoiceLockingAndDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        $segment = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $group = CustomerGroup::create(['name' => 'FIXTURE GROUP']);

        return Customer::create([
            'name' => 'FIXTURE CUSTOMER', 'customer_segment_id' => $segment->id,
            'customer_group_id' => $group->id, 'address' => 'X', 'pic' => 'X',
            'phone' => '08', 'top' => 30,
        ]);
    }

    private function paidInvoice(): Invoice
    {
        $customer = $this->customer();

        $salesOrder = SalesOrder::create([
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
            'po_number' => 'PO-FIXTURE-'.uniqid(),
            'status' => 'ready',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 14,
            'status' => 'Belum TF',
            'subtotal' => 1000000.0,
            'total_discount' => 0.0,
            'balance' => 1000000.0,
        ]);

        $group = CustomerGroup::create(['name' => 'FIXTURE PAYMENT GROUP-'.uniqid()]);
        $bankAccount = BankAccount::create([
            'initial' => 'FX'.substr(uniqid(), -4), 'bank_name' => 'BANK FIXTURE',
            'account_number' => '000', 'account_holder' => 'X', 'is_active' => true,
        ]);
        $payment = Payment::create([
            'customer_group_id' => $group->id,
            'bank_account_id' => $bankAccount->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000000.0,
            'total_deduction' => 0.0,
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_allocated' => 1000000.0,
        ]);

        $invoice->applyPayment(1000000.0);

        return $invoice->fresh();
    }

    /** @test */
    public function force_deleting_an_invoice_after_its_payment_was_cancelled_shows_a_friendly_notification_not_a_raw_sql_error(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($user);

        $invoice = $this->paidInvoice();
        $payment = PaymentAllocation::where('invoice_id', $invoice->id)->first()->payment;

        // Batalkan pembayarannya -- ini TIDAK menghapus baris
        // payment_allocations, cuma menandai payment-nya cancelled_at.
        $payment->cancel('Uji coba');

        // Guard Invoice::deleting() sekarang lolos (cuma menghitung alokasi
        // yang belum dibatalkan), jadi hapus lunak berhasil dan invoicenya
        // menjadi trashed -- syarat ForceDeleteAction supaya muncul.
        $invoice->delete();
        $this->assertTrue($invoice->fresh()->trashed());

        // payment_allocations.invoice_id masih menunjuk ke invoice ini
        // (RESTRICT) walau pembayarannya sudah batal -- force delete di
        // sini HARUS ditangkap MasterDataDeletion::attempt(), bukan
        // meledak sebagai QueryException mentah.
        Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
            ->mountAction('forceDelete')
            ->callMountedAction()
            ->assertNotified();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    // =========================================================================
    // Race: Payment::cancel() mengunci baris invoice, bukan menimpa yang basi
    // =========================================================================

    /** @test */
    public function cancelling_a_payment_locks_the_invoice_row_instead_of_overwriting_a_stale_read(): void
    {
        $invoice = $this->paidInvoice();
        $payment = PaymentAllocation::where('invoice_id', $invoice->id)->first()->payment;

        $this->assertSame(1000000.0, (float) $invoice->fresh()->paid_amount);

        $payment->cancel('Uji race');

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->paid_amount);
        $this->assertSame(1000000.0, (float) $invoice->balance);
        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->status);
    }

    /** @test */
    public function cancelling_the_same_payment_twice_is_rejected_not_double_reversed(): void
    {
        $invoice = $this->paidInvoice();
        $payment = PaymentAllocation::where('invoice_id', $invoice->id)->first()->payment;

        $payment->cancel('Pertama');

        $this->expectException(\RuntimeException::class);
        $payment->cancel('Kedua');
    }
}
