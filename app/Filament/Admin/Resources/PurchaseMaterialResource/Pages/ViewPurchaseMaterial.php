<?php

namespace App\Filament\Admin\Resources\PurchaseMaterialResource\Pages;

use App\Filament\Admin\Resources\PurchaseMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseMaterial extends ViewRecord
{
    protected static string $resource = PurchaseMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Print PO')
                ->visible(fn () => auth()->user()->hasPermission('print_purchase_materials'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn() => route('print.po-material', ['id' => $this->record->id]))
                ->openUrlInNewTab(),
                
            Actions\DeleteAction::make()
                ->tooltip('Delete PO')
                ->icon('heroicon-o-trash')
                ->visible(fn() => $this->record->goodsReceipts()->count() === 0)
                ->action(function () {
                    if ($this->record->materialRequisition) {
                        $this->record->materialRequisition->update([
                            'status' => 'Pending Finance'
                        ]);
                    }
                    $this->record->delete();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('back')
                ->label('Back to List')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
