<?php

namespace App\Filament\Admin\Clusters;

use Filament\Clusters\Cluster;

class Beefs extends Cluster
{
    protected static ?string $navigationGroup = 'STOCKS';

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    public static function getNavigationLabel(): string
    {
        return __('Beefs');
    }
}
