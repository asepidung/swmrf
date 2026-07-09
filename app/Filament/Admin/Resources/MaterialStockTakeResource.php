<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialStockTakeResource\Pages;
use App\Filament\Admin\Resources\MaterialStockTakeResource\RelationManagers;
use App\Models\MaterialStockTake;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;

class MaterialStockTakeResource extends Resource
{
    protected static ?string $model = MaterialStockTake::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getModelLabel(): string
    {
        return __('Opname Material');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Opname Material');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'STOCKS';
    }

    public static function getNavigationLabel(): string
    {
        return __('Opname Material');
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
                            ->hiddenOn('create')
                            ->required(),
                        Forms\Components\TextInput::make('period')
                            ->label(__('Periode (Bulan/Tahun)'))
                            ->type('month')
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
                Tables\Columns\TextColumn::make('period')
                    ->label(__('Periode'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label(__('Date'))
                    ->date('d-M-Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'IN_PROGRESS' => 'warning',
                        'REVIEW' => 'info',
                        'COMPLETED' => 'success',
                        'CANCELED' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('d-M-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (MaterialStockTake $record): string => static::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (MaterialStockTake $record) => match (true) {
                $record->trashed() => 'border-s-2 border-red-600 dark:border-red-500 bg-red-50 dark:bg-red-500/10',
                default => null,
            })
            ->filters([
                Tables\Filters\TrashedFilter::make()->visible(fn () => auth()->user()->can('view_deleted_material_stock_takes')),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label(__('Start Date')),
                        Forms\Components\DatePicker::make('created_until')->label(__('End Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
    ])
    ->actions([
        Tables\Actions\Action::make('input_stock')
            ->label(__('Input Stock'))
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->button()
            ->url(fn (MaterialStockTake $record): string => static::getUrl('items', ['record' => $record]))
            ->visible(fn (MaterialStockTake $record) => in_array($record->status, ['DRAFT', 'IN_PROGRESS'])),

        Tables\Actions\DeleteAction::make()
            ->visible(fn (MaterialStockTake $record) => in_array($record->status, ['DRAFT', 'IN_PROGRESS'])),
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
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialStockTakes::route('/'),
            'create' => Pages\CreateMaterialStockTake::route('/create'),
            'view' => Pages\ViewMaterialStockTake::route('/{record}'),
            'edit' => Pages\EditMaterialStockTake::route('/{record}/edit'),
            'items' => Pages\ManageMaterialStockTakeItems::route('/{record}/items'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (auth()->check() && auth()->user()->can('view_deleted_material_stock_takes')) {
            $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
        }

        return $query;
    }
}
