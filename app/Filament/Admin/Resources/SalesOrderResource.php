<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SalesOrderResource\Pages;
use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\RawJs;
use Illuminate\Support\Str;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Sales Order';
    protected static ?string $pluralModelLabel = 'Sales Orders';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Order Information'))
                    ->compact()
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label(__('Customer'))
                            ->relationship('customer', 'name', fn (Builder $query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn (?SalesOrder $record) => $record?->status === 'processing')
                            ->dehydrated()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if ($state) {
                                    $customer = Customer::find($state);
                                    if ($customer) {
                                        $set('shipping_address', $customer->alamat1 ?? $customer->address ?? '');
                                    }
                                }

                                // Update prices in existing items
                                $items = $get('items') ?? [];
                                foreach ($items as $key => $item) {
                                    if (!empty($item['product_id'])) {
                                        $newPrice = static::calculateProductPrice($state, $item['product_id']);
                                        $set("items.{$key}.price", number_format($newPrice, 0, '', '.'));
                                    }
                                }
                            }),

                        Forms\Components\DatePicker::make('delivery_date')
                            ->label(__('Delivery Date'))
                            ->required()
                            ->default(now())
                            ->disabled(fn (?SalesOrder $record) => $record?->status === 'processing')
                            ->dehydrated(),

                        Forms\Components\TextInput::make('po_number')
                            ->label(__('PO Number'))
                            ->maxLength(255),

                        Forms\Components\Textarea::make('shipping_address')
                            ->label(__('Shipping Address'))
                            ->rows(2)
                            ->columnSpan(2)
                            ->disabled(fn (?SalesOrder $record) => $record?->status === 'processing')
                            ->dehydrated(),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('down_payment')
                            ->label(__('Down Payment (DP)'))
                            ->numeric()
                            ->default(0)
                            ->disabled(fn (?SalesOrder $record) => in_array($record?->status, ['processing', 'cancelled', 'canceled', 'ready']))
                            ->dehydrated()
                            /*
                             * WAJIB, dan ini bukan soal tampilan saja.
                             *
                             * Nilai dari kolom decimal(15,2) berbentuk
                             * "500000.00". Mask uang membuang karakter
                             * non-digit, jadi nol di belakang titik ikut
                             * terhitung dan field menampilkan 50.000.000.
                             * Lalu `stripCharacters('.')` membuang titiknya
                             * saat disimpan -- dan yang TERSIMPAN benar-benar
                             * 50 juta.
                             *
                             * Artinya uang muka pelanggan membengkak seratus
                             * kali lipat setiap kali form dibuka lalu disimpan
                             * ulang, tanpa satu pun error.
                             */
                            ->formatStateUsing(fn ($state): ?string => $state === null
                                ? null
                                : number_format((float) $state, 0, ',', '.'))
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters('.')
                            ->extraInputAttributes(['onfocus' => 'this.select()', 'class' => 'text-right'])
                            ->columnSpan(3),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => auth()->id()),
                    ])->columns(3)
                    ->disabled(fn (?SalesOrder $record) => in_array($record?->status, ['cancelled', 'canceled', 'ready'])),

                Forms\Components\Section::make(__('Products Detail'))
                    ->compact()
                    ->extraAttributes([
                        'x-on:keydown.enter' => '
                            (function(e) {
                                let target = e.target;
                                if (!target) return;
                                
                                let classes = ["so-weight-input-column", "so-price-input-column", "so-discount-input-column", "so-note-input-column"];
                                let foundClass = classes.find(c => target.classList.contains(c));
                                
                                if (foundClass) {
                                    e.preventDefault();
                                    let inputs = Array.from(document.querySelectorAll("." + foundClass));
                                    let idx = inputs.indexOf(target);
                                    if (idx !== -1 && inputs[idx + 1]) {
                                        inputs[idx + 1].focus();
                                        if (typeof inputs[idx + 1].select === "function") {
                                            inputs[idx + 1].select();
                                        }
                                    }
                                } else {
                                    let selectWrapper = target.closest(".so-product-select-column");
                                    if (selectWrapper) {
                                        e.preventDefault();
                                        let wrappers = Array.from(document.querySelectorAll(".so-product-select-column"));
                                        let idx = wrappers.indexOf(selectWrapper);
                                        if (idx !== -1 && wrappers[idx + 1]) {
                                            let nextInput = wrappers[idx + 1].querySelector("input, [role=\"combobox\"]");
                                            if (nextInput) {
                                                nextInput.focus();
                                            }
                                        }
                                    }
                                }
                            })(event)
                        ',
                        'x-on:input' => '
                            (function(e) {
                                let target = e.target;
                                if (!target) return;
                                
                                let classes = ["so-weight-input-column", "so-price-input-column", "so-discount-input-column"];
                                let foundClass = classes.find(c => target.classList.contains(c));
                                
                                if (foundClass) {
                                    let digits = target.value.replace(/[^0-9]/g, "");
                                    let formatted = digits;
                                    if (foundClass !== "so-discount-input-column" && digits !== "") {
                                        formatted = new Intl.NumberFormat("de-DE").format(parseInt(digits, 10));
                                    }
                                    if (target.value !== formatted) {
                                        let selectionStart = target.selectionStart;
                                        let selectionEnd = target.selectionEnd;
                                        let originalLength = target.value.length;
                                        
                                        target.value = formatted;
                                        
                                        let diff = formatted.length - originalLength;
                                        target.setSelectionRange(selectionStart + diff, selectionEnd + diff);
                                        
                                        target.dispatchEvent(new Event("input", { bubbles: true }));
                                    }
                                }
                            })(event)
                        '
                    ])
                    ->headerActions([
                        Forms\Components\Actions\Action::make('add_products')
                            ->label(__('Add Products'))
                            ->icon('heroicon-m-plus')
                            ->visible(fn (?SalesOrder $record) => !in_array($record?->status, ['cancelled', 'canceled', 'ready']))
                            ->form([
                                Forms\Components\Select::make('product_ids')
                                    ->label(__('Select Products'))
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->extraAttributes([
                                        'style' => 'margin-bottom: 240px;',
                                    ])
                                    ->options(function (Get $get, $livewire) {
                                        $selectedIds = collect($livewire->data['items'] ?? $get('../../items') ?? [])
                                            ->pluck('product_id')
                                            ->filter()
                                            ->toArray();
                                        return Product::whereNotIn('id', $selectedIds)->pluck('name', 'id');
                                    })
                                    ->required(),
                            ])
                            ->action(function (array $data, Get $get, Set $set, $livewire) {
                                $customerId = $livewire->data['customer_id'] ?? $get('../../customer_id') ?? null;
                                $selectedProductIds = $data['product_ids'] ?? [];
                                $currentItems = $livewire->data['items'] ?? $get('../../items') ?? [];
                                
                                foreach ($selectedProductIds as $productId) {
                                    $existingKey = null;
                                    foreach ($currentItems as $key => $item) {
                                        if (($item['product_id'] ?? null) == $productId) {
                                            $existingKey = $key;
                                            break;
                                        }
                                    }

                                    $price = static::calculateProductPrice($customerId, $productId);

                                    if ($existingKey !== null) {
                                        $currentItems[$existingKey]['price'] = number_format($price, 0, '', '.');
                                    } else {
                                        $newKey = 'item_' . \Illuminate\Support\Str::random(12);
                                        $currentItems[$newKey] = [
                                            'product_id' => $productId,
                                            'weight' => 0,
                                            'price' => number_format($price, 0, '', '.'),
                                            'discount' => 0,
                                            'note' => '',
                                        ];
                                    }
                                }
                                $set('items', $currentItems);
                            })
                    ])
                    ->schema([
                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('col_produk')->label(__('Product'))->columnSpan(3),
                                Forms\Components\Placeholder::make('col_berat')->label(__('Weight/Qty'))->columnSpan(2),
                                Forms\Components\Placeholder::make('col_harga')->label(__('Price'))->columnSpan(2),
                                Forms\Components\Placeholder::make('col_diskon')->label(__('Discount (%)'))->columnSpan(2),
                                Forms\Components\Placeholder::make('col_note')->label(__('Note'))->columnSpan(3),
                            ]),

                        Forms\Components\Repeater::make('items')
                            ->hiddenLabel()
                            ->defaultItems(0) // Start empty, force use of modal
                            ->disableItemMovement()
                            ->disableItemCreation() // Disable standard "Add Item" to force modal usage
                            ->disableItemDeletion(fn (?SalesOrder $record) => $record?->status === 'processing')
                            ->validationMessages([
                                'min' => __('Sales order cannot be created without any products.'),
                            ])
                            ->columns(12)
                            ->schema([
                                Forms\Components\Hidden::make('id'),

                                Forms\Components\Select::make('product_id')
                                    ->hiddenLabel()
                                    ->placeholder(__('Product'))
                                    ->options(fn() => \App\Models\Product::pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->disabled(fn (Get $get, ?SalesOrder $record) => $record?->status === 'processing' && !empty($get('id')))
                                    ->dehydrated()
                                    ->extraAttributes([
                                        'class' => 'so-product-select-column',
                                    ])
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('weight')
                                    ->hiddenLabel()
                                    ->placeholder(__('Weight'))
                                    ->required()
                                    // Click auto select all block as requested
                                    ->extraInputAttributes([
                                        'class' => 'text-right so-weight-input-column',
                                        'onclick' => 'this.select()',
                                        'inputmode' => 'numeric',
                                    ])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('price')
                                    ->hiddenLabel()
                                    ->placeholder(__('Price'))
                                    ->required()
                                    ->prefix('Rp')
                                    ->extraInputAttributes([
                                        'class' => 'text-right so-price-input-column',
                                        'onclick' => 'this.select()',
                                        'inputmode' => 'numeric',
                                    ])
                                    ->columnSpan(2),

                                // Diskon ini PERSEN, dan angkanya dipakai langsung
                                // oleh Invoice sebagai gross * (discount / 100).
                                //
                                // Sebelumnya dijaga dengan ->minValue(0)->maxValue(100),
                                // yang ternyata TIDAK menjaga apa pun. Keduanya hanya
                                // menghasilkan aturan "min:0" dan "max:100" tanpa aturan
                                // numerik, sehingga Laravel memeriksa PANJANG KARAKTER,
                                // bukan nilainya -- "500" lolos karena cuma tiga huruf.
                                // Diskon 500% membuat baris tagihan menjadi minus tanpa
                                // satu pun error di sepanjang jalannya.
                                //
                                // Aturannya ditulis manual, BUKAN dengan ->numeric().
                                // Pemanggilan itu membuat input menjadi type=number
                                // lengkap dengan tombol panah, yang sudah dilarang untuk
                                // kolom uang dan berat karena gampang tergeser.
                                Forms\Components\TextInput::make('discount')
                                    ->hiddenLabel()
                                    ->placeholder(__('Discount'))
                                    ->suffix('%')
                                    ->rules(['numeric', 'min:0', 'max:100'])
                                    ->validationMessages([
                                        'numeric' => __('Discount must be a number.'),
                                        'min' => __('Discount cannot be negative.'),
                                        'max' => __('Discount cannot be more than 100%.'),
                                    ])
                                    ->extraInputAttributes([
                                        'class' => 'text-right so-discount-input-column',
                                        'onclick' => 'this.select()',
                                        'inputmode' => 'numeric',
                                    ])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('note')
                                    ->hiddenLabel()
                                    ->placeholder(__('Note'))
                                    ->maxLength(255)
                                    ->extraInputAttributes([
                                        'class' => 'so-note-input-column',
                                    ])
                                    ->columnSpan(3),
                            ]),
                    ])
                    ->disabled(fn (?SalesOrder $record) => in_array($record?->status, ['cancelled', 'canceled', 'ready']))
            ]);
    }

    /**
     * Harga awal sebuah baris SO, diambil dari price list grup pelanggan.
     *
     * Mengembalikan 0 bila harganya tidak ditemukan, dan itu DISENGAJA --
     * keputusan Project Owner, 31 Agustus 2026. Saat membuat SO user memang
     * bebas mengubah harga, jadi nol hanyalah titik awal, bukan kegagalan.
     * Jangan diubah menjadi penolakan.
     *
     * Yang justru diperbaiki adalah momennya: grup yang belum punya price
     * list ditawari membuatnya lebih awal, saat pelanggan atau grupnya baru
     * disimpan, supaya harganya sudah siap sebelum SO pertama dibuat.
     */
    protected static function calculateProductPrice($customerId, $productId): int
    {
        if (! $customerId || ! $productId) {
            return 0;
        }

        $customer = Customer::find($customerId);

        if (! $customer?->customer_group_id) {
            return 0;
        }

        $priceList = PriceList::where('customer_group_id', $customer->customer_group_id)->first();

        if (! $priceList) {
            return 0;
        }

        $priceItem = PriceListItem::where('price_list_id', $priceList->id)
            ->where('product_id', $productId)
            ->first();

        return (int) ($priceItem->price ?? 0);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('so_number')
                    ->label(__('SO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

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
                    ->toggleable(),

                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors([
                        'gray' => 'waiting',
                        'info' => 'processing',
                        'warning' => 'prepared',
                        'success' => ['completed', 'ready'],
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_sales_orders')),

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
                        $until = $data['delivery_until'] ?? SalesOrder::max('delivery_date') ?? now()->toDateString();

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
            ->recordClasses(fn (SalesOrder $record) => match (true) {
                $record->trashed() => 'bg-danger-50 dark:bg-danger-900/20',
                default => null,
            })
            ->headerActions([])
            ->actions([
                // Clickable rows handle editing; no action buttons displayed on the index table.
            ])
            ->recordUrl(fn(SalesOrder $record) => !$record->trashed() ? Pages\EditSalesOrder::getUrl([$record->id]) : null)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
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
            'index' => Pages\ListSalesOrders::route('/'),
            'create' => Pages\CreateSalesOrder::route('/create'),
            'edit' => Pages\EditSalesOrder::route('/{record}/edit'),
            'detail-list' => Pages\SalesOrderDetailList::route('/detail-list'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('SALES');
    }
}
