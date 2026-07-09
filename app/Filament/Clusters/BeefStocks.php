<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class BeefStocks extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    public static function getNavigationLabel(): string
    {
        return __('Beef');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'STOCKS';
    }
}
