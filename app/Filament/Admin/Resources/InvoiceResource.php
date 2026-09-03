<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\InvoiceResource\Pages;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return __('FINANCE');
    }

    public static function getNavigationLabel(): string
    {
        return __('Invoices');
    }

    public static function getModelLabel(): string
    {
        return __('Invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Invoices');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Invoice Header'))
                    ->schema([
                        Forms\Components\Hidden::make('delivery_order_receipt_id')
                            ->default(fn () => request()->query('delivery_order_receipt_id')),
                        Forms\Components\Hidden::make('sales_order_id')
                            ->default(fn () => \App\Models\DeliveryOrderReceipt::find(request()->query('delivery_order_receipt_id'))?->sales_order_id),

                        Forms\Components\Select::make('customer_id')
                            ->label(__('Customer'))
                            ->relationship('customer', 'name')
                            ->required()
                            ->disabled()
                            ->dehydrated(true)
                            ->live()
                            ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set))
                            ->default(fn () => \App\Models\DeliveryOrderReceipt::find(request()->query('delivery_order_receipt_id'))?->customer_id),

                        Forms\Components\DatePicker::make('invoice_date')
                            ->label(__('Invoice Date'))
                            ->required()
                            ->autofocus()
                            ->default(now()),

                        Forms\Components\TextInput::make('po_number')
                            ->label(__('PO Number'))
                            ->maxLength(255)
                            ->default(fn () => \App\Models\DeliveryOrderReceipt::find(request()->query('delivery_order_receipt_id'))?->po_number),

                        Forms\Components\TextInput::make('delivery_order_number')
                            ->label(__('DO Number'))
                            ->maxLength(255)
                            ->default(fn () => \App\Models\DeliveryOrderReceipt::find(request()->query('delivery_order_receipt_id'))?->deliveryOrder?->delivery_order_number),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->columnSpanFull(),
                    ])->columns(4),

                Forms\Components\Section::make(__('Products List'))
                    ->schema([
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('h_product')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white">Product</div>'))
                                    ->columnSpan(3),
                                Forms\Components\Placeholder::make('h_weight')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white text-right">Weight (Kg)</div>'))
                                    ->columnSpan(2),
                                Forms\Components\Placeholder::make('h_price')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white text-right">Price (Rp)*</div>'))
                                    ->columnSpan(2),
                                Forms\Components\Placeholder::make('h_disc_pct')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white text-right">Disc %</div>'))
                                    ->columnSpan(1),
                                Forms\Components\Placeholder::make('h_disc_rp')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white text-right">Disc Rp</div>'))
                                    ->columnSpan(2),
                                Forms\Components\Placeholder::make('h_amount')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white text-right">Amount (Rp)</div>'))
                                    ->columnSpan(2),
                            ]),
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->hiddenLabel()
                                    ->placeholder(__('Product'))
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('weight')
                                    ->hiddenLabel()
                                    ->placeholder(__('Weight (Kg)'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('price')
                                    ->hiddenLabel()
                                    ->placeholder(__('Price (Rp)'))
                                    ->numeric()
                                    ->required()
                                    ->numeric()
                                                                        ->live(onBlur: true)
                                    ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set))
                                    ->extraInputAttributes(['class' => 'text-right', 'onfocus' => 'this.select()'])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('discount_percent')
                                    ->hiddenLabel()
                                    ->placeholder(__('Disc %'))
                                    ->numeric()
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set))
                                    ->extraInputAttributes(['class' => 'text-right', 'onfocus' => 'this.select()'])
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('discount_rp')
                                    ->hiddenLabel()
                                    ->placeholder(__('Disc Rp'))
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->numeric()
                                                                        ->extraInputAttributes(['class' => 'text-right'])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('amount')
                                    ->hiddenLabel()
                                    ->placeholder(__('Amount (Rp)'))
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->numeric()
                                                                        ->extraInputAttributes(['class' => 'text-right'])
                                    ->columnSpan(2),
                             ])
                             ->columns(12)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement()
                            ->hiddenLabel()
                            ->default(function () {
                                $receiptId = request()->query('delivery_order_receipt_id');
                                if (!$receiptId) return [];
                                $receipt = \App\Models\DeliveryOrderReceipt::with('items', 'customer')->find($receiptId);
                                if (!$receipt) return [];
                                
                                $items = [];
                                foreach ($receipt->items as $item) {
                                    $soItem = \App\Models\SalesOrderItem::where('sales_order_id', $receipt->sales_order_id)
                                        ->where('product_id', $item->product_id)
                                        ->first();
                                    
                                    $price = $soItem ? (float)$soItem->price : 0.0;

                                    // Diskonnya diambil apa adanya dari Sales
                                    // Order dan TIDAK ditimpa di sini.
                                    //
                                    // Dulu berkas ini memberi 2% kepada
                                    // pelanggan yang NAMANYA mengandung DCA,
                                    // DCB, atau DCC, di empat tempat terpisah.
                                    // Aturannya benar secara bisnis -- tiga
                                    // Distribution Center Lion Superindo memang
                                    // disepakati mendapat diskon itu -- tetapi
                                    // tempatnya keliru, dengan dua akibat:
                                    //
                                    //  - SO tertulis 0% sementara invoice
                                    //    menagih 2%, sehingga dokumen yang
                                    //    dipegang pelanggan tidak cocok dengan
                                    //    tagihan yang dikirim;
                                    //  - mengganti nama pelanggan diam-diam
                                    //    mengubah harganya, dan pelanggan baru
                                    //    yang namanya kebetulan memuat huruf
                                    //    itu ikut mendapat diskon.
                                    //
                                    // Sekarang diskonnya berasal dari kolom
                                    // customers.default_discount, terisi
                                    // sendiri saat SO dibuat, dan terlihat di
                                    // sana. Jangan mengembalikan penggantian
                                    // di tempat ini.
                                    $discountPercent = $soItem ? (float)$soItem->discount : 0.0;

                                    // Yang ditagih adalah berat pada DO Receipt
                                    // APA ADANYA, tanpa dibandingkan dengan
                                    // berat PO.
                                    //
                                    // Keputusan bisnis Project Owner: kalau
                                    // barang yang datang lebih berat daripada
                                    // PO, penyesuaiannya dilakukan MANUSIA di
                                    // DO Receipt -- angkanya diturunkan ke
                                    // berat PO, dan selisihnya tercatat sebagai
                                    // Financial Loss lewat perbandingan berat
                                    // kirim lawan berat terima.
                                    //
                                    // Membatasinya otomatis di sini justru
                                    // merusak: penyesuaiannya hilang dari
                                    // pandangan, dan kerugiannya tidak pernah
                                    // tercatat. Jangan dipasang lagi.
                                    $gross = $item->weight * $price;
                                    $discountRp = round($gross * ($discountPercent / 100), 0);
                                    $amount = round($gross - $discountRp, 0);

                                    $items[] = [
                                        'product_id' => $item->product_id,
                                        'box' => $item->box,
                                        'weight' => $item->weight,
                                        'price' => $price,
                                        'discount_percent' => $discountPercent,
                                        'discount_rp' => $discountRp,
                                        'amount' => $amount,
                                    ];
                                }
                                return $items;
                            }),
                    ]),

                Forms\Components\Section::make(__('Other Charges (Shipping, etc.)'))
                    ->schema([
                        Forms\Components\Repeater::make('additionalCharges')
                            ->relationship('additionalCharges')
                            ->hiddenLabel()
                            ->addActionLabel(__('Add to additional charges'))
                            ->schema([
                                Forms\Components\Select::make('name')
                                    ->hiddenLabel()
                                    ->placeholder(__('Product'))
                                    ->options([
                                        'Ice Gell' => 'Ice Gell',
                                        'Styrofoam' => 'Styrofoam',
                                        'Delivery Cost' => 'Delivery Cost',
                                    ])
                                    ->required()
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('qty')
                                    ->hiddenLabel()
                                    ->placeholder(__('Qty'))
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->extraInputAttributes(['class' => 'text-right', 'onfocus' => 'this.select()'])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set))
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('price')
                                    ->hiddenLabel()
                                    ->placeholder(__('Price (Rp)'))
                                    ->numeric()
                                    ->required()
                                    ->extraInputAttributes(['class' => 'text-right', 'onfocus' => 'this.select()'])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set))
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('discount_percent')
                                    ->hiddenLabel()
                                    ->placeholder(__('Disc %'))
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('discount_rp')
                                    ->hiddenLabel()
                                    ->placeholder(__('Disc Rp'))
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('amount')
                                    ->hiddenLabel()
                                    ->placeholder(__('Amount (Rp)'))
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->columnSpan(2),
                            ])
                            ->columns(12)
                            ->default([])
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set)),
                    ]),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('empty1')->label('')->content('')->columnSpan(2),
                                Forms\Components\TextInput::make('total_weight')
                                    ->hiddenLabel()
                                    ->prefix('Total')
                                    ->suffix('Kg')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->default(fn () => \App\Models\DeliveryOrderReceipt::find(request()->query('delivery_order_receipt_id'))?->total_weight ?? 0.0)
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('total_discount')
                                    ->hiddenLabel()
                                    ->prefix('Total Disc')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->numeric()
                                                                        ->default(function () {
                                        $receiptId = request()->query('delivery_order_receipt_id');
                                        if (!$receiptId) return 0.0;
                                        $receipt = \App\Models\DeliveryOrderReceipt::find($receiptId);
                                        if (!$receipt) return 0.0;
                                        $totalDiscount = 0.0;
                                        foreach ($receipt->items as $item) {
                                            $soItem = \App\Models\SalesOrderItem::where('sales_order_id', $receipt->sales_order_id)
                                                ->where('product_id', $item->product_id)
                                                ->first();
                                            $price = $soItem ? (float)$soItem->price : 0.0;
                                            $discountPercent = $soItem ? (float)$soItem->discount : 0.0;
                                            $gross = $item->weight * $price;
                                            $totalDiscount += round($gross * ($discountPercent / 100), 0);
                                        }
                                        return round($totalDiscount, 0);
                                    })
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('subtotal')
                                    ->hiddenLabel()
                                    ->prefix('Total Amount')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->numeric()
                                                                        ->default(function () {
                                        $receiptId = request()->query('delivery_order_receipt_id');
                                        if (!$receiptId) return 0.0;
                                        $receipt = \App\Models\DeliveryOrderReceipt::find($receiptId);
                                        if (!$receipt) return 0.0;
                                        $subtotal = 0.0;
                                        foreach ($receipt->items as $item) {
                                            $soItem = \App\Models\SalesOrderItem::where('sales_order_id', $receipt->sales_order_id)
                                                ->where('product_id', $item->product_id)
                                                ->first();
                                            $price = $soItem ? (float)$soItem->price : 0.0;
                                            $discountPercent = $soItem ? (float)$soItem->discount : 0.0;
                                            $gross = $item->weight * $price;
                                            $discountRp = round($gross * ($discountPercent / 100), 0);
                                            $subtotal += ($gross - $discountRp);
                                        }
                                        return round($subtotal, 0);
                                    })
                                    ->columnSpan(4),
                            ]),

                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('empty3')->label('')->content('')->columnSpan(8),
                                Forms\Components\TextInput::make('down_payment')
                                    ->hiddenLabel()
                                    ->prefix('DP')
                                    ->numeric()
                                    ->extraInputAttributes(['class' => 'text-right', 'onfocus' => 'this.select()'])
                                    ->default(function () {
                                        $receiptId = request()->query('delivery_order_receipt_id');
                                        if (!$receiptId) return 0.0;
                                        return \App\Models\DeliveryOrderReceipt::find($receiptId)?->salesOrder?->down_payment ?? 0.0;
                                    })
                                    ->numeric()
                                                                        ->live(onBlur: true)
                                    ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set))
                                    ->columnSpan(4),
                            ]),

                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('empty4')->label('')->content('')->columnSpan(8),
                                Forms\Components\TextInput::make('balance')
                                    ->hiddenLabel()
                                    ->prefix('Balance')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->default(function () {
                                        $receiptId = request()->query('delivery_order_receipt_id');
                                        if (!$receiptId) return 0.0;
                                        $receipt = \App\Models\DeliveryOrderReceipt::find($receiptId);
                                        if (!$receipt) return 0.0;
                                        $subtotal = 0.0;
                                        foreach ($receipt->items as $item) {
                                            $soItem = \App\Models\SalesOrderItem::where('sales_order_id', $receipt->sales_order_id)
                                                ->where('product_id', $item->product_id)
                                                ->first();
                                            $price = $soItem ? (float)$soItem->price : 0.0;
                                            $discountPercent = $soItem ? (float)$soItem->discount : 0.0;
                                            $gross = $item->weight * $price;
                                            $discountRp = round($gross * ($discountPercent / 100), 0);
                                            $subtotal += ($gross - $discountRp);
                                        }
                                        $downPayment = \App\Models\DeliveryOrderReceipt::find($receiptId)?->salesOrder?->down_payment ?? 0.0;
                                        return round($subtotal - $downPayment, 0);
                                    })
                                    ->numeric()
                                                                        ->columnSpan(4),
                            ]),
                    ]),
            ]);
    }

    public static function updateTotals(callable $get, callable $set): void
    {
        $items = $get('items');
        if ($items === null) {
            $rootGet = function(string $path) use ($get) { return $get('../../' . $path); };
            $rootSet = function(string $path, $val) use ($set) { return $set('../../' . $path, $val); };
            $items = $rootGet('items') ?? [];
        } else {
            $rootGet = $get;
            $rootSet = $set;
        }

        $totalWeight = 0.0;
        $subtotal = 0.0;
        $totalDiscount = 0.0;

        foreach ($items as $key => $item) {
            $weightStr = (string)($item['weight'] ?? '0');
            $weight = (float) str_replace(',', '.', $weightStr);

            $priceStr = (string)($item['price'] ?? '0');
            $priceStr = str_replace('.', '', $priceStr);
            $price = (float) str_replace(',', '.', $priceStr);

            $discPercentStr = (string)($item['discount_percent'] ?? '0');
            $discPercentStr = str_replace('.', '', $discPercentStr);
            $discPercent = (float) str_replace(',', '.', $discPercentStr);

            $gross = $weight * $price;
            $discRp = round($gross * ($discPercent / 100), 0);
            $amount = round($gross - $discRp, 0);

            $totalWeight += $weight;
            $totalDiscount += $discRp;
            $subtotal += $amount;

            $rootSet("items.{$key}.discount_rp", $discRp);
            $rootSet("items.{$key}.amount", $amount);
        }

        $additionalCharges = $rootGet('additionalCharges') ?? [];
        $totalCharges = 0.0;
        foreach ($additionalCharges as $key => $chargeItem) {
            $qtyStr = (string)($chargeItem['qty'] ?? '1');
            $qty = (float) str_replace(',', '.', $qtyStr);
            
            $priceStr = (string)($chargeItem['price'] ?? $chargeItem['amount'] ?? '0');
            $price = (float) str_replace(',', '.', $priceStr);
            
            $discPercentStr = (string)($chargeItem['discount_percent'] ?? '0');
            $discPercent = (float) str_replace(',', '.', $discPercentStr);
            
            $gross = $qty * $price;
            $discRp = round($gross * ($discPercent / 100), 0);
            $amount = round($gross - $discRp, 0);
            
            $totalCharges += $amount;
            
            $rootSet("additionalCharges.{$key}.discount_rp", $discRp);
            $rootSet("additionalCharges.{$key}.amount", $amount);
        }
        
        $downPaymentStr = (string)($rootGet('down_payment') ?? '0');
        $downPaymentStr = str_replace('.', '', $downPaymentStr);
        $downPayment = (float) str_replace(',', '.', $downPaymentStr);
        
        $grandTotal = $subtotal + $totalCharges;
        $balance = round($grandTotal - $downPayment, 0);

        $rootSet('total_weight', round($totalWeight, 2));
        $rootSet('subtotal', $grandTotal);
        $rootSet('total_discount', $totalDiscount);
        $rootSet('balance', $balance);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label(__('Invoice Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label(__('Invoice Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_order_number')
                    ->label(__('DO Number'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (!$state) return __('-');
                        preg_match_all('/\d+/', $state, $matches);
                        $digits = implode('', $matches[0]);
                        if (strlen($digits) >= 6) {
                            return substr($digits, -6);
                        }
                        return $state;
                    }),

                Tables\Columns\TextColumn::make('balance')
                    ->label(__('Total Amount'))
                    ->numeric(0, ',', '.')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tukar_faktur')
                    ->label(__('TTF'))
                    ->getStateUsing(function (Invoice $record) {
                        if ($record->customer?->invoice_exchange && is_null($record->invoice_exchange_date)) {
                            return __('TTF');
                        }
                        return null;
                    })
                    ->badge()
                    ->colors([
                        'danger' => 'TTF',
                    ])
                    ->action(
                        Tables\Actions\Action::make('tukar_faktur')
                            ->modalHeading(__('Tukar Faktur'))
                            ->visible(fn () => auth()->user()->hasPermission('tukar_faktur'))
                            ->form([
                                Forms\Components\DatePicker::make('invoice_exchange_date')
                                    ->label(__('Tanggal Tukar Faktur'))
                                    ->required()
                                    ->default(now()),
                                Forms\Components\TextInput::make('exchange_by')
                                    ->label(__('Diserahkan Oleh (PIC)'))
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('exchange_note')
                                    ->label(__('Keterangan (No Resi, Ekspedisi, dll)'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->action(function (Invoice $record, array $data) {
                                // Status WAJIB ikut berpindah ke 'Sudah TF'. Hook saving()
                                // di model Invoice bercabang pada status: selama masih
                                // 'Belum TF' ia memaksa due_date kembali null, sehingga
                                // jatuh tempo yang diisi di sini akan terhapus lagi.
                                //
                                // due_date sengaja tidak dihitung di sini. Cabang
                                // 'Sudah TF' pada hook itulah yang menghitungnya dari
                                // invoice_exchange_date + term_of_payment, jadi rumusnya
                                // cukup hidup di satu tempat.
                                $record->update([
                                    'status' => 'Sudah TF',
                                    'invoice_exchange_date' => $data['invoice_exchange_date'],
                                    'exchange_by' => $data['exchange_by'],
                                    'exchange_note' => $data['exchange_note'],
                                ]);
                            })
                            ->disabled(fn (Invoice $record) => !($record->customer?->invoice_exchange && is_null($record->invoice_exchange_date)) || $record->trashed())
                    ),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_invoices')),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('belum_tukar_faktur')
                    ->label(__('Belum Tukar Faktur'))
                    ->toggle()
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query->whereNull('invoice_exchange_date')->whereHas('customer', fn($q) => $q->where('invoice_exchange', true))),

                Tables\Filters\Filter::make('invoice_date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('date_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['date_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['date_until'] ?? now()->toDateString();

                        return $query
                            ->when($from, fn(Builder $query, $date): Builder => $query->whereDate('invoice_date', '>=', $date))
                            ->when($until, fn(Builder $query, $date): Builder => $query->whereDate('invoice_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['date_from'])->format('d M Y');
                        }
                        if ($data['date_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['date_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([])
            ->headerActions([])
            ->bulkActions([])
            ->recordUrl(function (Invoice $record) {
                if ($record->trashed()) {
                    return Pages\ViewInvoice::getUrl([$record->id]);
                }
                return Pages\EditInvoice::getUrl([$record->id]);
            })
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'detail_list' => Pages\InvoiceDetailList::route('/detail-list'),
            'draft' => Pages\DraftInvoices::route('/draft'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_invoices',
        );
    }
}
