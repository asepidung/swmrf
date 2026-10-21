<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerProductSpecResource\Pages;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerProductSpecResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerProductSpec extends EditRecord
{
    protected static string $resource = CustomerProductSpecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            Actions\DeleteAction::make(),
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
}
