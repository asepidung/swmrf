<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMaterialRequisition extends ViewRecord
{
    protected static string $resource = MaterialRequisitionResource::class;

    public function getTitle(): string
    {
        return 'Request: ' . $this->record->document_number;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->mapWithKeys(function ($item) {
            return [(string) \Illuminate\Support\Str::uuid() => [
                'material_id' => $item->material_id,
                'qty' => number_format($item->qty, 2, ',', '.'),
                'price' => number_format($item->price, 0, ',', '.'),
                'item_total' => number_format($item->qty * $item->price, 0, ',', '.'),
                'note' => $item->note,
            ]];
        })->toArray();
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->tooltip('Print')
                ->hiddenLabel()
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn() => route('print.material-request', ['id' => $this->record->id]))
                ->openUrlInNewTab(),

            Actions\Action::make('review')
                ->tooltip('Review')
                ->hiddenLabel()
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->visible(function () {
                    $user = auth()->user();
                    return in_array($this->record->status, ['Requested', 'Returned to Purchasing']) && ($user->isProgrammer() || $user->hasPermission('review_material_requisitions'));
                })
                ->url(fn() => $this->getResource()::getUrl('review', ['record' => $this->record])),

            Actions\Action::make('resubmit')
                ->tooltip('Resubmit Request')
                ->hiddenLabel()
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
                ->tooltip('Finance Approval')
                ->hiddenLabel()
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->visible(function () {
                    $user = auth()->user();
                    return $this->record->status === 'Pending Finance' && ($user->isProgrammer() || $user->hasPermission('approve_material_requisitions'));
                })
                ->url(fn() => $this->getResource()::getUrl('finance-approve', ['record' => $this->record])),

            Actions\EditAction::make()
                ->tooltip('Edit')
                ->hiddenLabel()
                ->visible(fn() => in_array($this->record->status, ['Requested', 'Returned to Purchasing'])),

            Actions\DeleteAction::make()
                ->tooltip('Delete')
                ->hiddenLabel()
                ->visible(fn() => $this->record->status === 'Requested'),

            Actions\Action::make('back')
                ->tooltip('Back to List')
                ->hiddenLabel()
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
