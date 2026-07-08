<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DeliveryOrderResource\Pages;
use App\Models\DeliveryOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\RawJs;
use App\Models\Product;

class DeliveryOrderResource extends Resource
{
    protected static ?string $model = DeliveryOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationGroup(): ?string
    {
        return 'DISTRIBUTION';
    }

    public static function getNavigationLabel(): string
    {
        return 'Delivery Order';
    }

    public static function getModelLabel(): string
    {
        return 'Delivery Order';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Delivery Orders';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Delivery Order Header'))
                    ->schema([
                        Forms\Components\Hidden::make('tally_id')
                            ->default(fn () => request()->query('tally_id')),
                        
                        Forms\Components\Select::make('customer_id')
                            ->label(__('Customer'))
                            ->relationship('customer', 'name')
                            ->disabled() // readonly
                            ->dehydrated(true)
                            ->required()
                            ->default(function () {
                                $tallyId = request()->query('tally_id');
                                return $tallyId ? \App\Models\Tally::find($tallyId)?->salesOrder?->customer_id : null;
                            }),
                        
                        Forms\Components\TextInput::make('customer_address')
                            ->label(__('Address'))
                            ->formatStateUsing(fn ($record, callable $get) => $record?->customer?->address ?? \App\Models\Customer::find($get('customer_id'))?->address)
                            ->disabled()
                            ->dehydrated(false)
                            ->default(function () {
                                $tallyId = request()->query('tally_id');
                                return $tallyId ? \App\Models\Tally::find($tallyId)?->salesOrder?->customer?->address : null;
                            }),
                        
                        Forms\Components\Select::make('sales_order_id')
                            ->label(__('SO Number'))
                            ->relationship('salesOrder', 'so_number')
                            ->disabled()
                            ->dehydrated(true)
                            ->required()
                            ->default(function () {
                                $tallyId = request()->query('tally_id');
                                return $tallyId ? \App\Models\Tally::find($tallyId)?->sales_order_id : null;
                            }),

                        Forms\Components\DatePicker::make('delivery_date')
                            ->label(__('Delivery Date'))
                            ->required()
                            ->default(function () {
                                $tallyId = request()->query('tally_id');
                                return $tallyId ? \App\Models\Tally::find($tallyId)?->salesOrder?->delivery_date : null;
                            }),

                        Forms\Components\TextInput::make('po_number')
                            ->label(__('Cust PO'))
                            ->default(function () {
                                $tallyId = request()->query('tally_id');
                                return $tallyId ? \App\Models\Tally::find($tallyId)?->salesOrder?->po_number : null;
                            }),

                        Forms\Components\TextInput::make('seal_number')
                            ->label(__('Seal Numb'))
                            ->default(function () {
                                $tallyId = request()->query('tally_id');
                                return $tallyId ? \App\Models\Tally::find($tallyId)?->seal_number : null;
                            }),

                        Forms\Components\TextInput::make('driver')
                            ->label(__('Driver'))
                            ->autofocus()
                            ->required(),

                        Forms\Components\TextInput::make('police_number')
                            ->label(__('Police Number')),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make(__('Products List'))
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->default(function () {
                                $tallyId = request()->query('tally_id');
                                if ($tallyId) {
                                    $tally = \App\Models\Tally::with('items')->find($tallyId);
                                    if ($tally) {
                                        $aggregated = [];
                                        foreach ($tally->items as $item) {
                                            if (!isset($aggregated[$item->product_id])) {
                                                $aggregated[$item->product_id] = [
                                                    'product_id' => $item->product_id,
                                                    'box' => 0,
                                                    'weight' => 0,
                                                ];
                                            }
                                            $aggregated[$item->product_id]['box'] += 1;
                                            $aggregated[$item->product_id]['weight'] += (float)$item->weight;
                                        }
                                        return array_values($aggregated);
                                    }
                                }
                                return [];
                            })
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label(__('Product'))
                                    ->relationship('product', 'name')
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('box')
                                    ->label(__('Box'))
                                    ->numeric()
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-center font-bold', 'style' => 'text-align: center;'])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('weight')
                                    ->label(__('Weight'))
                                    ->numeric()
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-right font-bold', 'style' => 'text-align: right;'])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('received_weight')
                                    ->label(__('Received Weight'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn ($record) => $record && $record->deliveryOrder?->status === 'Approved')
                                    ->formatStateUsing(function ($record) {
                                        if (!$record) return null;
                                        $receipt = \App\Models\DeliveryOrderReceipt::where('delivery_order_id', $record->delivery_order_id)->first();
                                        if (!$receipt) return null;
                                        $receiptItem = $receipt->items()->where('product_id', $record->product_id)->first();
                                        return $receiptItem?->weight;
                                    })
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('notes')
                                    ->label(__('Notes'))
                                    ->columnSpan(fn ($record) => $record && $record->deliveryOrder?->status === 'Approved' ? 2 : 4),
                            ])
                            ->columns(12)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement()
                            ->hiddenLabel()
                            ->live(true),

                        Forms\Components\Placeholder::make('totals')
                            ->label('')
                            ->content(function ($record, callable $get) {
                                $items = $get('items');
                                $totalBox = 0;
                                $totalWeight = 0;

                                if (is_array($items)) {
                                    foreach ($items as $item) {
                                        $totalBox += (int) ($item['box'] ?? 0);
                                        $totalWeight += (float) ($item['weight'] ?? 0);
                                    }
                                }

                                $totalBoxFormatted = number_format($totalBox, 0, ',', '.');
                                $totalWeightFormatted = number_format($totalWeight, 2, ',', '.');

                                $receivedWeightHtml = "";
                                if ($record && $record->status === 'Approved') {
                                    $receipt = \App\Models\DeliveryOrderReceipt::where('delivery_order_id', $record->id)->first();
                                    if ($receipt) {
                                        $receivedWeightFormatted = number_format($receipt->total_weight, 2, ',', '.');
                                        $receivedWeightHtml = "
                                            <div class='h-8 w-px bg-gray-200 dark:bg-gray-700'></div>
                                            <div class='flex flex-col items-end'>
                                                <span class='text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider'>" . __('Total Received Weight') . "</span>
                                                <span class='text-base font-extrabold text-emerald-600 dark:text-emerald-400'>{$receivedWeightFormatted} <span class='text-xs font-normal text-gray-400'>Kg</span></span>
                                            </div>
                                        ";
                                    }
                                }

                                return new \Illuminate\Support\HtmlString("
                                    <div class='flex justify-end mt-4'>
                                        <div class='flex flex-wrap items-center gap-6 border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-gray-50/50 dark:bg-gray-800/40 shadow-sm'>
                                            <div class='flex flex-col items-end'>
                                                <span class='text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider'>" . __('Total Box') . "</span>
                                                <span class='text-base font-extrabold text-amber-600 dark:text-amber-400'>{$totalBoxFormatted}</span>
                                            </div>
                                            <div class='h-8 w-px bg-gray-200 dark:bg-gray-700'></div>
                                            <div class='flex flex-col items-end'>
                                                <span class='text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider'>" . __('Total Weight') . "</span>
                                                <span class='text-base font-extrabold text-gray-800 dark:text-gray-200'>{$totalWeightFormatted} <span class='text-xs font-normal text-gray-400'>Kg</span></span>
                                            </div>
                                            {$receivedWeightHtml}
                                        </div>
                                    </div>
                                ");
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (DeliveryOrder $record): string => ($record->trashed() || $record->status === 'Approved') 
                ? static::getUrl('view', ['record' => $record->id]) 
                : static::getUrl('edit', ['record' => $record->id])
            )
            ->columns([
                Tables\Columns\TextColumn::make('delivery_order_number')
                    ->label(__('DO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn (\App\Models\DeliveryOrder $record): ?string => route('print.delivery-order', ['record' => $record->id]))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('tally.tally_number')
                    ->label(__('Tally Number'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (DeliveryOrder $record): ?string => $record->tally_id ? route('print.tally', ['record' => $record->tally_id]) : null)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(50)
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'Ready',
                        'success' => 'Approved',
                    ])
                    ->formatStateUsing(fn ($state) => __($state)),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_delivery_orders')),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('Customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['created_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['created_until'] ?? now()->toDateString();

                        return $query
                            ->when(
                                $from,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $until,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['created_from'])->format('d M Y');
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['created_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->recordClasses(fn (DeliveryOrder $record) => match (true) {
                $record->trashed() => 'bg-danger-50 dark:bg-danger-900/20',
                default => null,
            })
            ->actions([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(fn (\Illuminate\Support\Collection $records) => $records->each->delete()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryOrders::route('/'),
            'create' => Pages\CreateDeliveryOrder::route('/create'),
            'draft' => Pages\DraftDeliveryOrders::route('/draft'),
            'detail-list' => Pages\DeliveryOrderDetailList::route('/detail-list'),
            'edit' => Pages\EditDeliveryOrder::route('/{record}/edit'),
            'view' => Pages\ViewDeliveryOrder::route('/{record}'),
            'approve' => Pages\ApproveDeliveryOrder::route('/{record}/approve'),
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
