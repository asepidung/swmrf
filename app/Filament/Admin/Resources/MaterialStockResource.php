<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialStockResource\Pages;
use App\Models\MaterialStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;

class MaterialStockResource extends Resource
{
    protected static ?string $model = MaterialStock::class;

    protected static ?string $cluster = \App\Filament\Admin\Clusters\Materials::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function getNavigationLabel(): string
    {
        return __('Material Stock');
    }

    public static function getModelLabel(): string
    {
        return __('Material Stock');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Material Stocks');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Material Stock Details'))
                    ->schema([
                        Forms\Components\TextInput::make('material.code')
                            ->label(__('Item Code'))
                            ->disabled(),
                        Forms\Components\TextInput::make('material.name')
                            ->label(__('Item Name'))
                            ->disabled(),
                        Forms\Components\TextInput::make('material.category.name')
                            ->label(__('Category'))
                            ->disabled(),
                        Forms\Components\TextInput::make('material.unit.name')
                            ->label(__('Unit'))
                            ->disabled(),
                        Forms\Components\TextInput::make('qty')
                            ->label(__('Stok Aktual'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.')),
                        Forms\Components\TextInput::make('material.min_stock')
                            ->label(__('Min. Stock'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.')),
                    ])->columns(3)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('material.code')
                    ->label(__('Item Code'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Item Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.category.name')
                    ->label(__('Category'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.unit.name')
                    ->label(__('Unit'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label(__('Stok Aktual'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable()
                    ->color(fn (MaterialStock $record) => $record->qty < ($record->material->min_stock ?? 0) ? 'danger' : 'success')
                    ->weight(fn (MaterialStock $record) => $record->qty < ($record->material->min_stock ?? 0) ? 'bold' : null),
                Tables\Columns\TextColumn::make('material.min_stock')
                    ->label(__('Min. Stock'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable(),
            ])
            ->headerActions([
                \Filament\Tables\Actions\ActionGroup::make([
                    \Filament\Tables\Actions\ExportAction::make('excel')
                        ->label('Excel')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->exporter(\App\Filament\Exports\MaterialStockExporter::class)
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    \Filament\Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.material-stocks-pdf', [
                                'records' => $records,
                                'title' => __('Material Stocks')
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export_material_stocks.pdf');
                        }),
                ])
                ->label('Export Data')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('material_category_id')
                    ->relationship('material.category', 'name')
                    ->label(__('Category')),
                Tables\Filters\Filter::make('below_min_stock')
                    ->label(__('Below Min. Stock'))
                    ->query(fn (Builder $query) => $query->whereHas('material', function ($q) {
                        $q->whereColumn('material_stocks.qty', '<', 'materials.min_stock');
                    })),
            ])
            ->actions([
                // Read-only, clickable row handles navigation
            ])
            ->bulkActions([
                // Read-only stock levels
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->defaultSort('id', 'desc');
    }

    public static function getRecordUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return null;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialStocks::route('/'),
            'view' => Pages\ViewMaterialStock::route('/{record}'),
        ];
    }
}
