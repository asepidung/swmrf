<?php

namespace App\Filament\Admin\Resources\CattleReceivingResource\Pages;

use App\Filament\Admin\Resources\CattleReceivingResource;
use App\Models\PurchaseCattle;
use App\Filament\Admin\Resources\CattleReceivingResource\Concerns\SavesUniqueEartags;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateCattleReceiving extends CreateRecord
{
    use SavesUniqueEartags;

    protected static string $resource = CattleReceivingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveGuardingEartags(fn (): Model => parent::handleRecordCreation($data));
    }

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
            $this->form->fill([
                'purchase_cattle_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'po_number_display' => $po->document_number,
                'supplier_name_display' => $po->supplier->name,
                'receive_date' => now()->format('Y-m-d'),
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
