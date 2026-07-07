<?php

namespace App\Filament\Admin\Resources\BeefStockAgingResource\Pages;

use App\Filament\Admin\Resources\BeefStockAgingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBeefStockAgings extends ListRecords
{
    protected static string $resource = BeefStockAgingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
