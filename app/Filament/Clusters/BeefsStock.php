<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class BeefsStock extends Cluster
{
    protected static ?string $navigationGroup = 'STOCKS';

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    public static function getNavigationLabel(): string
    {
        return __('Beefs');
    }
}
