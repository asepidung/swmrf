<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\SubNavigationPosition;

class ProductsCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    // Sub-menu cluster wajib di atas, bukan di samping.
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    
    public static function getNavigationGroup(): ?string
    {
        return __('MASTER DATA');
    }

    public static function getNavigationLabel(): string
    {
        return __('Cattle Products');
    }
}
