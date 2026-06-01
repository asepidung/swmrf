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

    protected function beforeCreate(): void
    {
        if (isset($this->data['items']) && is_array($this->data['items'])) {
            $filteredItems = [];
            foreach ($this->data['items'] as $key => $item) {
                $c1 = (float) ($item['carcass_1'] ?? 0);
                $c2 = (float) ($item['carcass_2'] ?? 0);
                $h = (float) ($item['hides'] ?? 0);

                if ($c1 > 0 || $c2 > 0 || $h > 0) {
                    $filteredItems[$key] = $item;
                }
            }
            // Update the form's raw state so the Repeater doesn't save the removed items
            $this->data['items'] = $filteredItems;
            $this->form->fill($this->data);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
