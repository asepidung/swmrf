<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CustomerProductSpecResource\Pages;
use App\Filament\Admin\Resources\CustomerProductSpecResource\RelationManagers\SpecsRelationManager;
use App\Models\Customer;
use App\Models\CustomerProductSpec;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Spek tetap customer per produk (#579), dilihat PER CUSTOMER: daftar hanya memuat customer
 * yang sudah punya spek; membuka satu customer menampilkan semua produk beserta spek-nya
 * (dan di situ Excel-nya). Spek lahir dari baris Sales Order ("Simpan sebagai spek tetap");
 * halaman ini untuk melihat, mengubah, dan menghapus. Lihat {@see CustomerProductSpec}.
 */
class CustomerProductSpecResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'customer-specs';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('QC');
    }

    public static function getModelLabel(): string
    {
        return __('Customer Spec');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Customer Specs');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('productSpecs')
            ->withCount('productSpecs')
            ->withMax('productSpecs', 'updated_at');
    }

    // Izin mengikuti CustomerProductSpecPolicy (lihat / kelola spek customer), bukan izin Customer.
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', CustomerProductSpec::class) ?? false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()
                ->columns(['default' => 1, 'lg' => 3])
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label(__('Customer')),
                    Infolists\Components\TextEntry::make('group.name')->label(__('Customer Group'))->placeholder('-'),
                    Infolists\Components\TextEntry::make('segment.name')->label(__('Customer Segment'))->placeholder('-'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('group.name')
                    ->label(__('Customer Group'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('product_specs_count')
                    ->label(__('Products with spec'))
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_specs_max_updated_at')
                    ->label(__('Last updated'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordUrl(fn (Customer $record): string => Pages\ViewCustomerSpecs::getUrl([$record->getKey()]));
    }

    public static function getRelations(): array
    {
        return [
            SpecsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerProductSpecs::route('/'),
            'view' => Pages\ViewCustomerSpecs::route('/{record}'),
        ];
    }
}
