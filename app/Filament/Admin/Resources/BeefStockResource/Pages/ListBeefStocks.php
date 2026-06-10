<?php

namespace App\Filament\Admin\Resources\BeefStockResource\Pages;

use App\Filament\Admin\Resources\BeefStockResource;
use Filament\Resources\Pages\ListRecords;

class ListBeefStocks extends ListRecords
{
    protected static string $resource = BeefStockResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
