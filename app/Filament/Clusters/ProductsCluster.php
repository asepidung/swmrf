<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class ProductsCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    
    public static function getNavigationGroup(): ?string
    {
        return __('Master Data');
    }

    public static function getNavigationLabel(): string
    {
        return __('Products');
    }
}
