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
                ->url('#')
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
