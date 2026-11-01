<?php

namespace App\Filament\Admin\Resources\MeatProcessingStockResource\Pages;

use App\Filament\Admin\Resources\BeefStockResource\Pages\ListBeefStocks;
use App\Filament\Admin\Resources\MeatProcessingStockResource;

class ListMeatProcessingStocks extends ListBeefStocks
{
    protected static string $resource = MeatProcessingStockResource::class;
}
