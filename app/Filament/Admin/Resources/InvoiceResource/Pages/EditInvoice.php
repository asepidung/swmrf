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
            Actions\RestoreAction::make(),
        ];
    }
}
