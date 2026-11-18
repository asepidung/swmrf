<?php

namespace App\Filament\Admin\Resources\InvoiceResource\Pages;

use App\Filament\Admin\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Support\MasterDataDeletion;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    /**
     * INV-01 (keputusan Owner): invoice yang sudah ada pembayarannya terkunci. Koreksi dilakukan dengan membatalkan
     * pembayarannya dulu (yang sudah tersedia), bukan dengan mengubah harga/DP/biaya di bawah pembayaran yang berjalan.
     */
    public function mount(int | string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->hasActivePayment()) {
            \Filament\Notifications\Notification::make()
                ->title(__('This invoice already has a payment and cannot be edited. Cancel the payment first to correct it.'))
                ->danger()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Baris invoice dikunci sebelum ditulis. `Invoice::saving()` menghitung
     * ulang `balance`/`status` dari `paid_amount` -- ReceivePayment.php
     * mengunci baris yang sama saat melunasi. Tanpa penguncian yang sama di
     * sini, menyimpan Edit tepat saat pelunasan lain sedang berjalan untuk
     * invoice yang sama bisa menulis balik `paid_amount` yang sudah basi
     * (lost update), menghapus efek pelunasan yang baru saja commit.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            Invoice::whereKey($record->id)->lockForUpdate()->first();

            // INV-01: dibaca ulang di dalam transaksi; tab yang terbuka sebelum pembayaran dicatat tidak boleh menimpanya.
            if ($record->fresh()->hasActivePayment()) {
                \Filament\Notifications\Notification::make()
                    ->title(__('This invoice already has a payment and cannot be edited. Cancel the payment first to correct it.'))
                    ->danger()
                    ->send();

                throw new \Filament\Support\Exceptions\Halt();
            }

            // Faktur tidak boleh dipindah ke pelanggan, penerimaan, atau SO lain lewat Edit (K-03).
            unset($data['customer_id'], $data['delivery_order_receipt_id'], $data['sales_order_id']);

            // K-05: DP tidak boleh dinaikkan melampaui DP tercatat SO-nya; yang sudah ada (riwayat) boleh tetap.
            if (array_key_exists('down_payment', $data)) {
                $batas = max(\App\Support\CustomerDownPayment::availableForInvoice($record->sales_order_id, $record->id), (float) $record->down_payment);
                $data['down_payment'] = min(\App\Support\InvoiceTotals::number($data['down_payment']), $batas);
            }

            $record->update($data);

            return $record;
        });
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('print.invoice', $this->record->id))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
            // payment_allocations.invoice_id RESTRICT -- data pembayaran
            // aman, tapi tanpa ini force-delete invoice yang alokasinya
            // masih ada (termasuk yang pembayarannya sudah dibatalkan --
            // Payment::cancel() TIDAK menghapus baris alokasinya, cuma
            // menandai pembayarannya batal) menampilkan galat SQL mentah.
            Actions\ForceDeleteAction::make()
                ->action(function () {
                    $record = $this->getRecord();

                    if (MasterDataDeletion::attempt(fn () => $record->forceDelete(), __('Invoice').' '.$record->invoice_number)) {
                        $this->redirect($this->getResource()::getUrl('index'));
                    }
                }),
        ];
    }
}
