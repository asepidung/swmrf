<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class ProductsCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    
    protected static ?string $navigationGroup = 'Master Data';

    public static function getNavigationLabel(): string
    {
        return __('Products');
    }
}
