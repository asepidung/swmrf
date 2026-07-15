<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BeefStockResource\Pages;
use App\Filament\Admin\Resources\BeefStockResource\RelationManagers;
use App\Models\Product;
use App\Models\BeefStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Columns\Summarizers\Sum;

use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Grouping\Group;

class BeefStockResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $cluster = \App\Filament\Clusters\BeefStocks::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function getNavigationLabel(): string
    {
        return __('Beef Stock');
    }

    public static function getModelLabel(): string
    {
        return __('Beef Stock');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Beef Stock');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->isProgrammer() || auth()->user()->hasPermission('view_beef_stocks');
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('products.*')
            ->addSelect([
                'chill_jonggol' => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('warehouse_id', 1)
                    ->where('grade_id', 1)
                    ->where('status', 'IN_STOCK'),
                'frozen_jonggol' => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('warehouse_id', 1)
                    ->where('grade_id', 2)
                    ->where('status', 'IN_STOCK'),
                'chill_perum' => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('warehouse_id', 2)
                    ->where('grade_id', 1)
                    ->where('status', 'IN_STOCK'),
                'frozen_perum' => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('warehouse_id', 2)
                    ->where('grade_id', 2)
                    ->where('status', 'IN_STOCK'),
                'total_qty' => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', 'IN_STOCK'),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Product Details'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('Code'))
                            ->disabled(),
                        Forms\Components\TextInput::make('name')
                            ->label(__('Name'))
                            ->disabled(),
                        Forms\Components\TextInput::make('category_name')
                            ->label(__('Category'))
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $record) {
                                if ($record) {
                                    $component->state($record->category?->name);
                                }
                            }),
                    ])->columns(3),
            ]);
    }

    protected static ?array $cachedCategorySums = null;

    public static function getCategorySums($livewire, $categoryName): array
    {
        if (self::$cachedCategorySums === null) {
            self::$cachedCategorySums = [];
            
            $query = $livewire->getFilteredTableQuery();
            $sumsQuery = (clone $query);
            
            $results = Product::query()
                ->fromSub($sumsQuery, 'sub')
                ->join('product_categories', 'sub.category_id', '=', 'product_categories.id')
                ->selectRaw('
                    product_categories.name as category_name,
                    COALESCE(SUM(sub.chill_jonggol), 0) as chill_jonggol,
                    COALESCE(SUM(sub.frozen_jonggol), 0) as frozen_jonggol,
                    COALESCE(SUM(sub.chill_perum), 0) as chill_perum,
                    COALESCE(SUM(sub.frozen_perum), 0) as frozen_perum,
                    COALESCE(SUM(sub.total_qty), 0) as total_qty
                ')
                ->groupBy('product_categories.name')
                ->get();

            foreach ($results as $row) {
                self::$cachedCategorySums[$row->category_name] = [
                    'chill_jonggol' => (float)$row->chill_jonggol,
                    'frozen_jonggol' => (float)$row->frozen_jonggol,
                    'chill_perum' => (float)$row->chill_perum,
                    'frozen_perum' => (float)$row->frozen_perum,
                    'total_qty' => (float)$row->total_qty,
                ];
            }
        }

        return self::$cachedCategorySums[$categoryName] ?? [
            'chill_jonggol' => 0.0,
            'frozen_jonggol' => 0.0,
            'chill_perum' => 0.0,
            'frozen_perum' => 0.0,
            'total_qty' => 0.0,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->view('filament.admin.resources.beef-stock.table')
            ->striped()
            ->paginated(false)
            ->defaultGroup(
                Group::make('category.name')
                    ->titlePrefixedWithLabel(false)
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Code'))
                    ->weight('bold')
                    ->alignCenter()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Product Name'))
                    ->weight('bold')
                    ->searchable(),

                ColumnGroup::make(__('G. Jonggol'), [
                    Tables\Columns\TextColumn::make('chill_jonggol')
                        ->label(__('CHILL'))
                        ->alignRight()
                        ->extraHeaderAttributes(['style' => 'text-align: center; justify-content: center;'])
                        ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
                        ->summarize(Sum::make()->label('')),

                    Tables\Columns\TextColumn::make('frozen_jonggol')
                        ->label(__('FROZEN'))
                        ->alignRight()
                        ->extraHeaderAttributes(['style' => 'text-align: center; justify-content: center;'])
                        ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
                        ->summarize(Sum::make()->label('')),
                ]),

                ColumnGroup::make(__('G. Perum'), [
                    Tables\Columns\TextColumn::make('chill_perum')
                        ->label(__('CHILL'))
                        ->alignRight()
                        ->extraHeaderAttributes(['style' => 'text-align: center; justify-content: center;'])
                        ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
                        ->summarize(Sum::make()->label('')),

                    Tables\Columns\TextColumn::make('frozen_perum')
                        ->label(__('FROZEN'))
                        ->alignRight()
                        ->extraHeaderAttributes(['style' => 'text-align: center; justify-content: center;'])
                        ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
                        ->summarize(Sum::make()->label('')),
                ]),

                Tables\Columns\TextColumn::make('total_qty')
                    ->label(__('Total'))
                    ->alignRight()
                    ->extraHeaderAttributes(['style' => 'text-align: center; justify-content: center;'])
                    ->weight('bold')
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
                    ->summarize(Sum::make()->label('')),
            ])
            ->headerActions([
                \Filament\Tables\Actions\ActionGroup::make([
                    \Filament\Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'Code', 'Product Name', 'CHILL (J)', 'FROZEN (J)', 'CHILL (P)', 'FROZEN (P)', 'Total'
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->code ?? '',
                                        $record->name ?? '',
                                        $record->chill_jonggol ?? '',
                                        $record->frozen_jonggol ?? '',
                                        $record->chill_perum ?? '',
                                        $record->frozen_perum ?? '',
                                        $record->total_qty ?? ''
                                    ]));
                                }
                                $writer->close();
                            }, 'excel.xlsx');
                        }),
                    \Filament\Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.beef-stocks-pdf', [
                                'records' => $records,
                                'title' => __('Beef Stock')
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export_beef_stocks.pdf');
                        }),
                ])
                ->label('Export Data')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->filters([
                Tables\Filters\Filter::make('hide_empty')
                    ->label(__('Hide Empty Stock'))
                    ->query(fn (Builder $query) => $query->whereHas('beefStocks', fn ($q) => $q->where('status', 'IN_STOCK'))),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('Category'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                // Clickable rows are enabled, no explicit view action button is needed
            ])
            ->recordUrl(fn (Product $record): string => Pages\ViewBeefStock::getUrl(['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BeefStocksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBeefStocks::route('/'),
            'view' => Pages\ViewBeefStock::route('/{record}'),
        ];
    }
}
