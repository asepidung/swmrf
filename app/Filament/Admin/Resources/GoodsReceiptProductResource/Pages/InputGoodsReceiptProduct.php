<?php

namespace App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptProductResource;
use App\Models\GoodsReceiptProduct;
use App\Models\GoodsReceiptProductItem;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\Product;
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
use Filament\Notifications\Notification;
use Carbon\Carbon;

class InputGoodsReceiptProduct extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = GoodsReceiptProductResource::class;

    protected static string $view = 'filament.admin.resources.goods-receipt-product-resource.pages.input-goods-receipt-product';

    public GoodsReceiptProduct $record;
    public ?array $data = [];

    public function mount(GoodsReceiptProduct $record): void
    {
        $this->record = $record;

        $this->form->fill([
            'sj_number' => $record->sj_number,
            'receive_date' => $record->receive_date ?? now()->format('Y-m-d'),
            'note' => $record->note,
            'input_mode' => 'scan',
            'origin' => '7',
            'pack_date' => now()->format('Y-m-d'),
            'grade_id' => 1, // Default CHILL
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Purchase Order & Document Info')
                    ->description('PO Number: ' . ($this->record->purchaseProduct->po_number ?? '-'))
                    ->schema([
                        Forms\Components\TextInput::make('sj_number')
                            ->label('Surat Jalan Number')
                            ->required()
                            ->autofocus(),
                        Forms\Components\DatePicker::make('receive_date')
                            ->label('Receive Date')
                            ->required(),
                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->rows(1)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Input Item')
                    ->schema([
                        Forms\Components\Radio::make('input_mode')
                            ->label('Pilih Metode Input')
                            ->options([
                                'scan' => 'Scan Barcode (Buyback)',
                                'manual' => 'Manual Input & Print Label (Local/Import)',
                            ])
                            ->live()
                            ->default('scan'),

                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\TextInput::make('scan_barcode')
                                    ->label('Scan Barcode')
                                    ->placeholder('Scan 25-character Barcode here...')
                                    ->extraInputAttributes([
                                        'id' => 'scan_barcode_field',
                                        'onkeydown' => "if(event.key === 'Enter') { event.preventDefault(); document.getElementById('submit_btn_gr').click(); }"
                                    ]),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('input_mode') === 'scan'),

                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Select::make('origin')
                                    ->label('Origin')
                                    ->options([
                                        '7' => 'Trading Purchase (Local)',
                                        '8' => 'Import Purchase',
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->placeholder('Select Product')
                                    ->options(Product::whereIn('id', $this->record->purchaseProduct->items->pluck('product_id'))->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Select::make('grade_id')
                                    ->label('Grade')
                                    ->options(Grade::where('is_active', true)->pluck('name', 'id'))
                                    ->required(),

                                Forms\Components\DatePicker::make('pack_date')
                                    ->label('Pack Date')
                                    ->required(),

                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('qty_pcs_combined')
                                        ->label('Weight/Pcs')
                                        ->placeholder('Weight/Pcs (e.g. 22.5/8)')
                                        ->extraInputAttributes([
                                            'id' => 'qty_input_field',
                                            'oninput' => "this.value = this.value.replace(/,/g, '.');",
                                            'onkeydown' => "if(event.key === 'Enter') { event.preventDefault(); document.getElementById('submit_btn_gr').click(); }"
                                        ]),

                                    Forms\Components\TextInput::make('ph_level')
                                        ->label('PH Level')
                                        ->numeric()
                                        ->step(0.1)
                                        ->minValue(5.4)
                                        ->maxValue(5.7)
                                        ->placeholder('PH (5.4 - 5.7)')
                                        ->extraInputAttributes([
                                            'onkeydown' => "if(event.key === 'Enter') { event.preventDefault(); document.getElementById('submit_btn_gr').click(); }"
                                        ]),
                                ]),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('input_mode') === 'manual')
                            ->columns(1),
                    ])
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(GoodsReceiptProductItem::query()->where('goods_receipt_product_id', $this->record->id))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product')),
                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight (Kg)'))
                    ->numeric(2, ',', '.'),
                Tables\Columns\TextColumn::make('qty_pcs')
                    ->label(__('Pcs'))
                    ->numeric(),
                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->badge()
                    ->color(fn ($state) => str_contains(strtolower($state), 'chill') ? 'info' : 'danger'),
                Tables\Columns\TextColumn::make('origin')
                    ->label(__('Origin')),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Price'))
                    ->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('subtotal')
                    ->label(__('Subtotal'))
                    ->money('IDR', locale: 'id'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Void / Hapus Item')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {
                            // Log void movement
                            BeefStockMovement::create([
                                'product_id' => $record->product_id,
                                'warehouse_id' => 1,
                                'condition' => $record->grade_id,
                                'barcode' => $record->barcode,
                                'transaction_type' => 'VOID_GR_BEEF',
                                'reference_document' => $this->record->gr_number,
                                'weight_in' => -$record->weight,
                                'pcs_in' => -$record->qty_pcs,
                                'created_by' => auth()->id(),
                                'note' => 'Void item GR Beef ' . $this->record->gr_number,
                            ]);

                            // Delete stock
                            BeefStock::where('barcode', $record->barcode)->delete();

                            // Delete GR item
                            $record->delete();
                        });

                        Notification::make()->title('Item berhasil dihapus dari penerimaan!')->success()->send();
                        $this->dispatch('refreshTable');
                    }),
            ]);
    }

    public function create(): void
    {
        $formData = $this->form->getState();
        $inputMode = $formData['input_mode'] ?? 'scan';

        if ($inputMode === 'scan') {
            $this->processScan($formData);
        } else {
            $this->processManual($formData);
        }
    }

    protected function processScan(array $formData): void
    {
        $barcode = trim($formData['scan_barcode'] ?? '');
        if (empty($barcode)) {
            Notification::make()->title('Barcode kosong!')->danger()->send();
            return;
        }

        if (strlen($barcode) !== 25) {
            Notification::make()->title('Panjang Barcode harus 25 karakter!')->danger()->send();
            return;
        }

        $existsInGr = GoodsReceiptProductItem::where('goods_receipt_product_id', $this->record->id)
            ->where('barcode', $barcode)
            ->exists();
        if ($existsInGr) {
            Notification::make()->title('Barcode sudah di-scan di GR ini!')->danger()->send();
            return;
        }

        $existsInStock = BeefStock::where('barcode', $barcode)->exists();
        if ($existsInStock) {
            Notification::make()->title('Barcode sudah ada di stok aktif!')->danger()->send();
            return;
        }

        // Parse barcode
        $originCode = substr($barcode, 0, 1);
        $dateStr = substr($barcode, 1, 6);
        $productCode = substr($barcode, 7, 6);
        $gradeId = (int) substr($barcode, 13, 1);
        $weight = ((float) substr($barcode, 14, 4)) / 100;
        $pcs = (int) substr($barcode, 18, 2);
        $ph = ((float) substr($barcode, 20, 2)) / 10;
        if ($ph == 0) {
            $ph = null;
        }

        $product = Product::where('code', $productCode)
            ->orWhere('code', ltrim($productCode, '0'))
            ->first();
        if (!$product) {
            Notification::make()->title("Produk dengan kode {$productCode} tidak ditemukan!")->danger()->send();
            return;
        }

        $grade = Grade::find($gradeId);
        if (!$grade) {
            Notification::make()->title("Grade dengan ID {$gradeId} tidak ditemukan!")->danger()->send();
            return;
        }

        $poItem = $this->record->purchaseProduct->items()->where('product_id', $product->id)->first();
        if (!$poItem) {
            Notification::make()->title("Produk {$product->name} tidak ada dalam PO ini!")->danger()->send();
            return;
        }

        try {
            $packDate = Carbon::createFromFormat('dmy', $dateStr)->format('Y-m-d');
        } catch (\Exception $e) {
            $packDate = now()->format('Y-m-d');
        }

        $date = Carbon::parse($packDate);
        if (in_array((int)$gradeId, [1, 3])) { // CHILL or A
            $expDate = $date->copy()->addMonths(3)->format('Y-m-d');
        } else {
            $expDate = $date->copy()->addYear()->format('Y-m-d');
        }

        DB::transaction(function () use ($product, $gradeId, $weight, $pcs, $ph, $packDate, $expDate, $barcode, $poItem) {
            GoodsReceiptProductItem::create([
                'goods_receipt_product_id' => $this->record->id,
                'barcode' => $barcode,
                'product_id' => $product->id,
                'grade_id' => $gradeId,
                'weight' => $weight,
                'qty_pcs' => $pcs,
                'ph_level' => $ph,
                'pack_date' => $packDate,
                'origin' => 'BUYBACK',
                'price' => $poItem->price,
                'subtotal' => $weight * $poItem->price,
            ]);

            BeefStock::create([
                'barcode' => $barcode,
                'product_id' => $product->id,
                'warehouse_id' => 1,
                'grade_id' => $gradeId,
                'weight' => $weight,
                'qty_pcs' => $pcs,
                'ph_level' => $ph,
                'pack_date' => $packDate,
                'exp_date' => $expDate,
                'origin' => 'BUYBACK',
                'status' => 'IN_STOCK',
            ]);

            BeefStockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => 1,
                'condition' => $gradeId,
                'barcode' => $barcode,
                'transaction_type' => 'GR_BEEF',
                'reference_document' => $this->record->gr_number,
                'weight_in' => $weight,
                'pcs_in' => $pcs,
                'created_by' => auth()->id(),
                'note' => 'Buyback Scan GR Beef ' . $this->record->gr_number,
            ]);
        });

        Notification::make()->title('Barcode berhasil di-scan dan dimasukkan ke stok!')->success()->send();

        $this->form->fill([
            'input_mode' => 'scan',
            'scan_barcode' => null,
            'sj_number' => $formData['sj_number'],
            'receive_date' => $formData['receive_date'],
            'note' => $formData['note'] ?? '',
            'origin' => $formData['origin'] ?? '7',
            'product_id' => $formData['product_id'] ?? null,
            'grade_id' => $formData['grade_id'] ?? null,
            'pack_date' => $formData['pack_date'] ?? now()->format('Y-m-d'),
            'qty_pcs_combined' => null,
            'ph_level' => null,
        ]);

        $this->dispatch('refreshTable');
    }

    protected function processManual(array $formData): void
    {
        $origin = $formData['origin'] ?? '7';
        $productId = $formData['product_id'] ?? null;
        $gradeId = $formData['grade_id'] ?? null;
        $packDate = $formData['pack_date'] ?? null;
        $phLevel = $formData['ph_level'] ?? null;
        $qtyPcsCombined = $formData['qty_pcs_combined'] ?? null;

        if (!$productId || !$gradeId || !$packDate || !$qtyPcsCombined) {
            Notification::make()->title('Mohon lengkapi semua field input manual!')->danger()->send();
            return;
        }

        $parts = explode('/', $qtyPcsCombined);
        $weight = (float) trim($parts[0]);
        $pcs = isset($parts[1]) && trim($parts[1]) !== '' ? (int) trim($parts[1]) : 1;

        if ($weight <= 0 || $pcs <= 0) {
            Notification::make()->title('Qty dan Pcs harus lebih besar dari 0!')->danger()->send();
            return;
        }

        $product = Product::find($productId);
        if (!$product) {
            Notification::make()->title('Produk tidak ditemukan!')->danger()->send();
            return;
        }

        $poItem = $this->record->purchaseProduct->items()->where('product_id', $product->id)->first();
        if (!$poItem) {
            Notification::make()->title("Produk {$product->name} tidak ada dalam PO ini!")->danger()->send();
            return;
        }

        $date = Carbon::parse($packDate);
        if (in_array((int)$gradeId, [1, 3])) { // CHILL or A
            $expDate = $date->copy()->addMonths(3)->format('Y-m-d');
        } else {
            $expDate = $date->copy()->addYear()->format('Y-m-d');
        }

        $dateStr = Carbon::parse($packDate)->format('dmy');
        $productCode = $product->code ?? '000000';
        if (strlen($productCode) > 6) {
            $productCode = substr($productCode, 0, 6);
        } else {
            $productCode = str_pad($productCode, 6, '0', STR_PAD_LEFT);
        }

        $weightStr = str_pad(round($weight * 100), 4, '0', STR_PAD_LEFT);
        $pcsStr = str_pad($pcs, 2, '0', STR_PAD_LEFT);
        $phStr = $phLevel ? str_pad(round($phLevel * 10), 2, '0', STR_PAD_LEFT) : '00';

        $prefix = $origin . $dateStr;

        $latestItem = GoodsReceiptProductItem::withTrashed()->where('barcode', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
        $counterItem = ($latestItem && strlen($latestItem->barcode) >= 25) ? ((int) substr($latestItem->barcode, -3)) : 0;

        $latestBeefStock = BeefStock::where('barcode', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
        $counterStock = ($latestBeefStock && strlen($latestBeefStock->barcode) >= 25) ? ((int) substr($latestBeefStock->barcode, -3)) : 0;

        $counter = max($counterItem, $counterStock) + 1;
        $counterStr = str_pad($counter, 3, '0', STR_PAD_LEFT);

        $barcode = $origin . $dateStr . $productCode . $gradeId . $weightStr . $pcsStr . $phStr . $counterStr;

        $insertedItem = DB::transaction(function () use ($product, $gradeId, $weight, $pcs, $phLevel, $packDate, $expDate, $barcode, $poItem, $origin) {
            $item = GoodsReceiptProductItem::create([
                'goods_receipt_product_id' => $this->record->id,
                'barcode' => $barcode,
                'product_id' => $product->id,
                'grade_id' => $gradeId,
                'weight' => $weight,
                'qty_pcs' => $pcs,
                'ph_level' => $phLevel,
                'pack_date' => $packDate,
                'origin' => $origin == '7' ? 'TRADING' : 'IMPORT',
                'price' => $poItem->price,
                'subtotal' => $weight * $poItem->price,
            ]);

            BeefStock::create([
                'barcode' => $barcode,
                'product_id' => $product->id,
                'warehouse_id' => 1,
                'grade_id' => $gradeId,
                'weight' => $weight,
                'qty_pcs' => $pcs,
                'ph_level' => $phLevel,
                'pack_date' => $packDate,
                'exp_date' => $expDate,
                'origin' => $origin == '7' ? 'TRADING' : 'IMPORT',
                'status' => 'IN_STOCK',
            ]);

            BeefStockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => 1,
                'condition' => $gradeId,
                'barcode' => $barcode,
                'transaction_type' => 'GR_BEEF',
                'reference_document' => $this->record->gr_number,
                'weight_in' => $weight,
                'pcs_in' => $pcs,
                'created_by' => auth()->id(),
                'note' => 'Manual Print GR Beef ' . $this->record->gr_number,
            ]);

            return $item;
        });

        Notification::make()->title('Item berhasil ditambahkan!')->success()->send();

        $this->form->fill([
            'input_mode' => 'manual',
            'origin' => $origin,
            'product_id' => $productId,
            'grade_id' => $gradeId,
            'pack_date' => $packDate,
            'qty_pcs_combined' => null,
            'ph_level' => $phLevel,
            'scan_barcode' => null,
            'sj_number' => $formData['sj_number'],
            'receive_date' => $formData['receive_date'],
            'note' => $formData['note'] ?? '',
        ]);

        $this->dispatch('refreshTable');

        if ($insertedItem) {
            $printUrl = route('goods-receipt-product.label', [
                'id' => $insertedItem->id,
            ]);
            $this->dispatch('auto-print', url: $printUrl);
        }
    }

    public function completeGr()
    {
        $this->validate([
            'data.sj_number' => 'required',
            'data.receive_date' => 'required|date',
        ]);

        $itemCount = GoodsReceiptProductItem::where('goods_receipt_product_id', $this->record->id)->count();
        if ($itemCount === 0) {
            Notification::make()->title('Belum ada barang yang diterima!')->danger()->send();
            return;
        }

        $poItems = $this->record->purchaseProduct->items;
        $allFulfilled = true;

        foreach ($poItems as $poItem) {
            $qtyOrdered = $poItem->qty;
            
            $totalReceived = GoodsReceiptProductItem::whereHas('goodsReceiptProduct', function ($q) {
                $q->where('purchase_product_id', $this->record->purchaseProduct->id);
            })->where('product_id', $poItem->product_id)->sum('weight');

            if ($totalReceived < $qtyOrdered) {
                $allFulfilled = false;
                break;
            }
        }

        if (!$allFulfilled) {
            $this->dispatch('open-modal', id: 'partial-confirmation-modal');
        } else {
            $this->executeFinalize('completed');
        }
    }

    public function confirmPartial()
    {
        $this->executeFinalize('partial');
        $this->dispatch('close-modal', id: 'partial-confirmation-modal');
    }

    public function forceCompleted()
    {
        $this->executeFinalize('completed');
        $this->dispatch('close-modal', id: 'partial-confirmation-modal');
    }

    protected function executeFinalize(string $poStatus): void
    {
        DB::beginTransaction();
        try {
            $this->record->update([
                'sj_number' => $this->data['sj_number'],
                'receive_date' => $this->data['receive_date'],
                'note' => $this->data['note'] ?? null,
            ]);

            \App\Models\Payable::generateForGoodsReceiptProduct($this->record);

            $this->record->purchaseProduct->update(['status' => $poStatus]);

            DB::commit();

            Notification::make()->title('Goods Receipt berhasil disimpan!')->success()->send();
            $this->redirect(GoodsReceiptProductResource::getUrl('index'));
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }
}
