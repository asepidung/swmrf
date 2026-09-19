<?php

namespace App\Filament\Admin\Resources\SalesReturnPlanResource\Pages;

use App\Filament\Admin\Resources\SalesReturnPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesReturnPlan extends CreateRecord
{
    protected static string $resource = SalesReturnPlanResource::class;

    /** Sales lanjut langsung ke halaman item -- itu satu-satunya alasan membuat plan. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('items', ['record' => $this->getRecord()]);
    }
}
