<?php

namespace App\Filament\Admin\Resources\PurchaseCattleResource\Pages;

use App\Filament\Admin\Resources\PurchaseCattleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseCattle extends CreateRecord
{
    protected static string $resource = PurchaseCattleResource::class;

    protected function mutateFormDataBeforeValidation(array $data): array
    {
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $key => $item) {
                // If the row has no cattle class and no qty/price, it's a ghost row, remove it!
                if (empty($item['cattle_class_id'])) {
                    unset($data['items'][$key]);
                }
            }
        }
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
