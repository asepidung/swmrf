<?php

namespace App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptProductResource;
use App\Models\GoodsReceiptProduct;
use App\Models\GoodsReceiptProductItem;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Grade;
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
use Illuminate\Support\Facades\Auth;
use Filament\Support\Enums\MaxWidth;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class LabelingGoodsReceiptProduct extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = GoodsReceiptProductResource::class;
    protected static string $view = 'filament.admin.resources.goods-receipt-product-resource.pages.labeling-goods-receipt-product';

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function getHeading(): string
    {
        return '';
    }

    public GoodsReceiptProduct $record;
    public ?array $data = [];

    public function mount(GoodsReceiptProduct $record): void
    {
        $this->record = $record;

        $defaultPackDate = now()->format('Y-m-d');
        $defaultGrade = 1;

        $poProductIds = $this->record->purchaseProduct->items()->pluck('product_id')->toArray();
        $sessionProductId = session('gr_product_id');
        $productId = in_array($sessionProductId, $poProductIds) ? $sessionProductId : null;

        $this->form->fill([
            // TANPA cadangan. Cadangan 1 membuat Jonggol terpilih sendiri
            // sebelum pengguna memilih apa pun, dan label pertama hari itu
            // bisa tercetak untuk gudang yang salah tanpa ada yang menyadari.
            // Sesudah dipilih sekali, sesi yang mengingatnya.
            'warehouse_id' => session('gr_warehouse_id'),
            'origin' => session('gr_origin'),
            'product_id' => $productId,
            'grade_id' => session('gr_grade_id', $defaultGrade),
            'pack_date' => session('gr_pack_date', $defaultPackDate),
            'ph_level' => session('gr_ph_level'),
            'show_exp' => session('gr_show_exp', false),
            'exp_date' => Carbon::parse(session('gr_pack_date', $defaultPackDate))->addMonths(3)->format('Y-m-d'),
        ]);
    }

    /**
     * Aturannya satu rumah: `ShelfLife`.
     *
     * Sebelumnya berkas ini memuat salinannya sendiri, dan keempat salinan di
     * aplikasi ini tidak sama isinya -- satu karton grade A bisa mendapat
     * tanggal kedaluwarsa berbeda semata karena pintu mana yang dilewatinya.
     *
     * Dipertahankan sebagai pembungkus tipis karena namanya sudah dipanggil
     * dari beberapa berkas, termasuk lintas modul.
     */
    public static function calculateExpiry($packDate, $gradeId, callable $set)
    {
        \App\Support\ShelfLife::fill($packDate, $gradeId, $set);
    }

    public function form(Form $form): Form
    {
        // Get only the products present in this PO
        $productIds = $this->record->purchaseProduct->items()->pluck('product_id');
        $productOptions = Product::whereIn('id', $productIds)->orderBy('name')->pluck('name', 'id');

        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->hiddenLabel()
                            ->placeholder(__('Warehouse'))
                            ->options(Warehouse::pluck('name', 'id'))
                            ->required()
                            ->extraAttributes(['tabindex' => '-1'])
                            ->extraInputAttributes(['tabindex' => '-1']),

                        Forms\Components\Select::make('origin')
                            ->hiddenLabel()
                            ->placeholder(__('Origin'))
                            ->options([
                                '7' => 'TRADING',
                                '8' => 'IMPORT',
                            ])
                            ->required()
                            ->extraAttributes(['tabindex' => '-1'])
                            ->extraInputAttributes(['tabindex' => '-1']),

                        Forms\Components\Select::make('product_id')
                            ->hiddenLabel()
                            ->placeholder(__('Product'))
                            ->options($productOptions)
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
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => self::calculateExpiry($get('pack_date'), $state, $set))
                            ->extraAttributes(['tabindex' => '3'])
                            ->extraInputAttributes(['tabindex' => '3']),

                        Forms\Components\Hidden::make('exp_date'),

                        // Tanggal kemas dan centang exp dijadikan satu baris:
                        // keduanya pendek, dan sebelumnya masing-masing
                        // memakan barisnya sendiri.
                        //
                        // Kolomnya RESPONSIF -- satu kolom di layar kecil, dua
                        // kolom mulai layar sedang. Memakai Grid milik Filament,
                        // bukan kelas Tailwind yang ditulis sendiri: panel ini
                        // tidak memuat hasil build CSS aplikasi, sehingga kelas
                        // sembarang bisa tidak menghasilkan apa pun. Grid
                        // Filament membawa CSS-nya sendiri.
                        Forms\Components\Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                Forms\Components\DatePicker::make('pack_date')
                                    ->hiddenLabel()
                                    ->placeholder(__('Pack Date'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn($state, callable $set, callable $get) => self::calculateExpiry($state, $get('grade_id'), $set))
                                    ->extraAttributes(['tabindex' => '-1'])
                                    ->extraInputAttributes(['tabindex' => '-1']),

                                // Labelnya dipendekkan supaya muat di samping
                                // tanggal tanpa mendorong barisnya melipat.
                                Forms\Components\Checkbox::make('show_exp')
                                    ->label(__('Show Expiry'))
                                    ->default(false)
                                    ->dehydrated(false)
                                    ->extraAttributes(['tabindex' => '-1']),
                            ]),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('qty_pcs_combined')
                                ->hiddenLabel()
                                ->placeholder(__('Weight/Pcs (e.g. 22.5/8)'))
                                ->required()
                                ->extraInputAttributes([
                                    'id' => 'qty_input_field',
                                    'tabindex' => '2',
                                    'class' => 'text-2xl font-black text-right text-primary-600',
                                    'oninput' => "this.value = this.value.replace(/,/g, '.');",
                                    'onkeydown' => "if(event.key === 'Enter') { event.preventDefault(); document.getElementById('submit_btn_label').click(); }"
                                ]),

                            // pH ikut masuk ke barcode, jadi digit yang salah
                            // berarti barcode yang salah arti -- dan barcode
                            // itu terbawa ke seluruh modul sesudahnya.
                            //
                            // Rentangnya cuma 5,4 sampai 5,7 dengan langkah
                            // 0,1, sehingga satu sentuhan tombol panah
                            // menggeser nilainya tanpa terasa. ->numeric()
                            // dilepas karena itulah yang memunculkan
                            // panahnya; aturannya ditulis manual supaya
                            // batasnya tetap terjaga.
                            //
                            // Keputusan yang sama sudah diambil untuk pH di
                            // modul Boning.
                            Forms\Components\TextInput::make('ph_level')
                                ->hiddenLabel()
                                ->rules(['numeric', 'min:5.4', 'max:5.7'])
                                ->validationMessages([
                                    'numeric' => __('pH must be a number.'),
                                    'min' => __('pH cannot be lower than 5.4.'),
                                    'max' => __('pH cannot be higher than 5.7.'),
                                ])
                                ->placeholder(__('PH (5.4 - 5.7)'))
                                ->required()
                                ->extraInputAttributes([
                                    'tabindex' => '4',
                                    'onkeydown' => "if(event.key === ','){ event.preventDefault(); this.value = this.value + '.'; } else if(event.key === 'Enter'){ event.preventDefault(); document.getElementById('submit_btn_label').click(); }"
                                ]),
                        ]),
                    ])->columns(1)
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(GoodsReceiptProductItem::query()->where('goods_receipt_product_id', $this->record->id))
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50, 'all'])
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->weight('bold')
                    ->size('sm')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center'])
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->size('sm')
                    ->alignLeft()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center'])
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn($record) => route('goods-receipt-product.label', [
                        'id' => $record->id,
                        'show_exp' => session('gr_show_exp', false) ? 1 : 0
                    ]))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight'))
                    ->size('sm')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center'])
                    ->searchable()
                    ->formatStateUsing(fn($state) => number_format((float) $state, 2, '.', ',')),

                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center'])
                    ->badge()
                    ->color(fn($state) => in_array($state, ['CHILL', 'A']) ? 'info' : 'danger'),

                Tables\Columns\TextColumn::make('qty_pcs')
                    ->label(__('Pcs'))
                    ->size('sm')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center']),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Time'))
                    ->time('H:i:s')
                    ->size('sm')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label(__('Product'))
                    ->options(Product::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('grade_id')
                    ->label(__('Grade'))
                    ->options(Grade::where('is_active', true)->pluck('name', 'id'))
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-m-trash')
                    ->hiddenLabel()
                    ->color('danger')
                    ->tooltip(__('Delete Label'))
                    ->requiresConfirmation()
                    ->action(function (GoodsReceiptProductItem $record) {
                        DB::transaction(function () use ($record) {
                            $stock = BeefStock::where('barcode', $record->barcode)->lockForUpdate()->first();
                            $warehouseId = $stock ? $stock->warehouse_id : 1;

                            BeefStockMovement::create([
                                'product_id' => $record->product_id,
                                'warehouse_id' => $warehouseId,
                                'condition' => $record->grade_id,
                                'barcode' => $record->barcode,
                                'transaction_type' => 'VOID_GR_BEEF',
                                'reference_document' => $this->record->gr_number ?? 'DELETED',
                                'weight_in' => -$record->weight,
                                'pcs_in' => -$record->qty_pcs,
                                'created_by' => Auth::id(),
                            ]);

                            BeefStock::where('barcode', $record->barcode)->delete();
                            $record->delete();
                        });

                        Notification::make()
                            ->title(__('Data voided and removed from stock!'))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function create(): void
    {
        if ($this->record->is_locked) {
            Notification::make()->title(__('Failed'))->body(__('This goods receipt is locked.'))->danger()->send();
            return;
        }

        $showExp = $this->data['show_exp'] ?? false;
        $formData = $this->form->getState();

        try {
            $combinedInput = $formData['qty_pcs_combined'];
            $parts = explode('/', $combinedInput);
            $weight = (float) trim($parts[0]);
            $pcs = isset($parts[1]) && trim($parts[1]) !== '' ? (int) trim($parts[1]) : 1;

            $insertedItem = DB::transaction(function () use ($formData, $weight, $pcs) {
                $origin = $formData['origin']; // 7 or 8
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
                $phStr = isset($formData['ph_level']) ? str_pad(round($formData['ph_level'] * 10), 2, '0', STR_PAD_LEFT) : '00';

                $prefix = $origin . $dateStr;
                // Urutannya satu rumah di `BarcodeSequence`. Bentuk lamanya
                // memakai panjang barcode sebagai penanda sah dan membaca
                // baris TERAKHIR menurut id, bukan urutan TERBESAR.
                $counterStr = \App\Support\BarcodeSequence::nextPadded($prefix, [
                    GoodsReceiptProductItem::withTrashed()->lockForUpdate(),
                ]);

                $barcode = $origin . $dateStr . $productCode . $gradeId . $weightStr . $pcsStr . $phStr . $counterStr;

                // Ambil harga dari PurchaseOrder untuk menghitung subtotal
                $poItem = $this->record->purchaseProduct->items()->where('product_id', $formData['product_id'])->lockForUpdate()->first();
                $price = $poItem ? $poItem->price : 0;
                $subtotal = $price * $weight;

                $item = GoodsReceiptProductItem::create([
                    'goods_receipt_product_id' => $this->record->id,
                    'product_id' => $formData['product_id'],
                    'grade_id' => $gradeId,
                    'weight' => $weight,
                    'qty_pcs' => $pcs,
                    'ph_level' => $formData['ph_level'] ?? null,
                    'pack_date' => $formData['pack_date'],
                    'barcode' => $barcode,
                    'origin' => \App\Helpers\BarcodeHelper::getOrigin($barcode),
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);

                BeefStock::create([
                    'barcode' => $barcode,
                    'product_id' => $formData['product_id'],
                    'warehouse_id' => $formData['warehouse_id'],
                    'grade_id' => $gradeId,
                    'weight' => $weight,
                    'qty_pcs' => $pcs,
                    'ph_level' => $formData['ph_level'] ?? null,
                    'pack_date' => $formData['pack_date'],
                    'exp_date' => $formData['exp_date'],
                    'origin' => \App\Helpers\BarcodeHelper::getOrigin($barcode),
                    'status' => 'IN_STOCK',
                ]);

                BeefStockMovement::create([
                    'product_id' => $formData['product_id'],
                    'warehouse_id' => $formData['warehouse_id'],
                    'condition' => $gradeId,
                    'barcode' => $barcode,
                    'transaction_type' => 'IN_GR_BEEF',
                    'reference_document' => $this->record->gr_number,
                    'weight_in' => $weight,
                    'pcs_in' => $pcs,
                    'created_by' => Auth::id(),
                ]);

                return $item;
            });

            // Save values to session for auto-filling
            session([
                'gr_warehouse_id' => $formData['warehouse_id'],
                'gr_origin' => $formData['origin'],
                'gr_product_id' => $formData['product_id'],
                'gr_grade_id' => $formData['grade_id'],
                'gr_pack_date' => $formData['pack_date'],
                'gr_ph_level' => $formData['ph_level'],
                'gr_show_exp' => $showExp,
            ]);

            Notification::make()->title(__('Successfully Added'))->success()->send();

            $this->form->fill([
                'warehouse_id' => $formData['warehouse_id'],
                'origin' => $formData['origin'],
                'product_id' => $formData['product_id'],
                'grade_id' => $formData['grade_id'],
                'pack_date' => $formData['pack_date'],
                'exp_date' => $formData['exp_date'],
                'ph_level' => $formData['ph_level'],
                'qty_pcs_combined' => null, // Reset weight and pcs
                'show_exp' => $showExp,
            ]);

            $this->dispatch('refreshTable');

            if ($insertedItem) {
                $printUrl = route('goods-receipt-product.label', [
                    'id' => $insertedItem->id,
                    'show_exp' => $showExp ? 1 : 0
                ]);
                $this->dispatch('auto-print', url: $printUrl);
            }
        } catch (\Exception $e) {
            Notification::make()->title(__('Error!'))->body($e->getMessage())->danger()->send();
        }
    }
}
