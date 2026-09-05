<?php

namespace App\Filament\Admin\Resources\DeliveryOrderResource\Pages;

use App\Filament\Admin\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use App\Models\TallyItem;
use App\Models\DeliveryOrderReceipt;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ApproveDeliveryOrder extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    /**
     * Halaman ini MENYETUJUI pengiriman: tanda terimanya terbit dan stoknya
     * bergerak. Tidak boleh terbuka lewat alamatnya saja.
     *
     * Bentuk yang sama ditemukan pada empat halaman persetujuan permintaan --
     * izinnya ada dan diperiksa untuk menampilkan TAUTANNYA, tetapi halamannya
     * sendiri tidak memeriksa apa pun.
     */
    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isProgrammer()
            || (auth()->user()?->hasPermission('approve_delivery_orders') ?? false);
    }

    protected static string $resource = DeliveryOrderResource::class;

    protected static string $view = 'filament.admin.resources.delivery-order-resource.pages.approve-delivery-order';

    public $record;

    public ?array $data = [];

    /**
     * Filament SUDAH menyerahkan modelnya, bukan angka id.
     *
     * `DeliveryOrder::findOrFail($record)` dengan objek sebagai id tidak
     * pernah menemukan apa pun, dan findOrFail menjawab 404 -- tanpa satu
     * baris pun di log, karena Laravel tidak melaporkan
     * `ModelNotFoundException`.
     *
     * Pengujian halaman ini hijau selama ini karena `Livewire::test()`
     * menyerahkan ANGKA, jalur yang tidak pernah dilalui peramban. Lihat
     * ResourcePageRecordBindingTest.
     */
    public function mount(int|string|DeliveryOrder $record): void
    {
        $this->record = $record instanceof DeliveryOrder
            ? $record
            : DeliveryOrder::findOrFail($record);

        if ($this->record->status !== 'Ready') {
            Notification::make()
                ->title(__('Only a Delivery Order with status Ready can be approved'))
                ->danger()
                ->send();

            $this->redirect(DeliveryOrderResource::getUrl('edit', ['record' => $this->record->id]));
            return;
        }

        $this->fillForm();
    }

    public function fillForm(): void
    {
        $this->record->load('items');

        $items = [];
        foreach ($this->record->items as $item) {
            $items[] = [
                'product_id' => $item->product_id,
                'shipped_box' => $item->box,
                'shipped_weight' => (float)$item->weight,
                'box' => $item->box,
                'weight' => (float)$item->weight,
                'notes' => $item->notes,
            ];
        }

        $this->form->fill([
            'receipt_items' => $items,
            'receipt_note' => $this->record->note,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Receiving Check'))
                    ->schema([
                        Forms\Components\Repeater::make('receipt_items')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label(__('Product'))
                                    ->options(\App\Models\Product::pluck('name', 'id'))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('shipped_weight')
                                    ->label(__('Shipped Weight'))
                                    ->disabled()
                                    ->numeric()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->columnSpan(2),

                                // Satu-satunya isian yang benar-benar diketik
                                // di halaman ini, dan angkanya menentukan
                                // kerugian susut kirim: selisihnya terhadap
                                // berat kirim dicatat sebagai Financial Loss.
                                //
                                // Karena itu ->numeric() dilepas. Pemanggilan
                                // itu membuat input menjadi type=number lengkap
                                // dengan tombol panah, dan satu sentuhan yang
                                // tidak disengaja di sini menggeser angka
                                // kerugian tanpa ada yang menyadarinya.
                                // Keputusan yang sama sudah diambil untuk berat
                                // karkas, berat sapi masuk, pH, dan TOP.
                                Forms\Components\TextInput::make('weight')
                                    ->label(__('Received Weight'))
                                    ->required()
                                    ->extraInputAttributes(['inputmode' => 'decimal', 'class' => 'text-right'])
                                    ->rules(['numeric', 'min:0'])
                                    ->validationMessages([
                                        'numeric' => __('Received weight must be a number.'),
                                        'min' => __('Received weight cannot be negative.'),
                                    ])
                                    ->columnSpan(2),

                                // Satu produk = satu baris. Sebelumnya Notes
                                // memakai dua belas kolom sehingga selalu
                                // turun sendiri, dan satu baris produk
                                // memakan dua baris layar.
                                Forms\Components\TextInput::make('notes')
                                    ->label(__('Notes'))
                                    ->columnSpan(4),
                            ])
                            ->columns(12)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement()
                            ->hiddenLabel(),

                        Forms\Components\Textarea::make('receipt_note')
                            ->label(__('Receipt Note'))
                            ->placeholder(__('Receiving Note'))
                            ->columnSpanFull(),
                    ])
            ])
            ->statePath('data');
    }

    /**
     * Pilihan karton untuk daftar tolakan.
     *
     * Label dan keterangannya dibangun dari SATU kueri yang sama. Kalau
     * masing-masing mengambil sendiri, daftar ratusan karton itu dibaca dua
     * kali setiap kali modalnya digambar ulang -- dan CheckboxList
     * menggambar ulang setiap kali satu kotak dicentang.
     *
     * Relasi produknya ikut dimuat. Tanpa itu, satu kueri tambahan menembak
     * untuk SETIAP karton.
     *
     * @return array{labels: array<string, string>, barcodes: array<string, string>}
     */
    protected static function rejectionOptions(DeliveryOrder $record): array
    {
        static $cache = [];

        if (isset($cache[$record->id])) {
            return $cache[$record->id];
        }

        $labels = [];
        $barcodes = [];

        $items = $record->tally?->items()->with('product')->get() ?? collect();

        foreach ($items as $item) {
            $labels[$item->barcode] = ($item->product?->name ?? '-')
                .' — '.number_format($item->weight, 2).' kg';

            // Barcode menjadi keterangan, bukan bagian label: Filament
            // merendernya dengan huruf lebih kecil dan redup, sehingga
            // barisnya rata dan yang terbaca lebih dulu adalah produknya.
            $barcodes[$item->barcode] = $item->barcode;
        }

        return $cache[$record->id] = ['labels' => $labels, 'barcodes' => $barcodes];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('submit_header')
                ->label(__('Approve DO'))
                ->color('success')
                ->action(fn () => $this->submit()),

            Actions\Action::make('rejections')
                ->label(__('Rejections'))
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->modalWidth('4xl')
                ->modalHeading(__('Return Rejected Goods to Stock'))
                ->form([
                    Forms\Components\TextInput::make('barcode_scan')
                        ->label(__('Scan Barcode'))
                        ->placeholder(__('Scan the rejected barcode here...'))
                        ->autofocus()
                        ->extraAttributes([
                            'onkeydown' => 'if (event.key === "Enter") { event.preventDefault(); document.getElementById("add-barcode-btn")?.click(); }'
                        ])
                        ->suffixAction(
                            // Alat pemindai menekan Enter sendiri, dan Enter
                            // sudah memicu tombol ini. Jadi tombolnya hanya
                            // untuk yang mengetik barcode manual lalu
                            // mengklik -- diberi tooltip supaya gunanya tidak
                            // perlu ditebak.
                            Forms\Components\Actions\Action::make('add_barcode')
                                ->icon('heroicon-m-plus')
                                ->tooltip(__('Add the typed barcode to the list. A scanner does this by itself.'))
                                ->extraAttributes(['id' => 'add-barcode-btn'])
                                ->action(function (Forms\Set $set, Forms\Get $get) {
                                    $barcode = trim($get('barcode_scan'));
                                    if (!$barcode) return;

                                    $tallyItems = $this->record->tally?->items ?? collect();
                                    $matchingItem = $tallyItems->firstWhere('barcode', $barcode);
                                    if ($matchingItem) {
                                        $rejected = $get('rejected_barcodes') ?? [];
                                        if (!in_array($barcode, $rejected)) {
                                            $rejected[] = $barcode;
                                            $set('rejected_barcodes', $rejected);

                                            Notification::make()
                                                ->title(__('Barcode ticked'))
                                                ->success()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title(__('Barcode already ticked'))
                                                ->warning()
                                                ->send();
                                        }
                                    } else {
                                        Notification::make()
                                            ->title(__('Barcode not found in this Tally'))
                                            ->danger()
                                            ->send();
                                    }
                                    $set('barcode_scan', '');
                                })
                        ),

                    // Dihitung di satu tempat saja. Dulu kalimatnya juga
                    // ditulis ulang di dua pemanggilan $set yang sebenarnya
                    // tidak berguna -- isi Placeholder memang dihitung ulang
                    // sendiri setiap render.
                    Forms\Components\Placeholder::make('scanned_count_placeholder')
                        ->label('')
                        ->content(fn (Forms\Get $get) => new \Illuminate\Support\HtmlString(
                            "<div class='text-lg font-bold text-warning-600 dark:text-warning-400'>"
                            .e(__(':count box selected', ['count' => count($get('rejected_barcodes') ?? [])]))
                            .'</div>'
                        )),

                    // Satu tally bisa berisi RATUSAN karton, jadi daftar
                    // centang polos tidak terpakai: barcode 26 karakter
                    // membuat barisnya melipat, dan tidak ada cara mencari.
                    //
                    // Tiga hal membuatnya kembali bisa dibaca:
                    //
                    //  - barcode turun menjadi keterangan di bawah label,
                    //    dirender Filament dengan huruf lebih kecil dan
                    //    redup, sehingga kolomnya rata dan yang menonjol
                    //    adalah nama produk dan beratnya;
                    //  - kotak pencarian, satu-satunya cara masuk akal
                    //    menemukan satu karton di antara ratusan;
                    //  - centang massal, untuk menolak seluruh kiriman
                    //    tanpa menekan ratusan kotak.
                    //
                    // Tingginya dibatasi lewat style langsung, bukan kelas
                    // Tailwind: panel ini tidak memuat hasil build CSS
                    // aplikasi, sehingga kelas sembarang bisa tidak
                    // menghasilkan apa pun tanpa satu pun error.
                    Forms\Components\CheckboxList::make('rejected_barcodes')
                        ->label(__('Select Rejected Barcode'))
                        ->options(fn (): array => static::rejectionOptions($this->record)['labels'])
                        ->descriptions(fn (): array => static::rejectionOptions($this->record)['barcodes'])
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(2)
                        ->extraAttributes(['style' => 'max-height: 22rem; overflow-y: auto;'])
                        ->live(),
                ])
                ->action(function (array $data) {
                    $barcodes = $data['rejected_barcodes'] ?? [];
                    if (empty($barcodes)) {
                        Notification::make()
                            ->title(__('No item selected'))
                            ->warning()
                            ->send();
                        return;
                    }

                    DB::transaction(function () use ($barcodes) {
                        TallyItem::where('tally_id', $this->record->tally_id)
                            ->whereIn('barcode', $barcodes)
                            ->get()
                            ->each->delete();

                        $this->record->syncItemsFromTally();
                    });

                    Notification::make()
                        ->title(__('Rejection processed'))
                        ->body(__('The rejected goods have been returned to stock.'))
                        ->success()
                        ->send();

                    $this->fillForm();
                }),

            Actions\Action::make('cancel_header')
                ->label(__('Cancel'))
                ->color('gray')
                ->url(fn () => DeliveryOrderResource::getUrl('edit', ['record' => $this->record->id])),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data) {
            // Kedua awalan diambil dari model, bukan diketik ulang di sini.
            // Kalau awalannya berubah dan salinan di sini tertinggal,
            // penggantiannya tidak menemukan apa pun dan nomor resi menjadi
            // sama persis dengan nomor DO -- tanpa error.
            $receiptNumber = str_replace(
                DeliveryOrder::NUMBER_PREFIX,
                DeliveryOrder::RECEIPT_NUMBER_PREFIX,
                $this->record->delivery_order_number,
            );

            $doItemsList = $this->record->items->values();

            $totalBox = 0;
            $totalWeight = 0;
            $index = 0;
            foreach ($data['receipt_items'] as $key => $item) {
                $doItem = $doItemsList[$index] ?? null;
                $productId = $item['product_id'] ?? ($doItem ? $doItem->product_id : null);
                $boxCount = $item['box'] ?? ($doItem ? $doItem->box : 0);
                
                $data['receipt_items'][$key]['product_id'] = $productId;
                $data['receipt_items'][$key]['box'] = $boxCount;
                
                $totalBox += $boxCount;
                $totalWeight += (float)$item['weight'];
                $index++;
            }

            $receipt = DeliveryOrderReceipt::updateOrCreate(
                [
                    'receipt_number' => $receiptNumber,
                ],
                [
                    'delivery_order_id' => $this->record->id,
                    'sales_order_id' => $this->record->sales_order_id,
                    'customer_id' => $this->record->customer_id,
                    'delivery_date' => $this->record->delivery_date,
                    'po_number' => $this->record->po_number,
                    'note' => $data['receipt_note'] ?? null,
                    'total_box' => $totalBox,
                    'total_weight' => $totalWeight,
                    'status' => 'Approved',
                    'created_by' => auth()->id(),
                ]
            );

            $receipt->items()->delete();

            foreach ($data['receipt_items'] as $item) {
                $receipt->items()->create([
                    'product_id' => $item['product_id'],
                    'box' => $item['box'],
                    'weight' => $item['weight'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $this->record->update(['status' => 'Approved']);

            // Calculate financial loss for qty adjustments (shipped_weight vs weight)
            $doItems = $this->record->items->keyBy('product_id');
            $totalLossWeight = 0.0;
            foreach ($data['receipt_items'] as $item) {
                $productId = $item['product_id'];
                $receivedWeight = (float)$item['weight'];
                $shippedWeight = isset($doItems[$productId]) ? (float)$doItems[$productId]->weight : 0.0;
                
                if ($receivedWeight < $shippedWeight) {
                    $totalLossWeight += ($shippedWeight - $receivedWeight);
                }
            }

            if ($totalLossWeight > 0) {
                // Kilogramnya masuk ke KOLOMNYA SENDIRI, bukan cuma ke dalam
                // kalimat catatan.
                //
                // Dulu angka itu hanya hidup di dalam teks 'sebesar 12,50 Kg',
                // sehingga laporan kerugian menampilkan Rp 0 dan kilogramnya
                // tidak bisa dijumlah, disaring, atau diurut -- hanya bisa
                // dibaca satu per satu.
                //
                // `amount` MASIH nol, dan itu disengaja. Menilai susut kirim
                // dengan harga jual melebih-lebihkan: perusahaan tidak
                // kehilangan sebesar harga jual, melainkan sebesar modalnya
                // ditambah margin yang tidak jadi didapat. Angka yang benar
                // HPP, dan HPP menunggu B.O.M. Saat itu tiba, rupiahnya
                // tinggal `quantity x HPP` dari kolom ini -- tanpa menggali
                // ulang ratusan catatan lama.
                $this->record->financialLoss()->updateOrCreate(
                    [
                        'transaction_type' => 'Delivery Order',
                        'reference_number' => $this->record->delivery_order_number,
                    ],
                    [
                        'date' => $this->record->delivery_date,
                        'amount' => 0.00,
                        'quantity' => round($totalLossWeight, 2),
                        'unit' => 'Kg',
                        'note' => __('Delivery shrinkage on :document', [
                            'document' => $this->record->delivery_order_number,
                        ]),
                    ]
                );
            } else {
                $this->record->financialLoss()->delete();
            }

            if ($this->record->salesOrder) {
                $this->record->salesOrder->update(['status' => \App\Models\SalesOrder::STATUS_COMPLETED]);
            }
        });

        Notification::make()
            ->title(__('Delivery Order Approved & Receipt Created'))
            ->success()
            ->send();

        $this->redirect(DeliveryOrderResource::getUrl('index'));
    }
}
