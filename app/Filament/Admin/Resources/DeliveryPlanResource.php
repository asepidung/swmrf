<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DeliveryPlanResource\Pages;
use App\Models\DeliveryPlan;
use App\Models\SalesOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeliveryPlanResource extends Resource
{
    protected static ?string $model = DeliveryPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Plan Delivery';
    protected static ?string $pluralModelLabel = 'Plan Deliveries';

    public static function getModelLabel(): string
    {
        return __('Plan Delivery');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Plan Deliveries');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Order Information'))
                    ->compact()
                    ->schema([
                        Forms\Components\Placeholder::make('customer_name')
                            ->label(__('Customer'))
                            ->content(fn ($record) => $record?->customer?->name),
                        Forms\Components\Placeholder::make('delivery_date_formatted')
                            ->label(__('Tanggal Kirim'))
                            ->content(fn ($record) => $record?->delivery_date ? \Carbon\Carbon::parse($record->delivery_date)->format('d-m-Y') : '-'),
                    ])->columns(2),

                Forms\Components\Section::make(__('Trip Details'))
                    ->compact()
                    ->schema([
                        Forms\Components\TextInput::make('driver')
                            ->label(__('Driver'))
                            ->required()
                            ->maxLength(255)
                            ->autofocus(), // Ergonomic UI: autofocus on first editable field
                        Forms\Components\TextInput::make('armada')
                            ->label(__('Armada'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TimePicker::make('load_time')
                            ->label(__('Jam Loading'))
                            ->seconds(false)
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make(__('Associated Sales Orders'))
                    ->compact()
                    ->schema([
                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('col_so_number')->label(__('SO Number'))->columnSpan(4),
                                Forms\Components\Placeholder::make('col_weight')->label(__('Qty (Kg)'))->columnSpan(3),
                                Forms\Components\Placeholder::make('col_note')->label(__('Note'))->columnSpan(5),
                            ]),

                        Forms\Components\Repeater::make('salesOrders')
                            ->relationship('salesOrders')
                            ->hiddenLabel()
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement()
                            ->columns(12)
                            ->schema([
                                Forms\Components\TextInput::make('so_number')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(4),
                                Forms\Components\Placeholder::make('total_weight')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->content(fn ($record) => $record ? number_format($record->items()->sum('weight')) . ' Kg' : '-')
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('delivery_note')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->placeholder(__('Note'))
                                    ->maxLength(255)
                                    ->columnSpan(5),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sales_orders_count')
                    ->label(__('Total PO'))
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_qty')
                    ->label(__('Qty (Kg)'))
                    ->state(fn (DeliveryPlan $record) => number_format($record->total_qty))
                    ->alignRight(),
                Tables\Columns\TextColumn::make('driver')
                    ->label(__('Driver'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('armada')
                    ->label(__('Armada'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('load_time')
                    ->label(__('Jam Loading'))
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label(__('Notes'))
                    ->limit(40),
            ])
            ->recordUrl(
                fn (DeliveryPlan $record): ?string => Pages\EditDeliveryPlan::getUrl([$record->getKey()])
            )
            ->recordClasses(fn (DeliveryPlan $record) => match (true) {
                $record->trashed() => 'bg-danger-50 dark:bg-danger-900/20 border-danger-500',
                default => null,
            })
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_delivery_plans')),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('Customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('delivery_date')
                    ->form([
                        Forms\Components\DatePicker::make('delivery_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('delivery_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['delivery_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['delivery_until'] ?? null;

                        return $query
                            ->when(
                                $from,
                                fn(Builder $query, $date): Builder => $query->whereDate('delivery_date', '>=', $date),
                            )
                            ->when(
                                $until,
                                fn(Builder $query, $date): Builder => $query->whereDate('delivery_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['delivery_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['delivery_from'])->format('d M Y');
                        }
                        if ($data['delivery_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['delivery_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->headerActions([])
            ->actions([
                // Rows are clickable
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('delivery_date', 'desc');
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
            'index' => Pages\ListDeliveryPlans::route('/'),
            'edit' => Pages\EditDeliveryPlan::route('/{record}/edit'),
            'view' => Pages\ViewDeliveryPlan::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('salesOrders')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('DISTRIBUTION');
    }
}
