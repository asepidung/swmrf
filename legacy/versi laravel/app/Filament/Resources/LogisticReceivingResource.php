<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogisticReceivingResource\Pages;
use App\Models\LogisticReceiving;
use App\Models\LogisticPurchaseOrder;
use App\Models\AccountPayable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class LogisticReceivingResource extends Resource
{
    protected static ?string $model = LogisticReceiving::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'GOOD RECEIPT';
    protected static ?string $navigationLabel = 'GR Logistic';
    protected static ?int $navigationSort = 17;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Receiving Information')
                    ->schema([
                        Forms\Components\Hidden::make('logistic_purchase_order_id')->required(),
                        Forms\Components\Hidden::make('supplier_id')->required(),

                        Forms\Components\TextInput::make('po_number_display')
                            ->label('PO Number')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('supplier_name_display')
                            ->label('Supplier')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DatePicker::make('receive_date')
                            ->label('Receiving Date')
                            ->required(),

                        Forms\Components\TextInput::make('sj_number')
                            ->label('Delivery Order Number (SJ)'),

                        Forms\Components\Textarea::make('note')
                            ->label('Receiving Note')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Item Details (Physical Check)')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                Forms\Components\Hidden::make('logistic_item_id'),

                                Forms\Components\TextInput::make('item_name_display')
                                    ->label('Item Description')
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->columnSpan(6),

                                Forms\Components\TextInput::make('qty_ordered')
                                    ->label('Pending PO Qty')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('qty_received')
                                    ->label('Received Qty')
                                    ->numeric()
                                    ->required()
                                    ->columnSpan(3),
                            ])
                            ->columns(12)
                            ->addable(false)
                            ->deletable(true)
                    ])
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Receiving Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('receiving_number')
                            ->label('GR Number')
                            ->weight('bold')
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('purchaseOrder.po_number')
                            ->label('PO Number'),
                        Infolists\Components\TextEntry::make('supplier.name')
                            ->label('Supplier'),
                        Infolists\Components\TextEntry::make('receive_date')
                            ->label('Receiving Date')
                            ->date('d F Y'),
                        Infolists\Components\TextEntry::make('sj_number')
                            ->label('Delivery Order (SJ)'),
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Received By'),
                        Infolists\Components\TextEntry::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ])->columns(3),

                Infolists\Components\Section::make('Received Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('item.name')
                                    ->label('Item Description')
                                    ->weight('bold')
                                    ->columnSpan(3),
                                Infolists\Components\TextEntry::make('qty_received')
                                    ->label('Qty')
                                    ->badge()
                                    ->color('success')
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            // Bikin barisnya bisa diklik buat ngebuka Infolist (View)
            ->recordAction(Tables\Actions\ViewAction::class)
            ->columns([
                Tables\Columns\TextColumn::make('receiving_number')->label('No. GR')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('receive_date')->label('Tgl. GR')->date('d-M-Y')->sortable(),
                Tables\Columns\TextColumn::make('purchaseOrder.po_number')->label('No. PO')->searchable(),
                Tables\Columns\TextColumn::make('sj_number')->label('Surat Jalan')->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('Supplier')->searchable(),
            ])
            ->filters([
                // KITA BALIKIN LAGI FILTER TANGGALNYA BRO!
                Tables\Filters\Filter::make('receive_date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('date_until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn($q, $date) => $q->whereDate('receive_date', '>=', $date)
                            )
                            ->when(
                                $data['date_until'],
                                fn($q, $date) => $q->whereDate('receive_date', '<=', $date)
                            );
                    })
            ])
            ->actions([
                // 1. MESIN VIEW (Wujudnya digaibkan biar icon mata gak muncul)
                Tables\Actions\ViewAction::make()
                    ->extraAttributes(['class' => 'hidden']),

                // 2. TOMBOL PRINT (Cuma Ikon Printer)
                Tables\Actions\Action::make('print')
                    ->icon('heroicon-s-printer')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Print GR')
                    ->url(fn($record) => route('print.logistic-receiving', ['id' => $record->id]))
                    ->openUrlInNewTab(),

                // 3. TOMBOL CLOSE PO (Cuma Ikon Check Circle)
                Tables\Actions\Action::make('mark_po_done')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Close PO')
                    ->requiresConfirmation()
                    ->modalHeading('Tutup PO')
                    ->modalDescription('Dengan menutup PO ini, GR tidak bisa dilakukan lagi dan tagihan akan diteruskan ke Finance. Lanjutkan?')
                    ->hidden(function (LogisticReceiving $record) {
                        return $record->purchaseOrder?->status === 'COMPLETED';
                    })
                    ->action(function (LogisticReceiving $record) {
                        /** @var LogisticPurchaseOrder|null $po */
                        $po = $record->purchaseOrder;

                        if (!$po) return;

                        DB::transaction(function () use ($po, $record) {
                            $dpp = 0;

                            $po->load('receivings.items', 'supplier');
                            foreach ($po->receivings as $receiving) {
                                foreach ($receiving->items as $grItem) {
                                    $dpp += $grItem->subtotal;
                                }
                            }

                            $taxRate = $po->supplier->has_tax ? 11 : 0;
                            $taxAmount = $dpp * ($taxRate / 100);
                            $grandTotal = $dpp + $taxAmount;

                            $topDays = $po->supplier->term_of_payment ?? 0;
                            $dueDate = now()->addDays($topDays);

                            $existingAp = AccountPayable::where('logistic_purchase_order_id', $po->id)->first();

                            if ($existingAp) {
                                $existingAp->update([
                                    'dpp_amount'   => $dpp,
                                    'tax_amount'   => $taxAmount,
                                    'total_amount' => $grandTotal,
                                    'balance_due'  => $grandTotal - $existingAp->paid_amount,
                                    'due_date'     => $dueDate,
                                ]);
                            } else {
                                if ($grandTotal > 0) {
                                    AccountPayable::create([
                                        'logistic_purchase_order_id' => $po->id,
                                        'supplier_id'                => $po->supplier_id,
                                        'dpp_amount'                 => $dpp,
                                        'tax_amount'                 => $taxAmount,
                                        'total_amount'               => $grandTotal,
                                        'paid_amount'                => 0,
                                        'balance_due'                => $grandTotal,
                                        'status'                     => 'UNPAID',
                                        'due_date'                   => $dueDate,
                                        'note'                       => $record->note,
                                        'created_by'                 => Auth::id(),
                                    ]);
                                }
                            }

                            $po->update(['status' => 'COMPLETED']);

                            Notification::make()
                                ->success()
                                ->title('PO Berhasil Ditutup!')
                                ->body('Tagihan diteruskan ke Finance (Termasuk PPN: ' . $taxRate . '%).')
                                ->send();
                        });
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLogisticReceivings::route('/'),
            'create' => Pages\CreateLogisticReceiving::route('/create'),
            'draft' => Pages\DraftLogisticReceiving::route('/draft'),
        ];
    }
}
