<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

use Filament\Pages\SubNavigationPosition;

class CustomersCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    
    public static function getNavigationGroup(): ?string
    {
        return __('MASTER DATA');
    }

    public static function getNavigationLabel(): string
    {
        return __('Customers');
    }
}
