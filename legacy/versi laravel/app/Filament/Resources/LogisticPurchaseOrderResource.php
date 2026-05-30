<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogisticPurchaseOrderResource\Pages;
use App\Models\LogisticPurchaseOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

class LogisticPurchaseOrderResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = LogisticPurchaseOrder::class;

    // 1. MASUK KE GRUP MENU "PURCHASE ORDER"
    protected static ?string $navigationGroup = 'PURCHASE ORDER';
    protected static ?int $navigationSort = 15;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'PO Logistic';
    protected static ?string $modelLabel = 'PO Logistic';

    public static function getPermissionPrefixes(): array
    {
        return ['view_any', 'view', 'create', 'update', 'delete'];
    }

    // 2. MATIKAN TOMBOL "CREATE" (Karena PO otomatis dari Finance)
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi PO')
                    ->schema([
                        Forms\Components\TextInput::make('po_number')
                            ->label('Nomor PO')
                            ->readOnly(),
                        Forms\Components\DatePicker::make('po_date')
                            ->label('Tanggal PO')
                            ->readOnly(),
                        Forms\Components\Select::make('supplier_id')
                            ->relationship('supplier', 'name')
                            ->label('Supplier')
                            ->disabled(),
                        Forms\Components\Select::make('logistic_requisition_id')
                            ->relationship('requisition', 'document_number') // Disesuaikan dengan DB lu
                            ->label('No. Request')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Item Details')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('logistic_item_id')
                                    ->relationship('item', 'name')
                                    ->label('Item')
                                    ->disabled()
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                                Forms\Components\TextInput::make('qty')
                                    ->label('Qty')
                                    ->readOnly()
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                Forms\Components\TextInput::make('price')
                                    ->label('Harga')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->readOnly()
                                    ->columnSpan(['default' => 6, 'md' => 3]),
                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->readOnly()
                                    ->columnSpan(['default' => 12, 'md' => 3]),
                            ])
                            ->columns(12)
                            ->addable(false) // Gak bisa nambah item
                            ->deletable(false) // Gak bisa hapus item
                    ]),

                Forms\Components\Section::make('Summary & Tax')
                    ->schema([
                        Forms\Components\Textarea::make('note')
                            ->label('Catatan')
                            ->readOnly()
                            ->columnSpan(['default' => 12, 'md' => 8]),

                        Forms\Components\Grid::make(1)
                            ->schema([
                                Forms\Components\Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(function ($record) {
                                        $items = $record ? $record->items : [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += $item->qty * $item->price;
                                        }
                                        return 'Rp ' . number_format($total, 0, ',', '.');
                                    }),

                                Forms\Components\Placeholder::make('tax_display')
                                    ->label('Tax / PPN (11%)')
                                    ->content(function ($record) {
                                        $items = $record ? $record->items : [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += $item->qty * $item->price;
                                        }

                                        // Cek status pajak dari relasi supplier di PO
                                        if ($record && $record->supplier && $record->supplier->has_tax) {
                                            $tax = $total * 0.11;
                                            return 'Rp ' . number_format($tax, 0, ',', '.');
                                        }

                                        // Jika supplier tidak punya pajak (has_tax = 0), kembalikan Rp 0
                                        return 'Rp 0 (Non-PKP)';
                                    }),

                                Forms\Components\TextInput::make('total_amount')
                                    ->label('Grand Total')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->readOnly()
                                    ->extraAttributes(['class' => 'font-bold text-lg text-primary-600']),
                            ])
                            ->columnSpan(['default' => 12, 'md' => 4]),
                    ])->columns(12)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('No. PO')
                    ->searchable(),
                Tables\Columns\TextColumn::make('requisition.document_number') // Pastikan nama field ini bener di relasi lu ya
                    ->label('No. Request')
                    ->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                Tables\Columns\TextColumn::make('po_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('approver.name')
                    ->label('Approved By'),

                // INI DIA KOLOM STATUSNYA BRO!
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'OPEN' => 'gray',
                        'PARTIAL' => 'warning',
                        'COMPLETED' => 'success',
                        'CANCELLED' => 'danger',
                        default => 'primary',
                    }),

                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(50),
            ])
            ->filters([
                // Filter tanggal
                Tables\Filters\Filter::make('po_date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('date_until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when($data['date_from'], fn($q, $date) => $q->whereDate('po_date', '>=', $date))
                            ->when($data['date_until'], fn($q, $date) => $q->whereDate('po_date', '<=', $date));
                    })
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->icon('heroicon-s-printer')
                    ->color('success')
                    ->tooltip('Print PO')
                    ->iconButton()
                    ->url(fn($record) => route('print.logistic-po', ['id' => $record->id]))
                    ->openUrlInNewTab(),
                // Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            // Kita cuma butuh halaman index aja, sisanya dimatikan
            'index' => Pages\ListLogisticPurchaseOrders::route('/'),
        ];
    }
}
