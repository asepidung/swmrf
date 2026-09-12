<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\SubNavigationPosition;

class FleetCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    
    public static function getNavigationLabel(): string
    {
        return __('Fleet & Driver');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('MASTER DATA');
    }
}
