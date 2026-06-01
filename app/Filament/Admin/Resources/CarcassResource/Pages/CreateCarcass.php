<?php

namespace App\Filament\Admin\Resources\CarcassResource\Pages;

use App\Filament\Admin\Resources\CarcassResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCarcass extends CreateRecord
{
    protected static string $resource = CarcassResource::class;



    protected function getCreateAnotherFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['items']) && is_array($data['items'])) {
            $filteredItems = [];
            foreach ($data['items'] as $key => $item) {
                $c1 = (float) ($item['carcass_1'] ?? 0);
                $c2 = (float) ($item['carcass_2'] ?? 0);
                $h = (float) ($item['hides'] ?? 0);

                if ($c1 > 0 || $c2 > 0 || $h > 0) {
                    $filteredItems[$key] = $item;
                }
            }
            $data['items'] = $filteredItems;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
