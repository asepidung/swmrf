<?php

namespace App\Filament\Admin\Resources\DeliveryOrderReceiptResource\Pages;

use App\Filament\Admin\Resources\DeliveryOrderReceiptResource;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryOrderReceipts extends ListRecords
{
    protected static string $resource = DeliveryOrderReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
