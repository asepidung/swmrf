<?php

namespace App\Filament\Resources\LogisticRequisitionResource\Pages;

use App\Filament\Resources\LogisticRequisitionResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ReviewLogisticRequisition extends ViewRecord
{
    protected static string $resource = LogisticRequisitionResource::class;

    /* Mengubah judul halaman agar berbeda dengan View biasa */
    protected static ?string $title = 'Review Logistic Request';

    /* Pengaturan Header Aksi untuk Halaman Proses Persetujuan */
    protected function getHeaderActions(): array
    {
        return [
            /* ================= TIM PURCHASING ================= */
            Actions\Action::make('accept')
                ->label('Accept')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(function () {
                    /** @var \App\Models\User $user */
                    $user = auth()->user();
                    // Muncul saat baru request ATAU saat dikembalikan Finance
                    return in_array($this->record->status, ['Requested', 'Returned to Purchasing']) && ($user->hasRole('super_admin') || $user->can('review_logistic::requisition'));
                })
                ->action(function () {
                    $this->record->update(['status' => 'Pending Finance', 'reject_note' => null]);
                    Notification::make()->title('Request accepted. Sent to Finance.')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make('reject_note')
                        ->label('Rejection Reason')
                        ->required()
                        ->placeholder('Contoh: Harga terlalu mahal atau supplier salah'),
                ])
                ->visible(function () {
                    /** @var \App\Models\User $user */
                    $user = auth()->user();
                    // Muncul saat baru request ATAU saat dikembalikan Finance
                    return in_array($this->record->status, ['Requested', 'Returned to Purchasing']) && ($user->hasRole('super_admin') || $user->can('review_logistic::requisition'));
                })
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'Rejected', // Tolak total, balik ke Requester
                        'reject_note' => $data['reject_note'],
                    ]);
                    Notification::make()->title('Request rejected')->danger()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            /* ================= TIM FINANCE ================= */
            Actions\Action::make('approve_finance')
                ->label('Approve & Generate PO')
                ->icon('heroicon-m-document-text')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Request & Buat PO?')
                ->visible(function () {
                    /** @var \App\Models\User $user */
                    $user = auth()->user();
                    return $this->record->status === 'Pending Finance' && ($user->hasRole('super_admin') || $user->can('approve_logistic::requisition'));
                })
                ->action(function () {
                    LogisticRequisitionResource::generatePurchaseOrder($this->record);
                    $this->record->update(['status' => 'PO Created']);
                    Notification::make()->title('PO Logistic Berhasil Diterbitkan!')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('reject_finance')
                ->label('Return to Purchasing')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make('reject_note')
                        ->label('Alasan Dikembalikan')
                        ->required()
                        ->placeholder('Contoh: Cari supplier lain yang lebih murah'),
                ])
                ->visible(function () {
                    /** @var \App\Models\User $user */
                    $user = auth()->user();
                    return $this->record->status === 'Pending Finance' && ($user->hasRole('super_admin') || $user->can('approve_logistic::requisition'));
                })
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'Returned to Purchasing', // Pantul ke Purchasing
                        'reject_note' => $data['reject_note'],
                    ]);
                    Notification::make()->title('Request dikembalikan ke Purchasing!')->warning()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            /* ================= UMUM ================= */
            Actions\Action::make('cancel')
                ->label('Back')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
