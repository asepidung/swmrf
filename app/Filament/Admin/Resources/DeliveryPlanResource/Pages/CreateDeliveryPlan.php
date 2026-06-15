<?php

namespace App\Filament\Admin\Resources\DeliveryPlanResource\Pages;

use App\Filament\Admin\Resources\DeliveryPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliveryPlan extends CreateRecord
{
    protected static string $resource = DeliveryPlanResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
