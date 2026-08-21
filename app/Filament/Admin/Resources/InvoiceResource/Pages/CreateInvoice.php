<?php

namespace App\Filament\Admin\Resources\InvoiceResource\Pages;

use App\Filament\Admin\Resources\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use App\Models\DeliveryOrderReceipt;
use App\Models\SalesOrderItem;
use App\Models\Invoice;
use App\Models\Receivable;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount(): void
    {
        $receiptId = request()->query('delivery_order_receipt_id');

        if (!$receiptId || !DeliveryOrderReceipt::where('id', $receiptId)->exists()) {
            $this->redirect(\App\Filament\Admin\Resources\DeliveryOrderReceiptResource::getUrl('index'));
            return;
        }

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $receipt = DeliveryOrderReceipt::with('customer')->find($data['delivery_order_receipt_id'] ?? null);
        if ($receipt && $receipt->customer) {
            $data['term_of_payment'] = $receipt->customer->top ?? 0;
        } else {
            $data['term_of_payment'] = 0;
        }

        // Customer tukar faktur wajib mulai dari 'Belum TF', bukan 'Belum Dibayar'.
        // Status inilah yang dibaca hook saving() di model Invoice untuk menahan
        // due_date tetap null sampai fakturnya benar-benar ditukar, dan yang
        // dipakai modul Receivable untuk menandai piutang yang belum bisa ditagih.
        $isExchange = (bool) ($receipt?->customer?->invoice_exchange);
        $data['status'] = $isExchange ? 'Belum TF' : 'Belum Dibayar';

        // due_date sengaja tidak dihitung di sini. Hook saving() di model Invoice
        // sudah menjadi satu-satunya pemilik logika itu dan akan menimpa nilai
        // apa pun yang diisi dari sini, jadi menghitungnya dua kali hanya
        // membuka peluang kedua rumus berbeda arah.

        return $data;
    }

    protected function afterCreate(): void
    {
        $invoice = $this->record;

        DB::transaction(function () use ($invoice) {
            // Update DeliveryOrderReceipt and DeliveryOrder status
            if ($invoice->delivery_order_receipt_id) {
                $receipt = $invoice->deliveryOrderReceipt;
                if ($receipt) {
                    $receipt->update(['status' => 'Invoiced']);
                    if ($receipt->deliveryOrder) {
                        $receipt->deliveryOrder->update(['status' => 'Invoiced']);
                    }
                }
            }

            // Create Receivable
            Receivable::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'customer_group_id' => $invoice->customer?->customer_group_id,
            ]);
        });
    }
}
