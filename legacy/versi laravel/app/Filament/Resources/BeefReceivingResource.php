<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BeefReceivingResource\Pages;
use App\Models\BeefReceiving;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class BeefReceivingResource extends Resource
{
    protected static ?string $model = BeefReceiving::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'GOOD RECEIPT';
    protected static ?string $navigationLabel = 'GR Beef';
    protected static ?int $navigationSort = 18;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Receiving Information')
                    ->schema([
                        Forms\Components\Hidden::make('beef_purchase_order_id')->required(),
                        Forms\Components\Hidden::make('supplier_id')->required(),

                        Forms\Components\TextInput::make('po_number_display')
                            ->label('PO Number')
                            ->disabled()
                            ->dehydrated(false), // Gak disave ke DB, cuma buat display

                        Forms\Components\TextInput::make('supplier_name_display')
                            ->label('Supplier')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DatePicker::make('receive_date')
                            ->label('Receive Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('sj_number')
                            ->label('Surat Jalan Number')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ])->columns(2),

                // TAMBAHAN: Wadah buat nampilin detail daging yang ditarik dari PO
                Forms\Components\Section::make('Item Details')
                    ->schema([
                        Forms\Components\Repeater::make('tempItems')
                            ->label('Items to Receive')
                            ->addable(false) // Gak boleh nambah item manual di luar PO
                            ->deletable(false) // Gak boleh hapus item
                            ->reorderable(false)
                            ->schema([
                                Forms\Components\Hidden::make('beef_item_id'),
                                Forms\Components\Hidden::make('price'), // Bawaan harga PO buat tagihan AP

                                Forms\Components\TextInput::make('item_name')
                                    ->label('Item Name')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('qty_remaining')
                                    ->label('Qty Remaining')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->numeric()
                                    ->suffix('Kg'),

                                Forms\Components\TextInput::make('qty_received')
                                    ->label('Qty Received Today')
                                    ->required()
                                    ->numeric()
                                    ->suffix('Kg')
                                    ->default(0)
                                    ->rules([
                                        // Validasi biar user gak nginput lebih dari sisa PO
                                        fn(Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $remaining = (float) $get('qty_remaining');
                                            if ((float) $value > $remaining) {
                                                $fail("Qty cannot exceed remaining PO ($remaining).");
                                            }
                                        },
                                    ]),
                            ])
                            ->columns(4)
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receiving_number')
                    ->label('GR Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('purchaseOrder.po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\TextColumn::make('receive_date')
                    ->label('Date Received')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sj_number')
                    ->label('Surat Jalan')
                    ->searchable(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([


                // TOMBOL SAKTI: CLOSE PO & LEMPAR KE FINANCE
                Tables\Actions\Action::make('mark_done')
                    ->label('Mark as Done')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    // Cuma muncul kalau status PO-nya masih PARTIAL
                    ->visible(fn($record) => $record->purchaseOrder->status === 'PARTIAL')
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Done')
                    ->modalDescription('This will mark the receiving as done and close the associated PO. Continue?')
                    ->action(function ($record) {
                        $po = $record->purchaseOrder;

                        // Hitung grand total dari semua GR yang udah masuk buat PO ini
                        $totalKeseluruhan = \App\Models\BeefReceivingItem::whereHas('receiving', function ($q) use ($po) {
                            $q->where('beef_purchase_order_id', $po->id);
                        })->sum('subtotal');

                        if ($totalKeseluruhan > 0) {
                            \App\Models\AccountPayable::create([
                                'payable_id'   => $po->id,
                                'payable_type' => get_class($po),
                                'supplier_id'  => $po->supplier_id,
                                'total_amount' => $totalKeseluruhan,
                                'paid_amount'  => 0,
                                'balance_due'  => $totalKeseluruhan,
                                'status'       => 'UNPAID',
                                'due_date'     => now()->addDays(14),
                                'note'         => "Tagihan PO Beef (Manual Close): " . $po->po_number,
                                'created_by'   => \Illuminate\Support\Facades\Auth::id(),
                            ]);
                        }

                        // Ganti status jadi COMPLETED biar gak gantung
                        $po->update(['status' => 'COMPLETED']);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('PO Ditutup!')
                            ->body('Sisa PO dibatalkan dan Tagihan berhasil dikirim ke Finance.')
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Receiving Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('receiving_number')
                            ->label('Receive Number')
                            ->weight('bold')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('purchaseOrder.po_number')
                            ->label('PO Number')
                            ->color('primary')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('supplier.name')
                            ->label('Supplier'),

                        Infolists\Components\TextEntry::make('receive_date')
                            ->label('Receive Date')
                            ->date('d M Y'),

                        Infolists\Components\TextEntry::make('sj_number')
                            ->label('Delivery Note (SJ)')
                            ->default('-'),

                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Received By'),

                        Infolists\Components\TextEntry::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ])->columns(3),

                Infolists\Components\Section::make('Received Items Detail')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('item.name')
                                    ->label('Item Name')
                                    ->weight('bold'),

                                // Kolom 1: Qty yang dipesan di PO
                                Infolists\Components\TextEntry::make('po_qty')
                                    ->label('PO Qty')
                                    ->state(function ($record) {
                                        // Cari data item ini di PO awal
                                        $poItem = $record->receiving->purchaseOrder->items
                                            ->where('product_id', $record->beef_item_id)
                                            ->first();
                                        return $poItem ? number_format($poItem->qty, 2, '.', ',') . ' Kg' : '-';
                                    }),

                                // Kolom 2: Qty yang di-GR hari ini
                                Infolists\Components\TextEntry::make('qty_received')
                                    ->label('Received Qty')
                                    ->state(fn($record) => number_format($record->qty_received, 2, '.', ',') . ' Kg')
                                    ->color('success')
                                    ->weight('bold'),

                                // Kolom 3: Sisa barang yang belum datang
                                Infolists\Components\TextEntry::make('remaining_qty')
                                    ->label('Remaining Qty')
                                    ->color('danger')
                                    ->state(function ($record) {
                                        $poItem = $record->receiving->purchaseOrder->items
                                            ->where('product_id', $record->beef_item_id)
                                            ->first();

                                        if (!$poItem) return '-';

                                        // Hitung total penerimaan dari semua GR untuk PO ini
                                        $totalReceived = \App\Models\BeefReceivingItem::whereHas('receiving', function ($q) use ($record) {
                                            $q->where('beef_purchase_order_id', $record->receiving->beef_purchase_order_id);
                                        })
                                            ->where('beef_item_id', $record->beef_item_id)
                                            ->sum('qty_received');

                                        $remaining = $poItem->qty - $totalReceived;
                                        return number_format($remaining, 2, '.', ',') . ' Kg';
                                    }),
                            ])
                            ->columns(4) // Format: 4 kolom yang pas dan simetris
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBeefReceivings::route('/'),
            'create' => Pages\CreateBeefReceiving::route('/create'),
            // INI DIA OBAT DARI ERROR LU BRO:
            'draft' => Pages\DraftBeefReceiving::route('/draft'),
        ];
    }
}
