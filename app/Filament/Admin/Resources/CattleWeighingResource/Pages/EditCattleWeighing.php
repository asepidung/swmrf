<?php

namespace App\Filament\Admin\Resources\CattleWeighingResource\Pages;

use App\Filament\Admin\Resources\CattleWeighingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCattleWeighing extends EditRecord
{
    protected static string $resource = CattleWeighingResource::class;

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
                ->url(fn ($record): string => route('cattle-weighing.print', $record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
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

    protected function afterSave(): void
    {
        $record = $this->record;
        $record->refresh();
        $receiving = \App\Models\CattleReceiving::with('purchaseCattle.items')->find($record->cattle_receiving_id);
        
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
            $record->financialLoss()->updateOrCreate(
                ['transaction_type' => 'Cattle Weighing', 'reference_number' => $record->weighing_number],
                ['date' => $record->weighing_date, 'amount' => $totalLoss, 'note' => 'Susut Timbang Ulang Sapi']
            );
        } else {
            $record->financialLoss()->delete();
        }
    }
}
