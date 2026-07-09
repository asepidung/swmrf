<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class MaterialsStock extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    public static function getNavigationLabel(): string
    {
        return __('Materials');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('STOCKS');
    }
}
