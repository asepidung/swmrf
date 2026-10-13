<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\SubNavigationPosition;

/**
 * Pengeluaran dan kategorinya satu kelompok menu (keputusan Ayah, 13 Oktober 2026),
 * seperti Customers, Fleet, dan Products -- bukan dua menu terpisah di grup FINANCE.
 */
class ExpensesCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 20;

    // Sub-menu cluster di atas, bukan di samping.
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationLabel(): string
    {
        return __('Expenses');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('FINANCE');
    }
}
