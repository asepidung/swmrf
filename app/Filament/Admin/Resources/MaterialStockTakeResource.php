<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialStockTakeResource\Pages;
use App\Filament\Admin\Resources\MaterialStockTakeResource\RelationManagers;
use App\Models\MaterialStockTake;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
        return __('STOCKS');
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
                            ->label(__('Period (month/year)'))
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
                    ->label(__('Period'))
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
                        MaterialStockTake::STATUS_DRAFT => 'gray',
                        MaterialStockTake::STATUS_IN_PROGRESS => 'warning',
                        MaterialStockTake::STATUS_REVIEW => 'info',
                        MaterialStockTake::STATUS_COMPLETED => 'success',
                        MaterialStockTake::STATUS_CANCELED => 'danger',
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
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()?->hasPermission('view_deleted_material_stock_takes') ?? false),
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
            ->visible(fn (MaterialStockTake $record): bool => $record->isCountable()),

        // Hanya selama belum ada satu pun hitungan yang diisi. Sebelumnya
        // yang diperiksa cuma statusnya, jadi opname yang sudah dihitung
        // separuh bisa dibuang begitu saja.
        Tables\Actions\DeleteAction::make()
            ->visible(fn (MaterialStockTake $record): bool => $record->isDeletable()),
    ])
    ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Hapus massal ikut dijaga aturan yang sama dengan hapus
                    // satuan. Sebelumnya ia tidak menjaga apa pun.
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Support\Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $tertahan = $records->reject(fn (MaterialStockTake $record): bool => $record->isDeletable());

                            if ($tertahan->isEmpty()) {
                                return;
                            }

                            \Filament\Notifications\Notification::make()
                                ->title(__('Some documents cannot be deleted'))
                                ->body(__('A stock count that already has counted items cannot be deleted: :documents', [
                                    'documents' => $tertahan->pluck('document_number')->join(', '),
                                ]))
                                ->danger()
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }),
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
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_material_stock_takes',
        );
    }
}
