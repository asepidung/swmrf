<?php

namespace App\Filament\Clusters\BeefStocks\Pages;

use Filament\Pages\Page;
use App\Filament\Clusters\BeefStocks;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms;
use Filament\Actions\Action;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\TallyItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Grade;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Pages\SubNavigationPosition;

class FoundItemScanner extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-viewfinder-circle';
    protected static ?string $cluster = BeefStocks::class;

    /**
     * Halaman ini MENCETAK STOK, jadi izinnya sendiri.
     *
     * Ia membuat baris `BeefStock` baru dari isian orang -- barang yang
     * ditemukan di gudang tetapi tidak pernah tercatat. Itu satu-satunya
     * tempat di aplikasi ini yang menambah persediaan tanpa dokumen asal.
     *
     * Sebelumnya ia hanya dijaga gerbang clusternya, yang berisi izin MELIHAT:
     * `view_beef_stocks`, `view_beef_stock_movements`, atau
     * `view_beef_stock_aging`. Artinya siapa pun yang boleh melihat stok juga
     * boleh mencetaknya. Melihat dan mencetak dua kewenangan yang berbeda --
     * bentuk yang sama sudah ditambal pada Approve retur dan Lock repack.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('record_found_items') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.clusters.beef-stocks.pages.found-item-scanner';

    public ?string $barcode = '';

    public static function getNavigationLabel(): string
    {
        return __('Found Item');
    }

    public function getTitle(): string
    {
        return __('Found Items');
    }

    public function scan()
    {
        $barcode = trim($this->barcode);
        $this->barcode = '';
        $this->dispatch('focus-barcode');

        if (empty($barcode)) {
            return;
        }

        // 1. Cek apakah barang sudah ada di IN_STOCK
        $exists = BeefStock::where('barcode', $barcode)->where('status', 'IN_STOCK')->first();
        if ($exists) {
            Notification::make()
                ->title(__('This item is already registered in stock'))
                ->warning()
                ->send();
            return;
        }

        // 2. Jika 26 digit, parse barcode dan cari riwayat
        $historyMessage = null;
        $foundData = [];

        if (strlen($barcode) >= 26) {
            // Parse barcode
            $dateStr = substr($barcode, 1, 6);
            $productCode = substr($barcode, 7, 6);
            $gradeId = substr($barcode, 13, 1);
            $weightStr = substr($barcode, 14, 4);
            $pcsStr = substr($barcode, 18, 2);
            $phStr = substr($barcode, 20, 2);

            try {
                $productCodeTrimmed = ltrim($productCode, '0');
                if (empty($productCodeTrimmed)) $productCodeTrimmed = $productCode;

                $product = Product::where('code', $productCode)
                    ->orWhere('code', $productCodeTrimmed)
                    ->first();

                $foundData['pack_date'] = Carbon::createFromFormat('dmy', $dateStr)->format('Y-m-d');
                $foundData['weight'] = ((float) $weightStr) / 100;
                $foundData['qty_pcs'] = (int) $pcsStr;
                $foundData['ph_level'] = ((float) $phStr) / 10;
                $foundData['product_id'] = $product ? $product->id : null;
                $foundData['grade_id'] = (int) $gradeId;
            } catch (\Exception $e) {
                // Ignore parse errors, let user fill manually
            }

            // Cek di BeefStockMovement
            $lastMovement = BeefStockMovement::where('barcode', $barcode)->orderBy('created_at', 'desc')->first();
            if ($lastMovement) {
                $warehouseName = $lastMovement->warehouse ? $lastMovement->warehouse->name : '-';
                $historyMessage = "Histori Barang ditemukan. Posisi Terakhir di Gudang {$warehouseName} (Proses: {$lastMovement->transaction_type}).";
            } else {
                // Cek di TallyItem
                $tally = TallyItem::where('barcode', $barcode)->first();
                if ($tally) {
                    $warehouseName = $tally->warehouse ? $tally->warehouse->name : '-';
                    $historyMessage = "Histori Barang ditemukan. Posisi Terakhir di Gudang {$warehouseName} (Proses: Penerimaan/Tally).";
                    // Fallback pre-fill from tally if parse failed
                    if (empty($foundData['product_id'])) $foundData['product_id'] = $tally->product_id;
                    if (empty($foundData['weight'])) $foundData['weight'] = $tally->weight;
                    if (empty($foundData['qty_pcs'])) $foundData['qty_pcs'] = $tally->qty_pcs;
                }
            }
        }

        if (!$historyMessage) {
            $historyMessage = __('No history found for this item.');
        }

        // 3. Tampilkan modal konfirmasi
        $this->mountAction('confirmStockIn', [
            'historyMessage' => $historyMessage,
            'barcode' => $barcode, 
            'product_id' => $foundData['product_id'] ?? null,
            'grade_id' => $foundData['grade_id'] ?? null,
            'weight' => $foundData['weight'] ?? null,
            'qty_pcs' => $foundData['qty_pcs'] ?? null,
            'ph_level' => $foundData['ph_level'] ?? null,
            'pack_date' => $foundData['pack_date'] ?? now()->format('Y-m-d'),
        ]);
    }

    public function confirmStockInAction(): Action
    {
        return Action::make('confirmStockIn')
            ->modalHeading(__('Confirm the Find'))
            ->modalDescription(fn (array $arguments) => $arguments['historyMessage'] ?? '')
            ->modalSubmitActionLabel(__('Lanjutkan Stock In'))
            ->action(function (array $arguments) {
                $this->replaceMountedAction('manualInput', $arguments);
            });
    }

    public function manualInputAction(): Action
    {
        return Action::make('manualInput')
            ->label(__('Damaged Label'))
            ->color('warning')
            ->icon('heroicon-o-pencil-square')
            ->fillForm(function (array $arguments): array {
                $qty = $arguments['qty_pcs'] ?? '';
                $weight = $arguments['weight'] ?? '';
                $combined = ($weight && $qty) ? "{$weight}/{$qty}" : '';

                return [
                    'historyMessage' => $arguments['historyMessage'] ?? null,
                    'barcode' => $arguments['barcode'] ?? null,
                    'product_id' => $arguments['product_id'] ?? null,
                    'grade_id' => $arguments['grade_id'] ?? null,
                    'qty_pcs_combined' => $combined,
                    'ph_level' => $arguments['ph_level'] ?? null,
                    'pack_date' => $arguments['pack_date'] ?? now()->format('Y-m-d'),
                    'note' => null,
                ];
            })
            ->form([
                Forms\Components\Placeholder::make('historyMessage')
                    ->label('')
                    ->content(fn ($get) => $get('historyMessage') ? new \Illuminate\Support\HtmlString('<div class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400" role="alert"><span class="font-medium">' . __('History') . ':</span> ' . $get('historyMessage') . '</div>') : '')
                    ->hidden(fn ($get) => empty($get('historyMessage'))),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->label(__('Warehouse Where It Was Found'))
                            ->options(Warehouse::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('barcode')
                            ->label(__('Original Barcode (Optional)'))
                            ->helperText(__('A new barcode (prefix 0) is generated for every find. The old barcode is kept in the note.'))
                            ->disabled()
                            ->dehydrated()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('product_id')
                            ->label(__('Product'))
                            ->options(Product::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('grade_id')
                            ->label(__('Grade'))
                            ->options(Grade::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('qty_pcs_combined')
                            ->label(__('Weight/Pcs (e.g. 10.15/5)'))
                            ->placeholder(__('10.15/5'))
                            ->required()
                            ->extraInputAttributes([
                                'oninput' => "this.value = this.value.replace(/,/g, '.');"
                            ]),
                        Forms\Components\TextInput::make('ph_level')
                            ->label(__('pH Level'))
                            ->numeric()
                            ->inputMode('decimal')
                            ->minValue(5.4)
                            ->maxValue(5.7)
                            ->step(0.1),
                        Forms\Components\DatePicker::make('pack_date')
                            ->label(__('Pack Date'))
                            ->required(),
                        Forms\Components\Toggle::make('show_exp')
                            ->label(__('Show the expiry date on the label?'))
                            ->default(false)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
            ])
            ->modalWidth('xl')
            ->action(function (array $data) {
                // Parse Berat/Pcs
                $combinedInput = $data['qty_pcs_combined'];
                $parts = explode('/', $combinedInput);
                $weight = (float) trim($parts[0]);
                $pcs = isset($parts[1]) && trim($parts[1]) !== '' ? (int) trim($parts[1]) : 1;

                // Tentukan barcode baru
                $origin = '0'; // Default '0' for manual/unknown finding without label
                $dateStr = Carbon::parse($data['pack_date'] ?? now())->format('dmy');
                $product = Product::find($data['product_id']);
                $productCode = $product->code ?? '000000';
                
                if (strlen($productCode) > 6) {
                    $productCode = substr($productCode, 0, 6);
                } else {
                    $productCode = str_pad($productCode, 6, '0', STR_PAD_LEFT);
                }

                $gradeId = str_pad($data['grade_id'], 1, '0', STR_PAD_LEFT);
                $weightStr = str_pad(round($weight * 100), 4, '0', STR_PAD_LEFT);
                $pcsStr = str_pad($pcs, 2, '0', STR_PAD_LEFT);
                $phStr = isset($data['ph_level']) ? str_pad(round($data['ph_level'] * 10), 2, '0', STR_PAD_LEFT) : '00';

                // Urutan barcode barang temuan.
                //
                // Bentuk lamanya memuat tiga hal yang sudah diberantas di
                // `InputReturnItems` pada #230, dan ternyata hidup juga di
                // sini:
                //
                //  - PANJANG barcode dipakai sebagai penanda sah (`>= 26`).
                //    Project Owner sudah menegaskan tidak semua barcode 26
                //    karakter, jadi panjangnya tidak pernah boleh menjadi
                //    tanda benar atau salah;
                //  - `substr(-4)` memotong tepat empat karakter terakhir,
                //    sehingga urutan ke-10.000 terbaca `0000` dan penomorannya
                //    mulai dari 1 lagi;
                //  - `orderBy('barcode', 'desc')` mengurutkan sebagai TEKS,
                //    bukan angka.
                //
                // Sekarang urutannya dibaca dari bagian sesudah awalannya dan
                // yang diambil yang TERBESAR.
                $prefix = $origin . $dateStr;

                $counterStr = \App\Support\BarcodeSequence::nextPadded($prefix, [
                    BeefStock::query(),
                ]);

                $finalBarcode = $origin . $dateStr . $productCode . $gradeId . $weightStr . $pcsStr . $phStr . $counterStr;
                
                // Append legacy barcode to note for tracking
                $legacyBarcode = $data['barcode'] ?? null;
                $currentNote = $data['note'] ?? '';
                if (!empty($legacyBarcode)) {
                    $data['note'] = trim($currentNote . " (Legacy: " . $legacyBarcode . ")");
                }

                $packDate = Carbon::parse($data['pack_date'] ?? now());
                
                // Umur simpannya satu rumah di `ShelfLife`.
                $expDate = \App\Support\ShelfLife::expiryFor($packDate, $data['grade_id']);

                // Insert into BeefStock
                $stock = BeefStock::create([
                    'barcode' => $finalBarcode,
                    'product_id' => $data['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'grade_id' => $data['grade_id'],
                    'weight' => $weight,
                    'qty_pcs' => $pcs,
                    'ph_level' => $data['ph_level'] ?? null,
                    'pack_date' => $packDate,
                    'exp_date' => $expDate,
                    'origin' => \App\Helpers\BarcodeHelper::getOrigin($finalBarcode),
                    'status' => 'IN_STOCK',
                    'note' => $data['note'],
                ]);

                // Record Movement
                BeefStockMovement::create([
                    'product_id' => $stock->product_id,
                    'warehouse_id' => $stock->warehouse_id,
                    'condition' => 'GOOD',
                    'barcode' => $stock->barcode,
                    'transaction_type' => 'FOUND_ITEM',
                    'reference_document' => '-',
                    'weight_in' => $stock->weight,
                    'weight_out' => 0,
                    'pcs_in' => $stock->qty_pcs,
                    'pcs_out' => 0,
                    'note' => $stock->note,
                    'created_by' => auth()->id(),
                ]);

                $printUrl = route('beef-stock.label', [
                    'id' => $stock->id,
                    'show_exp' => ($data['show_exp'] ?? false) ? 1 : 0
                ]);
                $this->dispatch('auto-print', url: $printUrl);

                Notification::make()
                    ->title(__('Found item saved'))
                    ->body(__('New label (prefix 0) created: ') . $finalBarcode)
                    ->success()
                    ->send();
                    
                $this->dispatch('focus-barcode');
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BeefStockMovement::query()
                    ->whereIn('transaction_type', ['FOUND_ITEM', 'STOCK_TAKE_FOUND'])
            )
            ->defaultSort('created_at', 'desc')
            ->heading(__('Found Item History'))
            ->description(__('Goods that were never recorded and turned up during a scan or a stock count.'))
            ->emptyStateHeading(__('No found-item history yet'))
            ->emptyStateIcon('heroicon-o-viewfinder-circle')
            ->striped()
            ->paginated([5, 10, 25, 50])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Found At'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('weight_in')
                    ->label(__('Weight'))
                    ->numeric(2)
                    ->suffix(' Kg'),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label(__('Warehouse')),
                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->getStateUsing(fn ($record) => $record->transaction_type === 'STOCK_TAKE_FOUND' ? 'ST' : 'PF')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ST' => 'warning',
                        'PF' => 'info',
                        default => 'gray',
                    }),
            ])
            ->actions([
            ]);
    }
}
