<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\InvoiceResource\Pages;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\InvoiceTotals;
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
                        // Baris judul kolom, hanya untuk layar lebar.
                        //
                        // Di layar sempit repeaternya menumpuk satu kolom, dan
                        // judul yang berjajar di atasnya kehilangan seluruh
                        // maknanya -- yang tersisa lima label menggantung tanpa
                        // isi. Di sana fieldnya memakai label sendiri.
                        Forms\Components\Grid::make(['default' => 1, 'lg' => 12])
                            ->extraAttributes(['class' => 'swm-wide-only'])
                            ->schema([
                                Forms\Components\Placeholder::make('h_product')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white">Product</div>'))
                                    ->columnSpan(['default' => 'full', 'lg' => 3]),

                                Forms\Components\Placeholder::make('h_weight')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white text-right">Weight (Kg)</div>'))
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),

                                Forms\Components\Placeholder::make('h_price')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white text-right">Price (Rp)*</div>'))
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),

                                Forms\Components\Placeholder::make('h_disc_pct')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white text-right">Disc %</div>'))
                                    ->columnSpan(['default' => 'full', 'lg' => 1]),

                                Forms\Components\Placeholder::make('h_disc_rp')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white text-right">Disc Rp</div>'))
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),

                                Forms\Components\Placeholder::make('h_amount')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div class="text-sm font-medium text-gray-950 dark:text-white text-right">Amount (Rp)</div>'))
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),

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
                                    ->columnSpan(['default' => 'full', 'lg' => 3]),

                                Forms\Components\TextInput::make('weight')
                                    ->hiddenLabel()
                                    ->placeholder(__('Weight (Kg)'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),

                                static::money('price')
                                    ->hiddenLabel()
                                    ->placeholder(__('Price (Rp)'))
                                    ->required()
                                    ->rules(['numeric', 'gte:0'])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set))
                                    ->extraInputAttributes(['class' => 'text-right', 'inputmode' => 'numeric', 'onfocus' => 'this.select()'])
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),

                                Forms\Components\TextInput::make('discount_percent')
                                    ->hiddenLabel()
                                    ->placeholder(__('Disc %'))
                                    ->numeric()
                                    ->default(0)
                                    // Persen bulat, mengikuti seluruh sistem. Aturan
                                    // numeric-nya disebut eksplisit: `min`/`max` tanpa
                                    // itu memeriksa PANJANG TEKS, bukan besar angkanya.
                                    ->rules(['numeric', 'min:0', 'max:100'])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set))
                                    ->extraInputAttributes(['class' => 'text-right', 'onfocus' => 'this.select()'])
                                    ->columnSpan(['default' => 'full', 'lg' => 1]),

                                static::money('discount_rp')
                                    ->hiddenLabel()
                                    ->placeholder(__('Disc Rp'))
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),

                                static::money('amount')
                                    ->hiddenLabel()
                                    ->placeholder(__('Amount (Rp)'))
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),
                             ])
                             ->columns(['default' => 1, 'lg' => 12])
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement()
                            ->hiddenLabel()
                            ->default(fn () => static::billableLines(
                                request()->query('delivery_order_receipt_id'),
                            )),
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
                                    ->columnSpan(['default' => 'full', 'lg' => 3]),
                                Forms\Components\TextInput::make('qty')
                                    ->hiddenLabel()
                                    ->placeholder(__('Qty'))
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->extraInputAttributes(['class' => 'text-right', 'onfocus' => 'this.select()'])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set))
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),
                                static::money('price')
                                    ->hiddenLabel()
                                    ->placeholder(__('Price (Rp)'))
                                    ->required()
                                    ->rules(['numeric', 'gte:0'])
                                    ->extraInputAttributes(['class' => 'text-right', 'inputmode' => 'numeric', 'onfocus' => 'this.select()'])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set))
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),
                                Forms\Components\TextInput::make('discount_percent')
                                    ->hiddenLabel()
                                    ->placeholder(__('Disc %'))
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->columnSpan(['default' => 'full', 'lg' => 1]),
                                static::money('discount_rp')
                                    ->hiddenLabel()
                                    ->placeholder(__('Disc Rp'))
                                    ->default(0)
                                    ->disabled()
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),
                                static::money('amount')
                                    ->hiddenLabel()
                                    ->placeholder(__('Amount (Rp)'))
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->columnSpan(['default' => 'full', 'lg' => 2]),
                            ])
                            ->columns(['default' => 1, 'lg' => 12])
                            ->default([])
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set)),
                    ]),

                Forms\Components\Section::make()
                    ->schema([
                        // Semua angka uang berdiri dalam SATU kolom di kanan,
                        // satu baris masing-masing, dibaca dari atas ke bawah
                        // seperti struk: berat, barang, biaya, diskon, uang
                        // muka, lalu yang benar-benar ditagihkan.
                        //
                        // Sebelumnya empat di antaranya dijejalkan ke satu
                        // baris. Kotaknya jadi terlalu sempit untuk angka
                        // jutaan, dan "1.200.000" terpotong menjadi "1.200.0" --
                        // angka yang salah baca, bukan sekadar tampilan sesak.
                        //
                        // Letak kanannya dipasang dengan columnStart, BUKAN
                        // dengan placeholder kosong sebagai pengganjal. Di
                        // layar sempit gridnya menjadi satu kolom, dan
                        // pengganjal seperti itu berubah menjadi baris kosong
                        // yang ikut memakan tempat.
                        Forms\Components\Grid::make(['default' => 1, 'lg' => 12])
                            ->schema([
                                Forms\Components\TextInput::make('total_weight')
                                    ->label(__('Total Weight'))
                                    ->hiddenLabel()
                                    ->prefix('Total')
                                    ->suffix('Kg')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->default(fn () => \App\Models\DeliveryOrderReceipt::find(request()->query('delivery_order_receipt_id'))?->total_weight ?? 0.0)
                                    ->columnSpan(['default' => 'full', 'lg' => 5])
                                    ->columnStart(['lg' => 8]),

                                static::money('subtotal')
                                    ->hiddenLabel()
                                    // Dulu berlabel "Total Amount" padahal isinya
                                    // kadang barang saja, kadang barang ditambah
                                    // biaya tambahan. Sekarang isinya pasti barang
                                    // saja, dan labelnya mengatakan itu.
                                    ->prefix(__('Products'))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->default(fn () => static::initialTotal('subtotal'))
                                    ->columnSpan(['default' => 'full', 'lg' => 5])
                                    ->columnStart(['lg' => 8]),

                                static::money('charge')
                                    ->hiddenLabel()
                                    ->prefix(__('Charges'))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->default(0)
                                    ->columnSpan(['default' => 'full', 'lg' => 5])
                                    ->columnStart(['lg' => 8]),

                                static::money('total_discount')
                                    ->hiddenLabel()
                                    ->prefix(__('Total Disc'))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->default(fn () => static::initialTotal('total_discount'))
                                    ->columnSpan(['default' => 'full', 'lg' => 5])
                                    ->columnStart(['lg' => 8]),

                                static::money('down_payment')
                                    ->hiddenLabel()
                                    ->prefix('DP')
                                    ->rules(['numeric', 'gte:0'])
                                    ->extraInputAttributes(['class' => 'text-right', 'inputmode' => 'numeric', 'onfocus' => 'this.select()'])
                                    ->default(fn () => (float) (\App\Models\DeliveryOrderReceipt::find(
                                        request()->query('delivery_order_receipt_id')
                                    )?->salesOrder?->down_payment ?? 0))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (callable $get, callable $set) => self::updateTotals($get, $set))
                                    ->columnSpan(['default' => 'full', 'lg' => 5])
                                    ->columnStart(['lg' => 8]),

                                static::money('balance')
                                    ->hiddenLabel()
                                    ->prefix(__('Total Billed'))
                                    ->disabled()
                                    // TIDAK dikirim ke basis data. Kolom ini
                                    // milik Invoice::recalculate() sepenuhnya,
                                    // karena ia juga menampung pembayaran
                                    // pelanggan -- dan form tidak tahu apa-apa
                                    // tentang itu. Selama form ikut menulisnya,
                                    // menyunting invoice yang sudah dicicil
                                    // menghapus pembayarannya.
                                    ->dehydrated(false)
                                    ->default(fn () => static::initialTotal('balance'))
                                    ->columnSpan(['default' => 'full', 'lg' => 5])
                                    ->columnStart(['lg' => 8]),
                            ]),
                    ]),
            ]);
    }

    /**
     * Satu field uang, diformat sama di seluruh halaman ini.
     *
     * Sebelumnya tidak ada satu pun mask uang di form Invoice -- fieldnya cuma
     * `->numeric()`, jadi angkanya tampil polos tanpa pemisah ribuan dan
     * bertombol panah. Padahal `updateTotals()` sudah membuang titik dari
     * harga seolah titik itu pemisah ribuan yang dipasang mask. Pembersih yang
     * menunggu mask yang tidak pernah ada: ketik `1234.56` dan tagihannya
     * seratus kali lipat.
     *
     * Sekarang masknya benar-benar ada, jadi pembersihannya benar, angkanya
     * terbaca, dan koma desimal tidak mungkin lagi masuk.
     *
     * `formatStateUsing` WAJIB. Nilai dari kolom decimal(15,2) berbentuk
     * "1200000.00", dan mask $money membuang seluruh karakter non-digit --
     * dua nol di belakang titik ikut terbaca sebagai digit dan angkanya
     * membengkak seratus kali setiap form dibuka lalu disimpan ulang.
     */
    protected static function money(string $name): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->formatStateUsing(fn ($state): ?string => $state === null || $state === ''
                ? null
                : number_format((float) $state, 0, ',', '.'))
            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
            ->stripCharacters('.')
            ->extraInputAttributes(['class' => 'text-right', 'inputmode' => 'numeric']);
    }

    public static function updateTotals(callable $get, callable $set): void
    {
        $items = $get('items');

        if ($items === null) {
            // Dipanggil dari dalam sebuah baris repeater: naik dua tingkat
            // untuk sampai ke state form-nya.
            $rootGet = fn (string $path) => $get('../../'.$path);
            $rootSet = fn (string $path, $val) => $set('../../'.$path, $val);
            $items = $rootGet('items') ?? [];
        } else {
            $rootGet = $get;
            $rootSet = $set;
        }

        $totalWeight = 0.0;
        $subtotal = 0.0;
        $totalDiscount = 0.0;

        foreach ($items as $key => $item) {
            $weight = InvoiceTotals::number($item['weight'] ?? 0);
            $price = InvoiceTotals::number($item['price'] ?? 0);
            $discount = InvoiceTotals::percent($item['discount_percent'] ?? 0);

            $baris = InvoiceTotals::line($weight, $price, $discount);

            $totalWeight += $weight;
            $totalDiscount += $baris['discount_rp'];
            $subtotal += $baris['amount'];

            $rootSet("items.{$key}.discount_rp", $baris['discount_rp']);
            $rootSet("items.{$key}.amount", $baris['amount']);
        }

        $charge = 0.0;

        foreach ($rootGet('additionalCharges') ?? [] as $key => $item) {
            $qty = InvoiceTotals::number($item['qty'] ?? 1);
            $price = InvoiceTotals::number($item['price'] ?? 0);
            $discount = InvoiceTotals::percent($item['discount_percent'] ?? 0);

            $baris = InvoiceTotals::line($qty, $price, $discount);

            $charge += $baris['amount'];

            $rootSet("additionalCharges.{$key}.discount_rp", $baris['discount_rp']);
            $rootSet("additionalCharges.{$key}.amount", $baris['amount']);
        }

        $downPayment = InvoiceTotals::number($rootGet('down_payment'));

        // subtotal berisi BARANG saja, charge berisi biaya tambahan, dan
        // balance yang menjumlahkan keduanya. Dulu subtotal diisi jumlah
        // keduanya di sini sementara nilai awalnya diisi barang saja, jadi
        // satu kolom berarti dua hal tergantung ada tidaknya yang mengetik.
        $rootSet('total_weight', round($totalWeight, 2));
        $rootSet('subtotal', $subtotal);
        $rootSet('total_discount', $totalDiscount);
        $rootSet('charge', $charge);

        // Hanya untuk ditampilkan. Field balance tidak dikirim ke basis data,
        // karena angka sebenarnya juga memperhitungkan pembayaran pelanggan --
        // dan form tidak tahu apa-apa tentang itu.
        $rootSet('balance', round($subtotal + $charge - $downPayment, 0));
    }

    /**
     * Baris tagihan yang berasal dari satu bukti terima.
     *
     * Dipakai sebagai nilai awal repeater DAN sebagai dasar nilai awal semua
     * kolom totalnya, supaya tidak ada lagi empat salinan rumus yang sama
     * yang bisa berjalan sendiri-sendiri.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function billableLines(?int $receiptId): array
    {
        if (! $receiptId) {
            return [];
        }

        $receipt = \App\Models\DeliveryOrderReceipt::with('items')->find($receiptId);

        if (! $receipt) {
            return [];
        }

        // Harga dan diskon Sales Order diambil sekali untuk seluruh baris.
        // Sebelumnya setiap salinan rumus menembakkan satu kueri per baris,
        // jadi membuka satu invoice dua puluh baris berarti puluhan kueri.
        $soItems = \App\Models\SalesOrderItem::query()
            ->where('sales_order_id', $receipt->sales_order_id)
            ->get()
            ->keyBy('product_id');

        $lines = [];

        foreach ($receipt->items as $item) {
            $soItem = $soItems->get($item->product_id);

            $price = (float) ($soItem->price ?? 0);

            // Diskonnya diambil apa adanya dari Sales Order dan TIDAK ditimpa
            // di sini.
            //
            // Dulu berkas ini memberi 2% kepada pelanggan yang NAMANYA
            // mengandung DCA, DCB, atau DCC, di empat tempat terpisah.
            // Aturannya benar secara bisnis -- tiga Distribution Center Lion
            // Superindo memang disepakati mendapat diskon itu -- tetapi
            // tempatnya keliru, dengan dua akibat:
            //
            //  - SO tertulis 0% sementara invoice menagih 2%, sehingga dokumen
            //    yang dipegang pelanggan tidak cocok dengan tagihan yang
            //    dikirim;
            //  - mengganti nama pelanggan diam-diam mengubah harganya, dan
            //    pelanggan baru yang namanya kebetulan memuat huruf itu ikut
            //    mendapat diskon.
            //
            // Sekarang diskonnya berasal dari kolom customers.default_discount,
            // terisi sendiri saat SO dibuat, dan terlihat di sana. Jangan
            // mengembalikan penggantian di tempat ini.
            $discount = (float) ($soItem->discount ?? 0);

            // Yang ditagih adalah berat pada DO Receipt APA ADANYA, tanpa
            // dibandingkan dengan berat PO.
            //
            // Keputusan bisnis Project Owner: kalau barang yang datang lebih
            // berat daripada PO, penyesuaiannya dilakukan MANUSIA di DO
            // Receipt -- angkanya diturunkan ke berat PO, dan selisihnya
            // tercatat sebagai Financial Loss lewat perbandingan berat kirim
            // lawan berat terima.
            //
            // Membatasinya otomatis di sini justru merusak: penyesuaiannya
            // hilang dari pandangan, dan kerugiannya tidak pernah tercatat.
            // Jangan dipasang lagi.
            $baris = InvoiceTotals::line((float) $item->weight, $price, $discount);

            $lines[] = [
                'product_id' => $item->product_id,
                'box' => $item->box,
                'weight' => $item->weight,
                'price' => $price,
                'discount_percent' => $discount,
                'discount_rp' => $baris['discount_rp'],
                'amount' => $baris['amount'],
            ];
        }

        return $lines;
    }

    /** Nilai awal satu kolom total, dihitung dari baris yang sama. */
    protected static function initialTotal(string $key): float
    {
        $lines = static::billableLines(request()->query('delivery_order_receipt_id'));

        if ($key === 'total_discount') {
            return round(array_sum(array_column($lines, 'discount_rp')), 0);
        }

        $subtotal = round(array_sum(array_column($lines, 'amount')), 0);

        if ($key === 'subtotal') {
            return $subtotal;
        }

        // balance. Biaya tambahan selalu kosong saat form baru dibuka, jadi
        // tidak ada yang perlu dijumlahkan di sini.
        $downPayment = (float) (\App\Models\DeliveryOrderReceipt::find(
            request()->query('delivery_order_receipt_id')
        )?->salesOrder?->down_payment ?? 0);

        return round($subtotal - $downPayment, 0);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'deliveryOrderReceipt:id,delivery_order_id',
                'deliveryOrderReceipt.deliveryOrder:id',
            ]))
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
                    // Dipendekkan menjadi enam digit terakhir atas permintaan
                    // Project Owner; kolomnya jadi terbaca sekilas.
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return __('-');
                        }

                        preg_match_all('/\d+/', $state, $matches);
                        $digits = implode('', $matches[0]);

                        return strlen($digits) >= 6 ? substr($digits, -6) : $state;
                    })
                    // Dan bisa diklik langsung ke halaman cetak surat jalannya,
                    // supaya penagih tidak perlu mencarinya di modul lain.
                    ->url(function (Invoice $record): ?string {
                        $deliveryOrder = $record->deliveryOrderReceipt?->deliveryOrder;

                        return $deliveryOrder
                            ? route('print.delivery-order', $deliveryOrder->id)
                            : null;
                    })
                    ->openUrlInNewTab()
                    ->color(fn (Invoice $record): ?string => $record->deliveryOrderReceipt?->deliveryOrder
                        ? 'primary'
                        : null)
                    ->tooltip(fn (Invoice $record): ?string => $record->deliveryOrderReceipt?->deliveryOrder
                        ? __('Open the delivery order print page')
                        : null),

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
                            ->modalHeading(__('Invoice exchange'))
                            ->visible(fn () => auth()->user()->hasPermission('tukar_faktur'))
                            ->form([
                                Forms\Components\DatePicker::make('invoice_exchange_date')
                                    ->label(__('Invoice exchange date'))
                                    ->required()
                                    ->default(now()),
                                Forms\Components\TextInput::make('exchange_by')
                                    ->label(__('Handed over by (PIC)'))
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('exchange_note')
                                    ->label(__('Description (waybill number, courier, and so on)'))
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
                                    'status' => Invoice::STATUS_EXCHANGED,
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
                    ->label(__('Invoice not exchanged yet'))
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
