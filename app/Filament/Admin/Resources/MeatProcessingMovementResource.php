<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MeatProcessingMovementResource\Pages;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;

/** Pergerakan stok produk kategori Processing; lihat MeatProcessingStockResource. */
class MeatProcessingMovementResource extends BeefStockMovementResource
{
    protected static ?string $cluster = \App\Filament\Clusters\MeatProcessing::class;

    protected static ?string $slug = 'meat-processing-movements';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('view_meat_processing_movements') ?? false);
    }

    /** Tanpa ini, membuka satu baris jatuh ke BeefStockMovementPolicy yang meminta izin Beef Stock. */
    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    protected static function cakupanProduk(Builder $produk): Builder
    {
        return $produk->whereHas('category', fn (Builder $kategori) => $kategori->where('name', ProductCategory::PROCESSING));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMeatProcessingMovements::route('/'),
            'view' => Pages\ViewMeatProcessingMovement::route('/{record}'),
        ];
    }
}
