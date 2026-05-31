<?php

namespace App\Filament\Admin\Resources\CattleWeighingResource\Pages;

use App\Filament\Admin\Resources\CattleWeighingResource;
use App\Models\CattleReceiving;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateCattleWeighing extends CreateRecord
{
    protected static string $resource = CattleWeighingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $data = $this->form->getState();
        $receiving = CattleReceiving::with('purchaseCattle.items')->find($record->cattle_receiving_id);
        
        $totalLoss = 0;
        
        if ($receiving && $receiving->purchaseCattle) {
            $poItems = $receiving->purchaseCattle->items->keyBy('cattle_class_id');
            
            foreach ($record->items as $itemData) {
                $initial = floatval($itemData->initial_weight ?? 0);
                $actual = floatval($itemData->actual_weight ?? 0);
                
                if ($actual < $initial) {
                    $lossWeight = $initial - $actual;
                    $classId = $itemData->cattle_class_id ?? null;
                    $price = 0;
                    
                    if ($classId && isset($poItems[$classId])) {
                        $price = $poItems[$classId]->price;
                    }
                    
                    $totalLoss += ($lossWeight * $price);
                }
            }
        }
        
        if ($totalLoss > 0) {
            $record->financialLoss()->create([
                'date' => $record->weighing_date,
                'transaction_type' => 'Cattle Weighing',
                'reference_number' => $record->weighing_number,
                'amount' => $totalLoss,
                'note' => 'Susut Timbang Ulang Sapi',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}
