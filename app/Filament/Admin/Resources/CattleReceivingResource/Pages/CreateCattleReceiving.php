<?php

namespace App\Filament\Admin\Resources\CattleReceivingResource\Pages;

use App\Filament\Admin\Resources\CattleReceivingResource;
use App\Models\PurchaseCattle;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCattleReceiving extends CreateRecord
{
    protected static string $resource = CattleReceivingResource::class;

    public function mount(): void
    {
        $poId = request()->query('po_id');
        if (!$poId) {
            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        parent::mount();

        $po = PurchaseCattle::with(['items', 'supplier'])->find($poId);

        if ($po) {
            $generatedRows = [];
            foreach ($po->items as $poItem) {
                for ($i = 0; $i < $poItem->qty; $i++) {
                    $generatedRows[(string) Str::uuid()] = [
                        'cattle_class_id' => $poItem->cattle_class_id,
                        'eartag' => null,
                        'initial_weight' => null,
                        'notes' => null,
                    ];
                }
            }

            $this->form->fill([
                'purchase_cattle_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'po_number_display' => $po->document_number,
                'supplier_name_display' => $po->supplier->name,
                'receive_date' => now()->format('Y-m-d'),
                'items' => $generatedRows,
            ]);
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
