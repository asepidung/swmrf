<?php

namespace App\Filament\Admin\Resources\PurchaseCattleResource\Pages;

use App\Filament\Admin\Resources\PurchaseCattleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseCattle extends CreateRecord
{
    protected static string $resource = PurchaseCattleResource::class;

    protected function beforeValidate(): void
    {
        $items = $this->data['items'] ?? [];
        foreach ($items as $key => $item) {
            if (empty($item['cattle_class_id'])) {
                unset($items[$key]);
            }
        }
        $this->data['items'] = $items;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
