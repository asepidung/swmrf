<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMaterialRequisition extends ViewRecord
{
    protected static string $resource = MaterialRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn() => route('print.material-request', ['id' => $this->record->id]))
                ->openUrlInNewTab(),

            Actions\Action::make('review')
                ->label('Review')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->visible(function () {
                    $user = auth()->user();
                    return in_array($this->record->status, ['Requested', 'Returned to Purchasing']) && ($user->isProgrammer() || $user->hasPermission('review_material_requisitions'));
                })
                ->url(fn() => $this->getResource()::getUrl('review', ['record' => $this->record])),

            Actions\Action::make('resubmit')
                ->label('Resubmit Request')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->status === 'Rejected' && $this->record->user_id === auth()->id())
                ->action(function () {
                    $this->record->update([
                        'status' => 'Requested',
                        'reject_note' => null,
                    ]);
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('finance_approval')
                ->label('Finance Approval')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->visible(function () {
                    $user = auth()->user();
                    return $this->record->status === 'Pending Finance' && ($user->isProgrammer() || $user->hasPermission('approve_material_requisitions'));
                })
                ->url(fn() => $this->getResource()::getUrl('finance-approve', ['record' => $this->record])),

            Actions\EditAction::make()
                ->visible(fn() => in_array($this->record->status, ['Requested', 'Returned to Purchasing'])),

            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->status === 'Requested'),

            Actions\Action::make('back')
                ->label('Back to List')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
