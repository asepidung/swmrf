<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SalesReturnResource\Pages;
use App\Models\SalesReturn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;

class SalesReturnResource extends Resource
{
    protected static ?string $model = SalesReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'WAREHOUSE';

    protected static ?string $navigationLabel = 'Sales Returns';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Retur')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label(__('Customer'))
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('delivery_order_id', null);
                            })
                            ->required(),

                        Forms\Components\Select::make('delivery_order_id')
                            ->label(__('Delivery Order'))
                            ->relationship('deliveryOrder', 'delivery_order_number', function (Builder $query, callable $get) {
                                $customerId = $get('customer_id');
                                if ($customerId) {
                                    $query->where('customer_id', $customerId);
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih DO atau kosongkan untuk Unidentified'),

                        Forms\Components\DatePicker::make('return_date')
                            ->label(__('Return Date'))
                            ->default(now())
                            ->required(),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(3),
                Forms\Components\View::make('filament.resources.sales-return-resource.summary')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('return_number')
                    ->label(__('Return No.'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('return_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('deliveryOrder.delivery_order_number')
                    ->label(__('DO No.'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'warning',
                        'Approved' => 'success',
                        'Canceled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')->label('Dari Tanggal')->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('date_to')->label('Sampai Tanggal')->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('return_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('return_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        return [];
                    }),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('Customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn (SalesReturn $record) => $record->trashed() ? 'border-s-2 border-red-600 bg-red-50' : null)
            ->recordUrl(fn (SalesReturn $record) => $record->trashed() ? null : static::getUrl('edit', ['record' => $record]));
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
            'index' => Pages\ListSalesReturns::route('/'),
            'create' => Pages\CreateSalesReturn::route('/create'),
            'edit' => Pages\EditSalesReturn::route('/{record}/edit'),
            'input-items' => Pages\InputReturnItems::route('/{record}/input-items'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
