<?php

namespace App\Filament\Admin\Resources\StockTakeResource\Pages;

use App\Filament\Admin\Resources\StockTakeResource;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;

class ScanStockTake extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = StockTakeResource::class;

    protected static string $view = 'filament.admin.resources.stock-take-resource.pages.scan-stock-take';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('Back'))
                ->icon('heroicon-o-arrow-left')
                ->url(StockTakeResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    public function viewMissingAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('viewMissing')
            ->modalHeading(__('Missing Items (Waiting)'))
            ->modalContent(fn () => view('filament.admin.resources.stock-take-resource.pages.missing-items-modal', [
                'items' => StockTakeItem::where('stock_take_id', $this->record->id)
                    ->where('status', 'MISSING')
                    ->with(['product', 'grade'])
                    ->get()
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    public function viewUnexpectedAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('viewUnexpected')
            ->modalHeading(__('Unexpected Items (Found)'))
            ->modalContent(fn () => view('filament.admin.resources.stock-take-resource.pages.missing-items-modal', [
                'items' => StockTakeItem::where('stock_take_id', $this->record->id)
                    ->where('status', 'UNEXPECTED')
                    ->with(['product', 'grade'])
                    ->get()
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    public StockTake $record;

    public ?string $barcode = '';

    /**
     * Halaman ini hanya untuk opname yang MASIH BERJALAN.
     *
     * Dulu yang ditolak hanya status `DRAFT` -- status yang tidak pernah
     * ditulis oleh satu baris kode pun, sehingga penjagaannya tidak pernah
     * menahan apa pun. Yang lolos justru yang berbahaya: opname yang sudah
     * COMPLETED tetap bisa dibuka, dan barangnya yang MISSING bisa diubah
     * menjadi MATCHED. Dokumen yang sudah dipakai memotong stok berubah
     * isinya sesudah keputusannya diambil.
     */
    public function mount(StockTake $record): void
    {
        $this->record = $record;

        if (! $this->record->isCountable()) {
            \Filament\Notifications\Notification::make()
                ->title(__('This stock count is finished, so it can no longer be scanned.'))
                ->warning()
                ->send();

            redirect()->to(StockTakeResource::getUrl('view', ['record' => $this->record]));
        }
    }

    /** Penjagaan yang sama untuk setiap perubahan, bukan hanya saat membuka. */
    protected function masihBolehDiubah(): bool
    {
        if ($this->record->fresh()?->isCountable()) {
            return true;
        }

        \Filament\Notifications\Notification::make()
            ->title(__('This stock count is finished, so it can no longer be scanned.'))
            ->danger()
            ->send();

        return false;
    }
    
    public function scan()
    {
        $barcode = trim($this->barcode);
        $this->barcode = '';

        if (empty($barcode)) {
            return;
        }

        if (! $this->masihBolehDiubah()) {
            return;
        }

        // Check if barcode already exists in the snapshot (MISSING or MATCHED)
        $existingItem = StockTakeItem::where('stock_take_id', $this->record->id)
            ->where('barcode', $barcode)
            ->first();

        if ($existingItem) {
            if ($existingItem->status === 'MISSING') {
                $existingItem->update(['status' => 'MATCHED']);
                \Filament\Notifications\Notification::make()
                    ->title(__('Barcode Matched!'))
                    ->success()
                    ->send();
            } else {
                \Filament\Notifications\Notification::make()
                    ->title(__('Barcode already scanned!'))
                    ->warning()
                    ->send();
            }
        } else {
            // Not in snapshot. Parse if 26 digits, otherwise just open manual modal empty.
            if (strlen($barcode) !== 26) {
                // Check if this legacy barcode was already converted and saved in this stock take
                $alreadyConverted = StockTakeItem::where('stock_take_id', $this->record->id)
                    ->where('note', 'like', "%(Legacy: {$barcode})%")
                    ->exists();
                    
                if ($alreadyConverted) {
                    \Filament\Notifications\Notification::make()
                        ->title(__('Scanned'))
                        ->body(__('This old barcode has already been converted and its new label printed.'))
                        ->warning()
                        ->send();
                    
                    $this->dispatch('focus-barcode');
                    return;
                }

                // Not standard SWM barcode, open manual form
                $this->mountAction('manualInput', [
                    'barcode' => $barcode,
                ]);
                return;
            }

            // Parse barcode based on SWM structure
            $dateStr = substr($barcode, 1, 6);
            $productCode = substr($barcode, 7, 6);
            $gradeId = substr($barcode, 13, 1);
            $weightStr = substr($barcode, 14, 4);
            $pcsStr = substr($barcode, 18, 2);
            $phStr = substr($barcode, 20, 2);

            try {
                $packDate = \Carbon\Carbon::createFromFormat('dmy', $dateStr)->format('Y-m-d');
                $weight = ((float) $weightStr) / 100;
                $pcs = (int) $pcsStr;
                $ph = ((float) $phStr) / 10;
                
                $productCodeTrimmed = ltrim($productCode, '0');
                if (empty($productCodeTrimmed)) $productCodeTrimmed = $productCode; // fallback
                
                $product = \App\Models\Product::where('code', $productCode)
                    ->orWhere('code', $productCodeTrimmed)
                    ->first();
                
                $productId = $product ? $product->id : null;
                
                if (!$productId) {
                    \Filament\Notifications\Notification::make()
                        ->title(__('Product not found'))
                        // Kuncinya TIDAK boleh disusun dengan penyambungan
                        // teks: setiap kode produk akan melahirkan kunci
                        // sendiri, dan tidak satu pun bisa diterjemahkan.
                        ->body(__('Could not find product code :code', ['code' => $productCode]))
                        ->warning()
                        ->send();
                }

                // Trigger the manual input modal with pre-filled data
                $this->mountAction('manualInput', [
                    'barcode' => $barcode,
                    'product_id' => $productId,
                    'grade_id' => $gradeId,
                    'weight' => $weight,
                    'qty_pcs' => $pcs,
                    'ph_level' => $ph,
                    'pack_date' => $packDate,
                ]);

            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->title(__('Error Parsing Barcode'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }
        
        $this->dispatch('focus-barcode');
    }

    public function getMatchedCount(): int
    {
        return StockTakeItem::where('stock_take_id', $this->record->id)->where('status', 'MATCHED')->count();
    }

    public function getMissingCount(): int
    {
        return StockTakeItem::where('stock_take_id', $this->record->id)->where('status', 'MISSING')->count();
    }

    public function getUnexpectedCount(): int
    {
        return StockTakeItem::where('stock_take_id', $this->record->id)->where('status', 'UNEXPECTED')->count();
    }

    public function manualInputAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('manualInput')
            ->label(__('Manual Input of Findings'))
            ->color('warning')
            ->icon('heroicon-o-pencil-square')
            ->fillForm(function (array $arguments): array {
                return [
                    'barcode' => $arguments['barcode'] ?? null,
                    'product_id' => $arguments['product_id'] ?? null,
                    'grade_id' => $arguments['grade_id'] ?? null,
                    'qty_pcs_combined' => null,
                    'ph_level' => $arguments['ph_level'] ?? null,
                    'pack_date' => $arguments['pack_date'] ?? now(),
                    'note' => 'Temuan Saat Stock Opname',
                ];
            })
            ->form([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->label(__('Warehouse where it was found'))
                            ->options(\App\Models\Warehouse::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('barcode')
                            ->label(__('Barcode (Optional)'))
                            ->helperText(__('Leave blank to generate automatically.'))
                            ->columnSpanFull(),
                        Forms\Components\Select::make('product_id')
                            ->label(__('Product'))
                            ->options(\App\Models\Product::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('grade_id')
                            ->label(__('Grade'))
                            ->options(\App\Models\Grade::pluck('name', 'id'))
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
                            ->label(__('pH'))
                            ->numeric()
                            ->minValue(5.4)
                            ->maxValue(5.7)
                            ->step(0.1),
                        Forms\Components\DatePicker::make('pack_date')
                            ->label(__('Pack Date'))
                            ->default(now()),
                        Forms\Components\Toggle::make('show_exp')
                            ->label(__('Show the expiry date on the label?'))
                            ->default(false),
                        Forms\Components\Toggle::make('print_label')
                            ->label(__('Print a new label?'))
                            ->helperText(__('Print a new barcode label for this item.'))
                            ->default(function (\Filament\Forms\Get $get) {
                                $barcode = $get('barcode') ?? '';
                                return strlen($barcode) !== 26;
                            })
                            ->disabled(function (\Filament\Forms\Get $get) {
                                $barcode = $get('barcode') ?? '';
                                return strlen($barcode) !== 26;
                            })
                            ->dehydrated()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2)
                            ->default('Temuan Saat Stock Opname')
                            ->columnSpanFull(),
                    ])
            ])
            ->modalWidth('xl')
            ->action(function (array $data) {
                // Parse weight and pcs early so we can use them even if not generating new
                $combinedInput = $data['qty_pcs_combined'];
                $parts = explode('/', $combinedInput);
                $data['weight'] = (float) trim($parts[0]);
                $data['qty_pcs'] = isset($parts[1]) && trim($parts[1]) !== '' ? (int) trim($parts[1]) : 1;

                $barcode = $data['barcode'] ?? null;
                $generateNew = empty($barcode) || strlen($barcode) !== 26;
                
                if ($generateNew) {
                    $oldPrefix = !empty($barcode) ? substr($barcode, 0, 1) : null;
                    
                    // Legacy to New Origin Mapping
                    $legacyMap = [
                        '1' => '1', // Boning -> Boning
                        '2' => '7', // Trading Lokal -> TRD-LC
                        '3' => '2', // Repack Stock -> R-STCK
                        '4' => '6', // Relabel Tally -> RLB-TL
                        '5' => '3', // Repack Import -> R-IMPT
                        '6' => '4', // Repack Return -> R-RTRN
                        '7' => '5', // Repack Trading -> R-TRDG
                    ];
                    
                    $origin = $legacyMap[$oldPrefix] ?? '0'; // Default '0' for manual/unknown finding without label

                    $dateStr = \Carbon\Carbon::parse($data['pack_date'] ?? now())->format('dmy');
                    $product = \App\Models\Product::find($data['product_id']);
                    $productCode = $product->code ?? '000000';
                    
                    if (strlen($productCode) > 6) {
                        $productCode = substr($productCode, 0, 6);
                    } else {
                        $productCode = str_pad($productCode, 6, '0', STR_PAD_LEFT);
                    }
                    
                    $combinedInput = $data['qty_pcs_combined'];
                    $parts = explode('/', $combinedInput);
                    $data['weight'] = (float) trim($parts[0]);
                    $data['qty_pcs'] = isset($parts[1]) && trim($parts[1]) !== '' ? (int) trim($parts[1]) : 1;

                    $gradeId = str_pad($data['grade_id'], 1, '0', STR_PAD_LEFT);
                    $weightStr = str_pad(round($data['weight'] * 100), 4, '0', STR_PAD_LEFT);
                    $pcsStr = str_pad($data['qty_pcs'], 2, '0', STR_PAD_LEFT);
                    $phStr = isset($data['ph_level']) ? str_pad(round($data['ph_level'] * 10), 2, '0', STR_PAD_LEFT) : '00';

                    // Urutan barcode temuan opname.
                    //
                    // Bentuk lamanya memuat tiga hal yang sudah diberantas dua
                    // kali -- di `InputReturnItems` (#230) dan
                    // `FoundItemScanner` (#269) -- dan ternyata hidup juga di
                    // sini: `strlen >= 26` sebagai penanda sah, `substr(-4)`
                    // yang putus di 10.000, dan pengurutan sebagai TEKS.
                    //
                    // Dan di sini ada satu lagi yang lebih buruk: ia hanya
                    // melihat `beef_stocks`, padahal barcode barunya ditulis ke
                    // `stock_take_items` dan baru pindah ke stok saat opnamenya
                    // diselesaikan. Dua temuan dengan produk, tanggal, berat,
                    // dan pcs yang sama dalam satu opname karena itu mendapat
                    // barcode YANG SAMA PERSIS -- lalu keduanya lahir sebagai
                    // dua baris stok bernomor kembar.
                    //
                    // Sekarang urutannya diambil dari yang TERBESAR di kedua
                    // tempat sekaligus.
                    $prefix = $origin . $dateStr;

                    $counterStr = \App\Support\BarcodeSequence::nextPadded($prefix, [
                        \App\Models\BeefStock::query(),
                        \App\Models\StockTakeItem::query(),
                    ]);

                    $barcode = $origin . $dateStr . $productCode . $gradeId . $weightStr . $pcsStr . $phStr . $counterStr;
                    
                    // Append legacy barcode to note for tracking
                    if (!empty($data['barcode']) && strlen($data['barcode']) !== 26) {
                        $currentNote = $data['note'] ?? '';
                        $data['note'] = trim($currentNote . " (Legacy: " . $data['barcode'] . ")");
                    }
                }

                $packDate = \Carbon\Carbon::parse($data['pack_date'] ?? now());
                
                // Umur simpannya satu rumah, sama dengan seluruh jalur
                // label lain. Sebelumnya aturannya ditulis ulang di
                // sini, dan salinannya tidak sama dengan yang lain.
                $expDate = \App\Support\ShelfLife::expiryFor($packDate, $data['grade_id']);

                $insertedItem = StockTakeItem::create([
                    'stock_take_id' => $this->record->id,
                    'barcode' => $barcode,
                    'product_id' => $data['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'grade_id' => $data['grade_id'],
                    'weight' => $data['weight'],
                    'qty_pcs' => $data['qty_pcs'],
                    'ph_level' => $data['ph_level'] ?? null,
                    'pack_date' => $packDate->format('Y-m-d'),
                    'exp_date' => $expDate?->format('Y-m-d'),
                    'note' => $data['note'] ?? null,
                    'status' => 'UNEXPECTED',
                    'is_manual' => true,
                ]);

                \Filament\Notifications\Notification::make()
                    ->title(__('Manual item added'))
                    ->success()
                    ->send();
                    
                if (($data['print_label'] ?? false) || $generateNew) {
                    $printUrl = route('stock-take.label', [
                        'id' => $insertedItem->id,
                        'show_exp' => ($data['show_exp'] ?? false) ? 1 : 0
                    ]);
                    $this->dispatch('auto-print', url: $printUrl);
                }

                $this->dispatch('focus-barcode');
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockTakeItem::query()
                    ->where('stock_take_id', $this->record->id)
                    ->whereIn('status', ['MATCHED', 'UNEXPECTED'])
            )
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Item'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade')),
                Tables\Columns\TextColumn::make('qty')
                    ->label(__('Qty'))
                    ->getStateUsing(fn (StockTakeItem $record) => number_format($record->weight, 2, '.', '') . '/' . $record->qty_pcs)
                    ->alignRight(),
                Tables\Columns\TextColumn::make('ph_level')
                    ->label(__('pH'))
                    ->numeric(2),
                Tables\Columns\TextColumn::make('pack_date')
                    ->label(__('POD'))
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('origin')
                    ->label(__('Origin'))
                    ->state(fn (StockTakeItem $record) => \App\Helpers\BarcodeHelper::getOrigin($record->barcode)),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'MATCHED' => __('Matched (scanned)'),
                        'UNEXPECTED' => __('Unexpected (found)'),
                    ])
            ])
            ->actions([
                Tables\Actions\Action::make('cancel_scan')
                    ->label(__('Undo scan'))
                    ->icon('heroicon-o-x-mark')
                    ->iconButton()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (StockTakeItem $record): bool => $record->status === 'MATCHED'
                        && $this->record->isCountable())
                    ->action(fn (StockTakeItem $record) => $record->update(['status' => 'MISSING'])),
                Tables\Actions\DeleteAction::make()
                    ->label(__('Delete'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (StockTakeItem $record): bool => $record->status === 'UNEXPECTED'
                        && $this->record->isCountable())
                    ->iconButton(),
            ]);
    }
}
