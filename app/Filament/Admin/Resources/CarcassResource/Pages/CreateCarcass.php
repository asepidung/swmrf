<?php

namespace App\Filament\Admin\Resources\CarcassResource\Pages;

use App\Filament\Admin\Resources\CarcassResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCarcass extends CreateRecord
{
    protected static string $resource = CarcassResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $weighingId = request()->query('weighing_id');
        
        if ($weighingId) {
            $weighing = \App\Models\CattleWeighing::with(['items' => function ($q) {
                $q->whereDoesntHave('carcassItems');
            }])->find($weighingId);

            if ($weighing) {
                $data['cattle_weighing_id'] = $weighing->id;
                
                $items = [];
                foreach ($weighing->items as $item) {
                    $items[] = [
                        'cattle_weighing_item_id' => $item->id,
                        'eartag' => $item->eartag,
                        'tail' => 0,
                    ];
                }
                $data['items'] = $items;
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
