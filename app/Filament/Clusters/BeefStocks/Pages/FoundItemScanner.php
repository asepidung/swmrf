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

class FoundItemScanner extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-viewfinder-circle';
    protected static ?string $cluster = BeefStocks::class;
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.clusters.beef-stocks.pages.found-item-scanner';

    public ?string $barcode = '';

    public static function getNavigationLabel(): string
    {
        return __('Found Item');
    }

    public function getTitle(): string
    {
        return __('Temuan Barang');
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
                ->title(__('Barang sudah terdaftar di stok'))
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
            $historyMessage = "Histori Barang tidak ditemukan.";
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
            ->modalHeading(__('Konfirmasi Temuan'))
            ->modalDescription(fn (array $arguments) => $arguments['historyMessage'] ?? '')
            ->modalSubmitActionLabel(__('Lanjutkan Stock In'))
            ->action(function (array $arguments) {
                $this->replaceMountedAction('manualInput', $arguments);
            });
    }

    public function manualInputAction(): Action
    {
        return Action::make('manualInput')
            ->label(__('Label Rusak'))
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
                    'note' => 'Barang Temuan',
                ];
            })
            ->form([
                Forms\Components\Placeholder::make('historyMessage')
                    ->label('')
                    ->content(fn ($get) => $get('historyMessage') ? new \Illuminate\Support\HtmlString('<div class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400" role="alert"><span class="font-medium">Info Riwayat:</span> ' . $get('historyMessage') . '</div>') : '')
                    ->hidden(fn ($get) => empty($get('historyMessage'))),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->label(__('Lokasi Gudang Temuan'))
                            ->options(Warehouse::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('barcode')
                            ->label(__('Barcode Asli (Opsional)'))
                            ->helperText(__('Sistem akan generate barcode baru (prefix 0) untuk semua temuan. Barcode lama disimpan di catatan.'))
                            ->disabled()
                            ->dehydrated()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('product_id')
                            ->label(__('Produk'))
                            ->options(Product::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('grade_id')
                            ->label(__('Grade'))
                            ->options(Grade::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('qty_pcs_combined')
                            ->label(__('Berat/Pcs (Contoh: 10.15/5)'))
                            ->placeholder(__('10.15/5'))
                            ->required()
                            ->extraInputAttributes([
                                'oninput' => "this.value = this.value.replace(/,/g, '.');"
                            ]),
                        Forms\Components\TextInput::make('ph_level')
                            ->label('pH Level')
                            ->numeric()
                            ->inputMode('decimal')
                            ->minValue(5.4)
                            ->maxValue(5.7)
                            ->step(0.1),
                        Forms\Components\DatePicker::make('pack_date')
                            ->label(__('Pack Date'))
                            ->required(),
                        Forms\Components\Toggle::make('show_exp')
                            ->label(__('Tampilkan Exp Date di Label?'))
                            ->default(false)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('note')
                            ->label(__('Catatan'))
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

                $prefix = $origin . $dateStr;
                $latestItem = BeefStock::where('barcode', 'like', $prefix . '%')
                    ->orderBy('barcode', 'desc')
                    ->first();
                
                $counter = ($latestItem && strlen($latestItem->barcode) >= 26) ? ((int) substr($latestItem->barcode, -4) + 1) : 1;
                $counterStr = str_pad($counter, 4, '0', STR_PAD_LEFT);

                $finalBarcode = $origin . $dateStr . $productCode . $gradeId . $weightStr . $pcsStr . $phStr . $counterStr;
                
                // Append legacy barcode to note for tracking
                $legacyBarcode = $data['barcode'] ?? null;
                $currentNote = $data['note'] ?? '';
                if (!empty($legacyBarcode)) {
                    $data['note'] = trim($currentNote . " (Legacy: " . $legacyBarcode . ")");
                }

                $packDate = Carbon::parse($data['pack_date'] ?? now());
                
                // Grade 1 (Chill) = 3 months, others = 1 year
                $expDate = ((int)$data['grade_id'] === 1) 
                    ? $packDate->copy()->addMonths(3) 
                    : $packDate->copy()->addYear();

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
                    'note' => 'Barang Temuan di Gudang',
                    'created_by' => auth()->id(),
                ]);

                $printUrl = route('beef-stock.label', [
                    'id' => $stock->id,
                    'show_exp' => ($data['show_exp'] ?? false) ? 1 : 0
                ]);
                $this->dispatch('auto-print', url: $printUrl);

                Notification::make()
                    ->title(__('Temuan Barang Berhasil Disimpan'))
                    ->body(__('Label baru (prefix 0) dibuat: ') . $finalBarcode)
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
                    ->where('transaction_type', 'FOUND_ITEM')
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Waktu Temuan'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode Baru'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Produk'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('weight_in')
                    ->label(__('Berat'))
                    ->numeric(2)
                    ->suffix(' Kg'),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label(__('Gudang')),
                Tables\Columns\TextColumn::make('note')
                    ->label(__('Catatan'))
                    ->wrap(),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label(__('Cetak Ulang'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->action(function (BeefStockMovement $record) {
                        // Find the actual BeefStock record to print
                        $stock = BeefStock::where('barcode', $record->barcode)->first();
                        if ($stock) {
                            $printUrl = route('beef-stock.label', [
                                'id' => $stock->id,
                                'show_exp' => 0
                            ]);
                            $this->dispatch('auto-print', url: $printUrl);
                        } else {
                            Notification::make()
                                ->title(__('Barang sudah tidak ada di stok'))
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
