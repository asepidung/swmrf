<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Admin\Resources\InvoiceResource\Pages\EditInvoice;
use App\Models\Invoice;
use Livewire\Livewire;

/**
 * Kepala dan total invoice tidak boleh ditentukan browser (audit pra-rilis 14 November 2026, K-03).
 *
 * `delivery_order_receipt_id` dan `sales_order_id` adalah field tersembunyi, `customer_id` field nonaktif
 * yang tetap disimpan, dan `subtotal`/`charge`/`total_weight` field nonaktif yang tetap disimpan. Semuanya
 * bisa diisi lewat state Livewire: invoice bisa ditagihkan ke pelanggan lain, ditempelkan ke penerimaan
 * lain, atau membawa total yang tidak cocok dengan barisnya (piutang mengikuti total itu).
 */
class InvoiceHeaderTamperTest extends InvoiceTest
{
    private function buka(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($this->user)
            ->withQueryParams(['delivery_order_receipt_id' => $this->receiptRegular->id])
            ->test(CreateInvoice::class);
    }

    public function test_the_invoice_is_billed_to_the_customer_of_its_delivery_receipt(): void
    {
        $this->buka()
            ->set('data.customer_id', $this->customerExchange->id)
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::where('delivery_order_receipt_id', $this->receiptRegular->id)->firstOrFail();

        $this->assertSame($this->customerRegular->id, $invoice->customer_id, 'Pelanggan invoice harus pelanggan penerimaannya.');
    }

    public function test_the_invoice_stays_attached_to_the_receipt_the_page_was_opened_for(): void
    {
        $this->buka()
            ->set('data.delivery_order_receipt_id', $this->receiptExchange->id)
            ->set('data.sales_order_id', $this->receiptExchange->sales_order_id)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(0, Invoice::where('delivery_order_receipt_id', $this->receiptExchange->id)->count(), 'Penerimaan lain tidak boleh ikut ditagih.');

        $invoice = Invoice::where('delivery_order_receipt_id', $this->receiptRegular->id)->firstOrFail();

        $this->assertSame($this->receiptRegular->sales_order_id, $invoice->sales_order_id);
        $this->assertSame('Approved', $this->receiptExchange->fresh()->status);
    }

    public function test_the_stored_totals_are_recomputed_from_the_lines_not_taken_from_the_browser(): void
    {
        $this->buka()
            ->set('data.subtotal', 1)
            ->set('data.charge', 777)
            ->set('data.total_weight', 999)
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::where('delivery_order_receipt_id', $this->receiptRegular->id)->firstOrFail();

        $this->assertEquals(1000000.0, (float) $invoice->subtotal, 'Subtotal harus jumlah baris barangnya.');
        $this->assertEquals(0.0, (float) $invoice->charge, 'Tanpa baris biaya tambahan, charge harus nol.');
        $this->assertEquals(10.0, (float) $invoice->total_weight);
        $this->assertEquals(1000000.0, (float) $invoice->balance);
    }

    public function test_editing_an_invoice_cannot_move_it_to_another_customer_or_receipt(): void
    {
        $this->buka()->call('create')->assertHasNoFormErrors();
        $invoice = Invoice::where('delivery_order_receipt_id', $this->receiptRegular->id)->firstOrFail();

        Livewire::actingAs($this->user)
            ->test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
            ->set('data.customer_id', $this->customerExchange->id)
            ->set('data.delivery_order_receipt_id', $this->receiptExchange->id)
            ->set('data.sales_order_id', $this->receiptExchange->sales_order_id)
            ->call('save')
            ->assertHasNoFormErrors();

        $segar = $invoice->fresh();

        $this->assertSame($this->customerRegular->id, $segar->customer_id);
        $this->assertSame($this->receiptRegular->id, $segar->delivery_order_receipt_id);
        $this->assertSame($this->receiptRegular->sales_order_id, $segar->sales_order_id);
    }
}
