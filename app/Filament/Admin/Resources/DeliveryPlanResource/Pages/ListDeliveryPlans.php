<?php

namespace App\Filament\Admin\Resources\DeliveryPlanResource\Pages;

use App\Filament\Admin\Resources\DeliveryPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryPlans extends ListRecords
{
    protected static string $resource = DeliveryPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('detail')
                ->label(__('Detail'))
                ->color('info')
                ->url(fn (): string => $this->getResource()::getUrl('detail-list')),
            Actions\CreateAction::make()->label(__('Create')),
        ];
    }
}
