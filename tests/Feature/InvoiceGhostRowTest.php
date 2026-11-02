<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\InvoiceResource;
use App\Filament\Admin\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\Invoice;
use Livewire\Livewire;

/**
 * Baris biaya lain yang sudah dihapus tidak boleh hidup lagi sebagai "baris hantu" yang menolak invoice disimpan (Owner, 2 November 2026).
 */
class InvoiceGhostRowTest extends InvoiceTest
{
    public function test_ghost_charge_rows_are_recognised_by_the_missing_qty(): void
    {
        $this->assertSame(
            ['a' => ['name' => 'Ice Gell', 'qty' => 1, 'price' => 5000]],
            InvoiceResource::withoutGhostCharges(['a' => ['name' => 'Ice Gell', 'qty' => 1, 'price' => 5000], 'b' => ['discount_rp' => 0, 'amount' => 0]]),
        );
    }

    public function test_the_invoice_saves_when_a_ghost_row_came_back(): void
    {
        $c = Livewire::actingAs($this->user)
            ->withQueryParams(['delivery_order_receipt_id' => $this->receiptRegular->id])
            ->test(CreateInvoice::class);

        $c->set('data.additionalCharges', ['ghost-1' => ['discount_rp' => 0, 'amount' => 0]]);
        $c->call('create')->assertHasNoFormErrors();

        $this->assertSame(1, Invoice::where('delivery_order_receipt_id', $this->receiptRegular->id)->count());
        $this->assertSame(0, Invoice::first()->additionalCharges()->count());
    }

    public function test_a_deleted_additional_charge_row_stays_deleted(): void
    {
        $c = Livewire::actingAs($this->user)
            ->withQueryParams(['delivery_order_receipt_id' => $this->receiptRegular->id])
            ->test(CreateInvoice::class);

        $c->callFormComponentAction('additionalCharges', 'add');
        $key = array_key_first($c->get('data.additionalCharges'));
        $c->callFormComponentAction('additionalCharges', 'delete', arguments: ['item' => $key]);

        $this->assertSame([], $c->get('data.additionalCharges') ?? []);
    }
}
