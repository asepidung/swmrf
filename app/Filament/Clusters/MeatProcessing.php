<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

/**
 * Menu sendiri untuk stok produk olahan (kategori Processing), dipisah dari Beef Stock atas permintaan Owner, 1 November
 * 2026. Hak aksesnya juga sendiri: izin Beef Stock TIDAK membuka menu ini.
 */
class MeatProcessing extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function getNavigationLabel(): string
    {
        return __('Meat Processing');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('STOCKS');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->isProgrammer() || $user?->hasPermission('view_meat_processing_stocks') || $user?->hasPermission('view_meat_processing_movements');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
