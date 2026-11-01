<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MeatProcessingStockResource\Pages;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stok produk kategori Processing. Tampilan, kolom gudang x grade, posisi tanggal, dan ekspornya sama dengan Beef Stock;
 * yang berbeda hanya cakupan produk, izin, dan menunya.
 */
class MeatProcessingStockResource extends BeefStockResource
{
    protected static ?string $cluster = \App\Filament\Clusters\MeatProcessing::class;

    protected static ?string $slug = 'meat-processing-stocks';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function getNavigationLabel(): string
    {
        return __('Processing Stock');
    }

    public static function getModelLabel(): string
    {
        return __('Processing Stock');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Processing Stock');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isProgrammer() || auth()->user()?->hasPermission('view_meat_processing_stocks');
    }

    protected static function cakupanProduk(Builder $produk): Builder
    {
        return $produk->whereHas('category', fn (Builder $kategori) => $kategori->where('name', ProductCategory::PROCESSING));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMeatProcessingStocks::route('/'),
            'view' => Pages\ViewMeatProcessingStock::route('/{record}'),
        ];
    }
}
