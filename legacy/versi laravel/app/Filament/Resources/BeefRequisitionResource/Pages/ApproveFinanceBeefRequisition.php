<?php

namespace App\Filament\Resources\BeefRequisitionResource\Pages;

use App\Filament\Resources\BeefRequisitionResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;

class ApproveFinanceBeefRequisition extends ViewRecord
{
    protected static string $resource = BeefRequisitionResource::class;

    /* Menambahkan tombol aksi pada halaman finance approve */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve & Terbitkan PO')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'PO Created', 'reject_note' => null]);

                    /* Memanggil fungsi generate PO */
                    BeefRequisitionResource::generatePurchaseOrder($this->record);

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Action::make('reject')
                ->label('Kembalikan ke Purchasing')
                ->color('danger')
                ->form([
                    Textarea::make('reject_note')->label('Alasan Penolakan')->required(),
                ])
                ->action(function (array $data) {
                    // SEBELUMNYA: 'Rejected' (SALAH)
                    // SEKARANG: 'Returned to Purchasing' (BENAR, biar Ayu bisa edit/review lagi)
                    $this->record->update(['status' => 'Returned to Purchasing', 'reject_note' => $data['reject_note']]);
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}
