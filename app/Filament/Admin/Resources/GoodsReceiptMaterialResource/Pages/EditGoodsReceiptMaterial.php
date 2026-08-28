<?php

namespace App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoodsReceiptMaterial extends EditRecord
{
    protected static string $resource = GoodsReceiptMaterialResource::class;

    public ?string $poStatusAction = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label(__('Print'))
                ->color('warning')
                ->icon('heroicon-o-printer')
                ->url(fn ($record): string => route('goods-receipt-material.print', $record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make()
                ->hidden(fn ($record) => $record->items()->exists())
                ->before(function (Actions\DeleteAction $action) {
                    $payable = $this->getRecord()->payable;
                    if ($payable && in_array($payable->status, ['partial', 'paid'])) {
                        \Filament\Notifications\Notification::make()
                            ->title(__('This Goods Receipt cannot be deleted because its payment status is already partial or paid.'))
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }

    public function mount($record): void
    {
        parent::mount($record);

        $payable = $this->getRecord()->payable;
        if ($payable && in_array($payable->status, ['partial', 'paid'])) {
            \Filament\Notifications\Notification::make()
                ->title(__('This Goods Receipt cannot be edited because its payment status is already partial or paid.'))
                ->danger()
                ->send();
            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->poStatusAction = $data['po_status_action'] ?? 'partial';
        unset($data['po_status_action']);

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $po = $record->purchaseMaterial;

        if ($po) {
            // Calculate total PO qty
            $totalPoQty = \App\Models\PurchaseMaterialItem::where('purchase_material_id', $po->id)->sum('qty');

            // Calculate total received from ALL GRs of this PO
            $totalReceived = \App\Models\GoodsReceiptMaterialItem::whereHas('goodsReceiptMaterial', function ($query) use ($po) {
                $query->where('purchase_material_id', $po->id);
            })->sum('qty_received');

            if ($totalReceived >= $totalPoQty) {
                $po->update(['status' => 'completed']);
            } else {
                $status = $this->poStatusAction ?? 'partial';
                $po->update(['status' => $status]);
            }
        }

        // Payable will be generated/updated when the GR is locked
    }
}
