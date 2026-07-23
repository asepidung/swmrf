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
        return __('STOCKS');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->isProgrammer() || auth()->user()->hasPermission('view_beef_stocks') || auth()->user()->hasPermission('view_beef_stock_movements') || auth()->user()->hasPermission('view_beef_stock_aging');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
