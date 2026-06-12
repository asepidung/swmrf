<?php

namespace App\Filament\Admin\Resources\PriceListResource\Pages;

use App\Filament\Admin\Resources\PriceListResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPriceList extends EditRecord
{
    protected static string $resource = PriceListResource::class;

    public function getTitle(): string
    {
        return ($this->getRecord()->priceList && $this->getRecord()->priceList->items()->exists())
            ? __('Edit Price List')
            : __('Create Price List');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }

    protected function getFormActions(): array
    {
        // Override untuk menghilangkan cancel button di bawah
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
