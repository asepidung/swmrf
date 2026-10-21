<?php

namespace App\Filament\Admin\Resources\CustomerProductSpecResource\Pages;

use App\Filament\Admin\Resources\CustomerProductSpecResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerSpecs extends ViewRecord
{
    protected static string $resource = CustomerProductSpecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
