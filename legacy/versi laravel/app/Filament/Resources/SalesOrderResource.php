<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesOrderResource\Pages;
use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use Filament\Support\RawJs;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'SALES';
    protected static ?string $navigationLabel = 'Sales Order';
    protected static ?string $pluralModelLabel = 'Sales Orders';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                /*
                 * Bagian form header Sales Order
                 */
                Forms\Components\Section::make('Informasi Pesanan')
                    ->compact()
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if ($state) {
                                    $customer = \App\Models\Customer::find($state);
                                    if ($customer) {
                                        $set('shipping_address', $customer->alamat1 ?? $customer->address ?? '');
                                    }
                                }

                                $items = $get('items') ?? [];
                                foreach ($items as $key => $item) {
                                    if (!empty($item['product_id'])) {
                                        $newPrice = static::calculateProductPrice($state, $item['product_id']);
                                        // Poin 3: Memaksa format ribuan (koma) dari backend sebelum dikirim ke frontend
                                        $set("items.{$key}.price", number_format($newPrice, 0, '', ','));
                                    }
                                }
                            }),

                        Forms\Components\DatePicker::make('delivery_date')
                            ->label('Tanggal Pengiriman')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('po_number')
                            ->label('Nomor PO')
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->label('Status Progress')
                            ->options([
                                'waiting' => 'Waiting',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('waiting')
                            ->required()
                            ->hiddenOn('create'),

                        Forms\Components\Textarea::make('shipping_address')
                            ->label('Alamat Pengiriman')
                            ->rows(2)
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('note')
                            ->label('Catatan Umum')
                            ->rows(2)
                            ->columnSpan(1),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => auth()->id()),
                    ])->columns(3),

                /*
                 * Bagian form detail item Sales Order
                 */
                Forms\Components\Section::make('Detail Produk')
                    ->compact()
                    ->schema([
                        // Poin 2: Penyesuaian Grid -> Produk(3), Berat(2), Harga(2), Diskon(2), Catatan(3) = 12 Kolom
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('col_produk')->label('Produk')->columnSpan(3),
                                Forms\Components\Placeholder::make('col_berat')->label('Berat/Qty')->columnSpan(2),
                                Forms\Components\Placeholder::make('col_harga')->label('Harga/Kg')->columnSpan(2),
                                Forms\Components\Placeholder::make('col_diskon')->label('Diskon (%)')->columnSpan(2),
                                Forms\Components\Placeholder::make('col_note')->label('Catatan Item')->columnSpan(3),
                            ]),

                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->defaultItems(1)
                            ->reorderableWithButtons()
                            ->addActionLabel('Tambah Produk')
                            ->schema([
                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        Forms\Components\Select::make('product_id')
                                            ->hiddenLabel()
                                            ->placeholder('Pilih Produk')
                                            ->relationship('product', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                $customerId = $get('../../customer_id');
                                                $price = static::calculateProductPrice($customerId, $state);
                                                // Poin 3: Memaksa format ribuan saat produk dipilih
                                                $set('price', number_format($price, 0, '', ','));
                                            })
                                            ->columnSpan(3), // Diperkecil jadi 3

                                        Forms\Components\TextInput::make('weight')
                                            ->hiddenLabel()
                                            ->placeholder('Berat')
                                            ->required()
                                            ->numeric()
                                            ->mask(RawJs::make('$money($input)'))
                                            ->stripCharacters(',')
                                            // Tambahkan baris ini:
                                            ->dehydrateStateUsing(fn($state) => $state ?: 0)
                                            ->extraInputAttributes(['class' => 'text-right'])
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('price')
                                            ->hiddenLabel()
                                            ->placeholder('Harga')
                                            ->required()
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->mask(RawJs::make('$money($input)'))
                                            ->stripCharacters(',')
                                            // Tambahkan baris ini:
                                            ->dehydrateStateUsing(fn($state) => $state ?: 0)
                                            ->extraInputAttributes(['class' => 'text-right'])
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('discount')
                                            ->hiddenLabel()
                                            ->placeholder('Diskon')
                                            ->numeric()
                                            ->suffix('%')
                                            ->minValue(0)
                                            ->maxValue(100)
                                            // Tambahkan baris ini (paling penting untuk mengatasi error):
                                            ->dehydrateStateUsing(fn($state) => $state ?: 0)
                                            ->extraInputAttributes(['class' => 'text-right'])
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('note')
                                            ->hiddenLabel()
                                            ->placeholder('Catatan Khusus')
                                            ->maxLength(255)
                                            ->columnSpan(3),
                                    ]),
                            ]),
                    ])
            ]);
    }


    protected static function calculateProductPrice($customerId, $productId): int
    {
        if (!$customerId || !$productId) {
            return 0;
        }

        $customer = \App\Models\Customer::find($customerId);
        if (!$customer || !$customer->customer_group_id) {
            return 0;
        }

        $priceList = \App\Models\PriceList::where('customer_group_id', $customer->customer_group_id)->first();
        if ($priceList) {
            $priceItem = \App\Models\PriceListItem::where('price_list_id', $priceList->id)
                ->where('product_id', $productId)
                ->first();
            if ($priceItem) {
                return $priceItem->price;
            }
        }

        return 0;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('so_number')
                    ->label('SO Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // Pastikan menggunakan 'customer.name' (atau sesuaikan jika nama kolomnya beda)
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->label('Tgl Kirim')
                    ->date('d M Y')
                    ->sortable(),

                // Menambahkan Nomor PO
                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO')
                    ->searchable()
                    ->toggleable(),

                // Menambahkan Note dengan limit karakter agar tabel tidak memanjang
                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                // Badge Status yang menyesuaikan warna persis seperti legacy code lu
                Tables\Columns\TextColumn::make('status')
                    ->label('Progress')
                    ->badge()
                    ->colors([
                        'gray' => 'waiting',
                        'info' => 'processing', // Di legacy pakai On Process
                        'success' => 'completed', // Di legacy pakai Delivered
                        'danger' => 'cancelled', // Di legacy pakai Cancel
                    ])
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),

                // Menambahkan nama pembuat SO
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Made By')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Filter Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                // 2. Filter Berdasarkan Periode Tanggal Kirim
                Tables\Filters\Filter::make('delivery_date')
                    ->form([
                        Forms\Components\DatePicker::make('delivery_from')
                            ->label('Dari Tanggal')
                            ->default(now()->startOfMonth()), // Default: Tanggal 1 bulan ini
                        Forms\Components\DatePicker::make('delivery_until')
                            ->label('Sampai Tanggal')
                            ->default(fn() => \App\Models\SalesOrder::max('delivery_date')), // Default: Tanggal delivery paling maksimal
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['delivery_from'],
                                fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('delivery_date', '>=', $date),
                            )
                            ->when(
                                $data['delivery_until'],
                                fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('delivery_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        // Ambil nilai acuan default untuk pembanding
                        $defaultFrom = now()->startOfMonth()->format('Y-m-d');
                        $defaultUntil = \App\Models\SalesOrder::max('delivery_date');

                        // Badge indikator HANYA akan muncul jika user mengubah nilainya dari aturan default
                        if (($data['delivery_from'] ?? null) && $data['delivery_from'] !== $defaultFrom) {
                            $indicators[] = 'Dari: ' . \Carbon\Carbon::parse($data['delivery_from'])->format('d M Y');
                        }

                        if (($data['delivery_until'] ?? null) && $data['delivery_until'] !== $defaultUntil) {
                            $indicators[] = 'Sampai: ' . \Carbon\Carbon::parse($data['delivery_until'])->format('d M Y');
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                // Tombol View & Print Terpadu
                Tables\Actions\Action::make('print')
                    ->label('View / Print')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->iconButton()
                    ->tooltip('Lihat & Cetak Dokumen')
                    ->url(fn(SalesOrder $record): string => route('print.salesorder', $record))
                    ->openUrlInNewTab(), // Buka di tab baru agar tidak menimpa aplikasi

                // Button Edit
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->color('success')
                    ->visible(fn(SalesOrder $record) => $record->status === 'waiting'),

                // Button Delete
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->visible(fn(SalesOrder $record) => $record->status === 'waiting'),
            ])
            ->recordUrl(null)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Relasi tambahan jika diperlukan
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesOrders::route('/'),
            'create' => Pages\CreateSalesOrder::route('/create'),
            'edit' => Pages\EditSalesOrder::route('/{record}/edit'),
        ];
    }
}
