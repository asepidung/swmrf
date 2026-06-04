<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Models\User;

class ApproveFinanceMaterialRequisition extends EditRecord
{
    protected static string $resource = MaterialRequisitionResource::class;
    
    protected static ?string $title = 'Finance Approval';

    protected function getFormActions(): array
    {
        return [
            $this->getApproveAction(),
            $this->getRejectAction(),
        ];
    }

    protected function getApproveAction(): Actions\Action
    {
        return Actions\Action::make('approve')
            ->label('Approve & Generate PO')
            ->color('success')
            ->icon('heroicon-s-check-circle')
            ->requiresConfirmation()
            ->action(function () {
                $this->save(false); // Make sure to save any changes made by Finance, if any

                $this->record->update([
                    'status' => 'PO Created',
                    'reject_note' => null,
                ]);
                
                $this->record->generatePurchaseOrder();
                
                // Notify Purchasing
                $purchasingUsers = User::whereHas('roles.permissions', function ($query) {
                    $query->where('name', 'review_material_requisitions');
                })->get();

                if ($purchasingUsers->isNotEmpty()) {
                    Notification::make()
                        ->title('PO Generated')
                        ->body("Finance has approved request {$this->record->document_number} and generated a PO.")
                        ->success()
                        ->sendToDatabase($purchasingUsers);
                }

                $this->redirect($this->getResource()::getUrl('index'));
            });
    }

    protected function getRejectAction(): Actions\Action
    {
        return Actions\Action::make('reject')
            ->label('Return to Purchasing')
            ->color('danger')
            ->icon('heroicon-s-arrow-uturn-left')
            ->requiresConfirmation()
            ->form([
                Textarea::make('reject_note')
                    ->label('Alasan Pengembalian')
                    ->required(),
            ])
            ->action(function (array $data) {
                $this->record->update([
                    'status' => 'Returned to Purchasing',
                    'reject_note' => $data['reject_note'],
                ]);

                $purchasingUsers = User::whereHas('roles.permissions', function ($query) {
                    $query->where('name', 'review_material_requisitions');
                })->get();

                if ($purchasingUsers->isNotEmpty()) {
                    Notification::make()
                        ->title('Material Request Returned')
                        ->body("Finance returned request {$this->record->document_number}: {$data['reject_note']}")
                        ->danger()
                        ->sendToDatabase($purchasingUsers);
                }

                $this->redirect($this->getResource()::getUrl('index'));
            });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('Back')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
