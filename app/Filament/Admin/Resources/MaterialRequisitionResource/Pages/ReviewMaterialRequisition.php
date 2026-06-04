<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use App\Models\User;

class ReviewMaterialRequisition extends EditRecord
{
    protected static string $resource = MaterialRequisitionResource::class;
    
    protected static ?string $title = 'Review Request';

    protected function beforeValidate(): void
    {
        $items = $this->data['items'] ?? [];
        foreach ($items as $key => $item) {
            if (empty($item['material_id'])) {
                unset($items[$key]);
            }
        }
        $this->data['items'] = $items;
    }

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
            ->label('Approve (Send to Finance)')
            ->color('success')
            ->icon('heroicon-s-check-circle')
            ->requiresConfirmation()
            ->action(function () {
                $this->save(false);
                
                $this->record->update([
                    'status' => 'Pending Finance',
                    'reject_note' => null,
                ]);

                $financeUsers = User::whereHas('roles.permissions', function ($query) {
                    $query->where('name', 'approve_material_requisitions');
                })->get();

                if ($financeUsers->isNotEmpty()) {
                    Notification::make()
                        ->title('Material Request Approved')
                        ->body("Request {$this->record->document_number} has been reviewed and requires Finance approval.")
                        ->success()
                        ->sendToDatabase($financeUsers);
                }

                $this->redirect($this->getResource()::getUrl('index'));
            });
    }

    protected function getRejectAction(): Actions\Action
    {
        return Actions\Action::make('reject')
            ->label('Reject / Return')
            ->color('danger')
            ->icon('heroicon-s-x-circle')
            ->requiresConfirmation()
            ->form([
                Textarea::make('reject_note')
                    ->label('Alasan Penolakan/Revisi')
                    ->required(),
            ])
            ->action(function (array $data) {
                $this->record->update([
                    'status' => 'Rejected',
                    'reject_note' => $data['reject_note'],
                ]);

                if ($this->record->user) {
                    Notification::make()
                        ->title('Material Request Rejected')
                        ->body("Your request {$this->record->document_number} was returned: {$data['reject_note']}")
                        ->danger()
                        ->sendToDatabase($this->record->user);
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
