<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DeliveryOrderResource\Pages;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliveryOrderResource extends Resource
{
    protected static ?string $model = DeliveryOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationGroup(): ?string
    {
        return __('DISTRIBUTION');
    }

    public static function getNavigationLabel(): string
    {
        return __('Delivery Order');
    }

    public static function getModelLabel(): string
    {
        return __('Delivery Order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Delivery Orders');
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
                                        
                                        foreach ($aggregated as &$agg) {
                                            $agg['weight'] = round($agg['weight'], 2);
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

                        // Tiga angka ringkasan, dirender oleh Filament sendiri.
                        //
                        // Sebelumnya blok ini adalah HTML rakitan tangan dengan
                        // huruf extrabold, label kapital renggang berukuran 10
                        // piksel, garis pemisah, bayangan, dan warna amber serta
                        // emerald -- ramai untuk tiga angka yang cuma perlu
                        // dibaca sekilas.
                        //
                        // Yang lebih runyam: sebagian gayanya tidak pernah
                        // muncul. Panel ini tidak memuat hasil build CSS
                        // aplikasi, dan lima dari kelas yang dipakainya --
                        // dua warna, dua penata huruf, dan satu ukuran
                        // sembarang -- tidak ada di CSS bawaan Filament. Jadi
                        // yang tampil selama ini bukan yang dirancang, dan
                        // tidak ada error yang memberitahu.
                        //
                        // Memakai Placeholder biasa menghapus dua persoalan itu
                        // sekaligus: tampilannya seragam dengan isian lain di
                        // form yang sama, dan tidak ada satu pun kelas yang bisa
                        // diam-diam kehilangan wujudnya.
                        Forms\Components\Grid::make()
                            ->columns(3)
                            ->schema([
                                Forms\Components\Placeholder::make('total_box')
                                    ->label(__('Total Box'))
                                    ->content(fn (callable $get): string => number_format(
                                        static::sumItems($get('items'), 'box'),
                                        0,
                                        ',',
                                        '.',
                                    )),

                                Forms\Components\Placeholder::make('total_weight')
                                    ->label(__('Total Weight'))
                                    ->content(fn (callable $get): string => number_format(
                                        static::sumItems($get('items'), 'weight'),
                                        2,
                                        ',',
                                        '.',
                                    ).' Kg'),

                                // Hanya ada setelah barangnya benar-benar
                                // diterima, jadi tidak ditampilkan sebagai nol
                                // pada dokumen yang belum sampai tujuan --
                                // nol yang tidak berarti apa-apa lebih
                                // menyesatkan daripada tidak ada.
                                Forms\Components\Placeholder::make('total_received_weight')
                                    ->label(__('Total Received Weight'))
                                    ->visible(fn ($record): bool => $record?->status === 'Approved'
                                        && $record->receipt()->exists())
                                    ->content(fn ($record): string => number_format(
                                        (float) $record->receipt()->value('total_weight'),
                                        2,
                                        ',',
                                        '.',
                                    ).' Kg'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Menjumlahkan satu kolom dari baris-baris yang sedang ada di form.
     *
     * Dibaca dari state form, bukan dari basis data, supaya angkanya ikut
     * berubah saat baris disunting dan belum disimpan.
     *
     * @param  mixed  $items
     */
    protected static function sumItems($items, string $column): float
    {
        if (! is_array($items)) {
            return 0.0;
        }

        return array_sum(array_map(
            fn ($item): float => (float) ($item[$column] ?? 0),
            $items,
        ));
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
                    ->color('info')
                    ->weight('bold')
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
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['DO Number', 'Tally Number', 'Customer', 'Delivery Date', 'PO Number', 'Status', 'Created At', 'Created By']));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->delivery_order_number ?? '',
                                        $record->tally?->tally_number ?? '',
                                        $record->customer?->name ?? '',
                                        $record->delivery_date ? \Carbon\Carbon::parse($record->delivery_date)->format('Y-m-d') : '',
                                        $record->po_number ?? '',
                                        $record->status ?? '',
                                        $record->created_at ? $record->created_at->format('Y-m-d H:i') : '',
                                        $record->creator?->name ?? '',
                                    ]));
                                }
                                $writer->close();
                            }, 'DeliveryOrders.xlsx');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
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
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_delivery_orders',
        );
    }
}
