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

    public static function canViewAny(): bool
    {
        return auth()->user()->isProgrammer() || auth()->user()->hasPermission('view_material_stocks') || auth()->user()->hasPermission('view_material_stock_movements');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
