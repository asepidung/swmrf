<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\StockTakeResource\Pages;
use App\Filament\Admin\Resources\StockTakeResource\RelationManagers;
use App\Models\StockTake;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use App\Models\Warehouse;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Illuminate\Support\Carbon;

class StockTakeResource extends Resource
{
    protected static ?string $model = StockTake::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Warehouse';

    public static function getModelLabel(): string
    {
        return __('Stock Opname');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Stock Opnames');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Stock Opname Info'))
                    ->schema([
                        Forms\Components\TextInput::make('document_number')
                            ->label(__('Document Number'))
                            ->default('AUTO')
                            ->disabled()
                            ->dehydrated(false)
                            ->required(),
                        Forms\Components\Select::make('warehouse_id')
                            ->label(__('Warehouse'))
                            ->options(Warehouse::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->autofocus(),
                        Forms\Components\DatePicker::make('date')
                            ->label(__('Date'))
                            ->default(now())
                            ->required(),
                        Forms\Components\Textarea::make('summary_note')
                            ->label(__('Note'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label(__('Doc No'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label(__('Date'))
                    ->date('d-M-Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label(__('Warehouse'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'IN_PROGRESS' => 'warning',
                        'COMPLETED' => 'success',
                        'CANCELED' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('d-M-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (StockTake $record): string => static::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (StockTake $record) => match (true) {
                $record->trashed() => 'bg-danger-100/50 dark:bg-danger-900/50 border-l-4 border-danger-500',
                default => null,
            })
            ->filters([
                DateRangeFilter::make('created_at')
                    ->label(__('Date Range'))
                    ->defaultToday()
                    ->alwaysShowCalendar(false),
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->can('view_deleted_stock_takes')),
            ])
            ->actions([
                Tables\Actions\Action::make('scan')
                    ->label(__('Scan'))
                    ->icon('heroicon-o-qr-code')
                    ->iconButton()
                    ->url(fn (StockTake $record) => static::getUrl('scan', ['record' => $record]))
                    ->visible(fn (StockTake $record) => $record->status === 'IN_PROGRESS'),
                Tables\Actions\ViewAction::make()->iconButton(),
                Tables\Actions\EditAction::make()->iconButton()
                    ->visible(fn (StockTake $record) => $record->status === 'DRAFT' || $record->status === 'IN_PROGRESS'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListStockTakes::route('/'),
            'create' => Pages\CreateStockTake::route('/create'),
            'view' => Pages\ViewStockTake::route('/{record}'),
            'edit' => Pages\EditStockTake::route('/{record}/edit'),
            'scan' => Pages\ScanStockTake::route('/{record}/scan'),
        ];
    }
}
