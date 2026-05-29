<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Materials extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    public static function getNavigationGroup(): ?string
    {
        return __('Master Data');
    }
}
