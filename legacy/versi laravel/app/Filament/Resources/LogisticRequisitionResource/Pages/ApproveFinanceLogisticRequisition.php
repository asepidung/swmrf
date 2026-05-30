<?php

namespace App\Filament\Resources\LogisticRequisitionResource\Pages;

use App\Filament\Resources\LogisticRequisitionResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ApproveFinanceLogisticRequisition extends ViewRecord
{
    protected static string $resource = LogisticRequisitionResource::class;

    // Ubah judul biar jelas ini halaman khusus Finance
    protected static ?string $title = 'Finance Approval & Generate PO';

    protected function getHeaderActions(): array
    {
        return [
            /* TOMBOL APPROVE & BIKIN PO (HIJAU) */
            Actions\Action::make('approve_finance')
                ->label('Approve & Generate PO')
                ->icon('heroicon-m-document-text')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Request & Buat PO?')
                ->modalDescription('Dokumen ini akan disetujui, dan Purchase Order (PO) Logistic akan otomatis diterbitkan.')
                ->visible(function () {
                    /** @var \App\Models\User $user */
                    $user = auth()->user();
                    return $this->record->status === 'Pending Finance' && ($user->hasRole('super_admin') || $user->can('approve_logistic::requisition'));
                })
                ->action(function () {
                    // 1. Eksekusi pemindahan data ke tabel PO
                    LogisticRequisitionResource::generatePurchaseOrder($this->record);

                    // 2. Update status Logistic Requisition
                    $this->record->update(['status' => 'PO Created']);

                    Notification::make()->title('PO Logistic Berhasil Diterbitkan!')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            /* TOMBOL REJECT - PANTULAN KE PURCHASING (MERAH) */
            Actions\Action::make('reject_finance')
                ->label('Return to Purchasing')
                ->icon('heroicon-m-arrow-uturn-left') // Ikon putar balik
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make('reject_note')
                        ->label('Alasan Penolakan / Catatan Revisi')
                        ->required()
                        ->placeholder('Contoh: Harga masih terlalu mahal, tolong cari supplier lain.'),
                ])
                ->visible(function () {
                    /** @var \App\Models\User $user */
                    $user = auth()->user();
                    return $this->record->status === 'Pending Finance' && ($user->hasRole('super_admin') || $user->can('approve_logistic::requisition'));
                })
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'Returned to Purchasing', // Status pantulan
                        'reject_note' => $data['reject_note'],
                    ]);
                    Notification::make()->title('Request dikembalikan ke Purchasing!')->warning()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            /* TOMBOL KEMBALI (ABU-ABU) */
            Actions\Action::make('cancel')
                ->label('Back')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
