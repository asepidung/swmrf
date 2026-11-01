<?php

namespace App\Filament\Admin\Resources\MeatProcessingStockResource\Pages;

use App\Filament\Admin\Resources\BeefStockResource\Pages\ViewBeefStock;
use App\Filament\Admin\Resources\MeatProcessingStockResource;

class ViewMeatProcessingStock extends ViewBeefStock
{
    protected static string $resource = MeatProcessingStockResource::class;
}
