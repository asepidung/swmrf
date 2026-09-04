<?php

namespace App\Filament\Admin\Resources\SalesReturnResource\Pages;

use App\Filament\Admin\Resources\SalesReturnResource;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Product;
use App\Models\Grade;
use App\Models\BeefStock;
use App\Models\TallyItem;
use App\Models\Warehouse;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Facades\DB;
use Filament\Support\Enums\MaxWidth;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Filament\Actions;

class InputReturnItems extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = SalesReturnResource::class;
    
    protected static string $view = 'filament.resources.sales-return-resource.pages.input-return-items';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Lock')
                ->tooltip(__('Lock Return'))
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->hiddenLabel()
                ->requiresConfirmation()
                ->modalHeading(__('Approve Sales Return'))
                ->modalDescription(__('Every item on this return will be put into stock.'))
                // Tombol ini menambah stok, sama persis dengan tombol Approve di
                // halaman Edit -- jadi izinnya pun sama. Rutinnya dulu disalin
                // utuh di TIGA halaman: Edit, View, dan di sini. Yang ini yang
                // terakhir ditemukan, dan satu-satunya yang sama sekali tanpa
                // penjagaan izin.
                ->hidden(fn (): bool => $this->record->status !== 'Draft'
                    || ! (auth()->user()?->hasPermission('approve_sales_returns') ?? false))
                ->action(function (): void {
                    try {
                        $this->record->approve();
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('Return Approved & Stock Updated'))->success()->send();
                    $this->redirect(SalesReturnResource::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('back')
                ->tooltip(__('Back'))
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->hiddenLabel()
                ->url(fn () => SalesReturnResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public SalesReturn $record;
    public ?array $dataScan = [];
    public ?array $dataWeigh = [];
    public string $activeTab = 'scan';

    public function mount(SalesReturn $record): void
    {
        $this->record = $record;

        $defaultPackDate = now()->format('Y-m-d');
        
        $gudangPenerima = $this->receivingWarehouseId();

        $this->scanForm->fill([
            'warehouse_id' => $gudangPenerima,
        ]);

        $this->weighForm->fill([
            'pack_date' => $defaultPackDate,
            // Satu gudang penerima untuk kedua tab. Barang yang dipindai dan
            // barang yang ditimbang ulang datang dari truk yang sama.
            'warehouse_id' => $gudangPenerima,
            'origin_delivery_order_id' => $this->record->delivery_order_id,
            'grade_id' => Grade::where('is_active', true)->orderBy('id')->value('id'),
            'ph_level' => session('sr_ph_level_' . $this->record->id),
            'show_exp' => session('sr_show_exp_' . $this->record->id, true),
            'exp_date' => Carbon::parse($defaultPackDate)->addMonths(3)->format('Y-m-d'),
        ]);
    }

    protected function getForms(): array
    {
        return [
            'scanForm',
            'weighForm',
        ];
    }

    public function scanForm(Form $form): Form
    {
        return $form
            ->schema([
                // Gudang PENERIMA, dan karena itu sebuah pilihan yang terlihat.
                //
                // Sempat diambil diam-diam dari gudang tempat barangnya keluar.
                // Project Owner yang menangkapnya: "kalo diterima dari gudang
                // lain gimana?" -- barang retur tidak selalu mendarat di gudang
                // asalnya, dan yang menentukan adalah orang yang menerimanya,
                // bukan riwayat pengirimannya.
                //
                // Gudang asal tetap dipakai sebagai nilai bawaan karena itu
                // tebakan yang paling mungkin. Bedanya sekarang ia terbaca di
                // layar dan bisa diganti sebelum barcode pertama dipindai.
                Forms\Components\Select::make('warehouse_id')
                    ->label(__('Receiving Warehouse'))
                    ->options(Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($state) => session([
                        'sr_warehouse_'.$this->record->id => $state,
                    ]))
                    ->extraAttributes(['tabindex' => '-1'])
                    ->extraInputAttributes(['tabindex' => '-1']),

                Forms\Components\TextInput::make('barcode')
                    ->hiddenLabel()
                    ->placeholder(__('Scan Barcode Lama (Karton Utuh)'))
                    ->required()
                    ->autofocus()
                    ->extraInputAttributes([
                        'onkeydown' => "if(event.key === 'Enter') { event.preventDefault(); document.getElementById('submit_scan_btn').click(); }"
                    ]),
            ])
            ->statePath('dataScan');
    }

    public function weighForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Select::make('warehouse_id')
                        ->hiddenLabel()
                        ->placeholder(__('Warehouse'))
                        ->options(\App\Models\Warehouse::where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->extraAttributes(['tabindex' => '-1'])
                        ->extraInputAttributes(['tabindex' => '-1']),

                    // Karton yang rusak dan sulit dibaca barcodenya di-barcode
                    // ULANG di tab ini, sehingga barcodenya BARU dan tidak
                    // menunjuk ke kiriman mana pun.
                    //
                    // Selama ini asalnya diambil dari surat jalan yang tertulis
                    // di returnya. Retur lintas pengiriman tidak menyebut surat
                    // jalan apa pun -- dan justru di situlah barang timbang
                    // ulang paling sering muncul, karena pelanggan besar
                    // mengembalikan banyak barang sekaligus. Akibatnya barang
                    // semacam itu berharga NOL tanpa satu pun gejala.
                    //
                    // Jadi asalnya DITANYAKAN. Kosong pun tidak apa-apa: yang
                    // hilang cuma harganya, dan nolnya terbaca di layar.
                    Forms\Components\Select::make('origin_delivery_order_id')
                        ->hiddenLabel()
                        ->placeholder(__('Original Delivery Order'))
                        ->options(fn (): array => $this->originDeliveryOptions())
                        ->searchable()
                        ->preload()
                        ->extraAttributes(['tabindex' => '-1'])
                        ->extraInputAttributes(['tabindex' => '-1']),

                    Forms\Components\Select::make('product_id')
                        ->hiddenLabel()
                        ->placeholder(__('Product'))
                        ->options(Product::orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->autofocus()
                        ->extraAttributes(['class' => 'product-select-container', 'tabindex' => '1'])
                        ->extraInputAttributes(['tabindex' => '1']),

                    Forms\Components\Select::make('grade_id')
                        ->hiddenLabel()
                        ->placeholder(__('Grade'))
                        ->options(Grade::where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn($state, callable $set, callable $get) => \App\Filament\Admin\Resources\RepackResource\Pages\InputHasilRepack::calculateExpiry($get('pack_date'), $state, $set))
                        ->extraAttributes(['tabindex' => '3'])
                        ->extraInputAttributes(['tabindex' => '3']),

                    Forms\Components\DatePicker::make('pack_date')
                        ->hiddenLabel()
                        ->placeholder(__('Pack Date'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn($state, callable $set, callable $get) => \App\Filament\Admin\Resources\RepackResource\Pages\InputHasilRepack::calculateExpiry($state, $get('grade_id'), $set))
                        ->extraAttributes(['tabindex' => '-1'])
                        ->extraInputAttributes(['tabindex' => '-1']),

                    Forms\Components\Hidden::make('exp_date'),

                    Forms\Components\Checkbox::make('show_exp')
                        ->label(__('Tampilkan Tanggal Expired Pada Label'))
                        ->default(false)
                        ->extraAttributes(['tabindex' => '-1']),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('qty_pcs_combined')
                            ->hiddenLabel()
                            ->placeholder(__('Weight/Pcs (e.g. 22.5/8)'))
                            ->required()
                            ->extraInputAttributes([
                                'id' => 'qty_input_field',
                                'tabindex' => '2',
                                'class' => 'text-2xl font-black text-center text-primary-600',
                                'oninput' => "this.value = this.value.replace(/,/g, '.');",
                                'onkeydown' => "if(event.key === 'Enter') { event.preventDefault(); document.getElementById('submit_weigh_btn').click(); }"
                            ]),

                        Forms\Components\TextInput::make('ph_level')
                            ->hiddenLabel()
                            ->numeric()
                            ->step(0.1)
                            ->minValue(5.4)
                            ->maxValue(5.7)
                            ->placeholder(__('PH (5.4 - 5.7)'))
                            ->extraInputAttributes([
                                'tabindex' => '4',
                                'onkeydown' => "if(event.key === ','){ event.preventDefault(); this.value = this.value + '.'; } else if(event.key === 'Enter'){ event.preventDefault(); document.getElementById('submit_weigh_btn').click(); }"
                            ]),
                    ]),
                ])->columns(1),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('submit_weigh')
                            ->label('PRINT & SAVE LABEL')
                            ->color('warning')
                            ->action('processWeigh')
                            ->extraAttributes(['class' => 'w-full'])
                    ])->fullWidth(),
                ]),
            ])
            ->statePath('dataWeigh');
    }

    /**
     * Surat jalan yang pernah dikirim ke pelanggan retur ini.
     *
     * Dibatasi pada pelanggannya sendiri -- barang pelanggan lain tidak pernah
     * menjadi jawaban -- dan yang terbaru lebih dulu, karena retur biasanya
     * menyangkut kiriman yang belum lama.
     *
     * @return array<int, string>
     */
    private function originDeliveryOptions(): array
    {
        return \App\Models\DeliveryOrder::query()
            ->where('customer_id', $this->record->customer_id)
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('delivery_order_number', 'id')
            ->all();
    }

    /**
     * Gudang penerima retur ini.
     *
     * Urutannya: yang sudah dipilih orangnya, lalu gudang tempat barang itu
     * keluar sebagai tebakan awal, lalu gudang aktif pertama sebagai jaring
     * terakhir. Nomor 1 tidak muncul di mana pun.
     */
    private function receivingWarehouseId(): ?int
    {
        $dipilih = session('sr_warehouse_'.$this->record->id);

        if ($dipilih && Warehouse::whereKey($dipilih)->where('is_active', true)->exists()) {
            return (int) $dipilih;
        }

        $gudangAsal = $this->record->deliveryOrder?->tally?->items()->value('warehouse_id');

        return $gudangAsal ? (int) $gudangAsal : $this->defaultWarehouseId();
    }

    /**
     * Gudang yang dipakai kalau tidak ada satu pun petunjuk lain.
     *
     * Gudang pertama yang aktif, bukan angka 1 yang ditulis langsung. Kalau
     * suatu saat gudang berid 1 dinonaktifkan atau terhapus, yang dipaku akan
     * menunjuk ke gudang yang tidak ada -- tanpa satu pun gejala sampai
     * seseorang mencari barangnya.
     */
    private function defaultWarehouseId(): ?int
    {
        return Warehouse::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');
    }

    public function processScan(): void
    {
        $formData = $this->scanForm->getState();
        $barcode = $formData['barcode'];

        try {
            // ------------------------------------------------------------------
            // Tiga pertanyaan yang harus dijawab sebelum sebuah barcode boleh
            // diretur. Keputusan Project Owner, 4 September 2026.
            //
            // Sebelumnya hanya satu yang ditanyakan -- "barcode ini pernah ada
            // di Tally?" -- sehingga barang yang tidak pernah dikirim ke
            // pelanggan ini pun bisa diretur, dan barang yang masih tergeletak
            // di gudang bisa "diretur" lalu masuk stok untuk kedua kalinya.
            // ------------------------------------------------------------------

            // SATU: barang ini benar-benar pernah KITA kirim?
            //
            // Tally adalah penyiapan barang untuk dikirim, dan tiap surat jalan
            // lahir dari satu tally. Jadi pertanyaannya dijawab dengan mencari
            // barcodenya di tally.
            //
            // Yang dicari TIDAK LAGI dibatasi pada tally milik surat jalan
            // retur ini. Project Owner, 4 September 2026: pelanggan sebesar
            // Lion Superindo mengembalikan barang dari BEBERAPA kiriman
            // sekaligus, dan justru untuk itulah retur tanpa surat jalan
            // disediakan. Membatasinya pada satu surat jalan membuat retur
            // semacam itu tidak bisa dipindai sama sekali.
            $tally = $this->record->deliveryOrder?->tally;

            $tallyItem = $tally
                // Kalau returnya MENYEBUT sebuah surat jalan, barangnya wajib
                // dari sana. Penjagaannya tidak dilonggarkan, hanya tidak lagi
                // dipaksakan pada retur yang memang tidak menyebut apa pun.
                ? TallyItem::where('tally_id', $tally->id)->where('barcode', $barcode)->first()
                // Tanpa surat jalan: tally MANA PUN, dan yang diambil yang
                // TERBARU. Barcode unik per tally, bukan global -- satu karton
                // yang pernah diretur lalu dikirim lagi memakai barcode yang
                // sama, dan yang sedang dikembalikan tentu kiriman terakhirnya.
                : TallyItem::where('barcode', $barcode)->orderByDesc('tally_id')->first();

            if (! $tallyItem) {
                throw new \Exception($tally
                    ? __('This barcode was not on the delivery note being returned.')
                    : __('This barcode was never shipped by us.'));
            }

            // DUA: barangnya memang sedang TIDAK di gudang?
            //
            // Barang yang masih tercatat di stok berarti belum pernah keluar --
            // dan sesuatu yang belum keluar tidak bisa kembali. Ini juga yang
            // menahan barcode yang sama diretur dua kali: sesudah retur pertama
            // disetujui, barangnya ada di stok lagi.
            //
            // Kalau kelak barang itu benar-benar dikirim lagi dengan barcode
            // yang sama, ia keluar dari stok lagi, dan returnya boleh -- persis
            // seperti yang seharusnya.
            if (BeefStock::where('barcode', $barcode)->exists()) {
                throw new \Exception(__('This item is still in the warehouse, so it cannot be returned.'));
            }

            // TIGA: belum tercatat di retur lain yang masih berjalan?
            //
            // Dua retur berstatus Draft bisa memegang barcode yang sama tanpa
            // satu pun dari keduanya menyentuh stok, jadi pertanyaan KEDUA tidak
            // menangkapnya. Yang sudah disetujui tidak perlu diperiksa di sini
            // -- barangnya sudah kembali ke gudang dan pertanyaan kedua yang
            // menahannya.
            $adaDiReturLain = SalesReturnItem::where('barcode', $barcode)
                ->whereHas('salesReturn', fn ($q) => $q
                    ->where('id', '!=', $this->record->id)
                    ->where('status', 'Draft'))
                ->exists();

            if ($adaDiReturLain) {
                throw new \Exception(__('This barcode is already on another return that is still a draft.'));
            }
            
            $gudangPenerima = $formData['warehouse_id']
                ?? $tallyItem->warehouse_id
                ?? $this->defaultWarehouseId();

            $productId = $tallyItem->product_id;
            $gradeId = $tallyItem->grade_id;
            $weight = $tallyItem->weight;
            $pcs = $tallyItem->qty_pcs;
            $origin = $tallyItem->origin;

            DB::transaction(function () use ($barcode, $productId, $gradeId, $weight, $pcs, $origin, $tallyItem, $gudangPenerima) {
                // Cek duplikat wajib berada di dalam transaksi dan terkunci, supaya
                // dua scan berbarengan (klik ganda / glitch scanner) tidak sama-sama
                // lolos pengecekan lalu menyisipkan barcode kembar.
                $alreadyScanned = SalesReturnItem::where('sales_return_id', $this->record->id)
                    ->where('barcode', $barcode)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyScanned) {
                    throw new \Exception(__('This barcode has already been scanned on this return.'));
                }

                SalesReturnItem::create([
                    'sales_return_id' => $this->record->id,
                    'product_id' => $productId,
                    // Gudang PENERIMA yang dipilih di layar, bukan gudang asal
                    // barangnya. Lihat catatan di scanForm().
                    'warehouse_id' => $gudangPenerima,
                    'grade_id' => $gradeId,
                    'barcode' => $barcode,
                    'weight' => $weight,
                    'qty_pcs' => $pcs,
                    'ph_level' => $tallyItem->ph_level ?? null,
                    'pack_date' => $tallyItem->pack_date ?? null,
                    'exp_date' => $tallyItem->exp_date ?? null,
                    'origin' => $origin,
                    'is_repacked' => false,
                ]);
            });

            // Hanya barcodenya yang dikosongkan. Mengisi ulang seluruh form
            // akan menghapus gudang penerima yang baru saja dipilih orangnya,
            // dan pindaian berikutnya diam-diam mendarat di gudang lain.
            $this->dataScan['barcode'] = null;
            Notification::make()->title(__('Barcode scanned'))->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();
        }
    }

    public function processWeigh(): void
    {
        $formData = $this->weighForm->getState();
        
        try {
            $qtyInput = $formData['qty_pcs_combined'];
            $parts = explode('/', $qtyInput);
            
            $weight = (float) trim($parts[0]);
            $pcs = isset($parts[1]) ? (int) trim($parts[1]) : 1;

            if ($weight <= 0) {
                throw new \Exception('Berat tidak valid.');
            }

            DB::transaction(function () use ($formData, $weight, $pcs) {
                $origin = '4'; // Repack Return origin (per project rules)
                $dateStr = Carbon::parse($formData['pack_date'])->format('dmy');
                
                $product = Product::find($formData['product_id']);
                $productCode = $product->code ?? '000000';
                if (strlen($productCode) > 6) {
                    $productCode = substr($productCode, 0, 6);
                } else {
                    $productCode = str_pad($productCode, 6, '0', STR_PAD_LEFT);
                }

                $gradeId = $formData['grade_id'];

                $weightStr = str_pad(round($weight * 100), 4, '0', STR_PAD_LEFT);
                $pcsStr = str_pad($pcs, 2, '0', STR_PAD_LEFT);
                $phStr = !empty($formData['ph_level']) ? str_pad(round($formData['ph_level'] * 10), 2, '0', STR_PAD_LEFT) : '00';

                // Urutan barcode barang timbang manual.
                //
                // Dua hal yang sudah pernah diberantas di proyek ini sempat
                // hidup berdampingan di baris ini:
                //
                //  - PANJANG barcode dipakai sebagai penanda sah (`>= 26`).
                //    Project Owner sudah menegaskan tidak semua barcode 26
                //    karakter, jadi panjangnya tidak pernah boleh menjadi
                //    tanda benar atau salah;
                //  - `substr(-4)` memotong tepat empat karakter terakhir,
                //    sehingga urutan ke-10.000 terbaca `0000` dan nomor
                //    berikutnya dihitung 1 lagi -- persis batas yang membuat
                //    `DocumentNumber` dibuat.
                //
                // Sekarang urutannya dibaca dari bagian SESUDAH awalannya,
                // berapa pun panjangnya, dan yang diambil urutan TERBESAR --
                // bukan baris terakhir menurut id, yang bisa saja justru
                // barisnya yang formatnya lain.
                $prefix = $origin . $dateStr;

                $counter = SalesReturnItem::where('barcode', 'like', $prefix . '%')
                    ->lockForUpdate()
                    ->pluck('barcode')
                    ->map(fn (string $lama): int => (int) substr($lama, -4))
                    ->max();

                $counter = ($counter ?? 0) + 1;
                $counterStr = str_pad($counter, 4, '0', STR_PAD_LEFT);

                $barcode = $origin . $dateStr . $productCode . $gradeId . $weightStr . $pcsStr . $phStr . $counterStr;

                $insertedItem = SalesReturnItem::create([
                    'sales_return_id' => $this->record->id,
                    'origin_delivery_order_id' => $formData['origin_delivery_order_id'] ?? null,
                    'product_id' => $formData['product_id'],
                    'warehouse_id' => $formData['warehouse_id'] ?? $this->defaultWarehouseId(),
                    'grade_id' => $gradeId,
                    'barcode' => $barcode,
                    'weight' => $weight,
                    'qty_pcs' => $pcs,
                    'ph_level' => $formData['ph_level'] ?? null,
                    'pack_date' => $formData['pack_date'],
                    'exp_date' => $formData['exp_date'],
                    'origin' => $origin,
                    'is_repacked' => true,
                ]);

                // Auto Print
                $showExp = $formData['show_exp'] ?? false;
                $printUrl = route('sales-return.label', [
                    'id' => $insertedItem->id,
                    'show_exp' => $showExp ? 1 : 0
                ]);
                $this->dispatch('auto-print', url: $printUrl);
            });

            // Auto Print handled inside transaction but showExp is needed here too
            $showExp = $formData['show_exp'] ?? false;

            // Set sessions only for ph_level and show_exp, matching Boning exactly
            session([
                'sr_show_exp_' . $this->record->id => $showExp,
                'sr_ph_level_' . $this->record->id => $formData['ph_level'] ?? null,
                'sr_warehouse_' . $this->record->id => $formData['warehouse_id'] ?? null,
            ]);

            $this->weighForm->fill([
                'warehouse_id' => $formData['warehouse_id'] ?? null,
                'origin_delivery_order_id' => $formData['origin_delivery_order_id'] ?? null,
                'product_id' => $formData['product_id'] ?? null,
                'grade_id' => $formData['grade_id'] ?? null,
                'pack_date' => $formData['pack_date'] ?? null,
                'exp_date' => $formData['exp_date'] ?? null,
                'show_exp' => $showExp,
                'ph_level' => $formData['ph_level'] ?? null,
                'qty_pcs_combined' => null,
            ]);
            
            // Explicitly force Livewire to clear it in the state array
            $this->dataWeigh['qty_pcs_combined'] = null;
            
            $this->dispatch('refreshTable');
        } catch (\Exception $e) {
            Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SalesReturnItem::where('sales_return_id', $this->record->id)->latest())
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->weight('bold')
                    ->size('xs')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->size('xs'),
                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->size('xs'),
                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight/Pcs'))
                    ->size('xs')
                    ->formatStateUsing(fn ($record) => number_format($record->weight, 2) . '/' . $record->qty_pcs)
                    ->summarize(
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('')
                            ->using(function ($query) {
                                $wt = (clone $query)->sum('weight');
                                $pcs = (clone $query)->sum('qty_pcs');
                                $val = number_format((float) $wt, 2) . ' / ' . $pcs;
                                return new \Illuminate\Support\HtmlString('<span style="color: #eab308; font-weight: bold; font-size: 0.875rem;">' . $val . '</span>');
                            })
                    ),
                Tables\Columns\TextColumn::make('ph_level')
                    ->label(__('pH'))
                    ->size('xs'),
                Tables\Columns\TextColumn::make('pack_date')
                    ->label(__('POD'))
                    ->date('d/m/Y')
                    ->size('xs'),
                Tables\Columns\TextColumn::make('origin')
                    ->label(__('Origin'))
                    ->alignCenter()
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record || !$record->barcode) return $state;
                        $prefix = substr($record->barcode, 0, 1);
                        $map = [
                            '1' => 'BNG',
                            '2' => 'RSTK',
                            '3' => 'RIMP',
                            '4' => 'RRTN',
                            '5' => 'RTRD',
                            '6' => 'RLBT',
                            '7' => 'TRDL',
                            '8' => 'TRDI',
                        ];
                        return $map[$prefix] ?? $state;
                    })
                    ->badge()
                    ->color('gray'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete'),
            ]);
    }
}
