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
                ->label(__('Kembali'))
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

    public function mount(StockTake $record): void
    {
        $this->record = $record;
        
        // Block scanning if DRAFT (not started yet)
        if ($this->record->status === 'DRAFT') {
            redirect()->to(StockTakeResource::getUrl('view', ['record' => $this->record]));
        }
    }
    
    public function scan()
    {
        $barcode = trim($this->barcode);
        $this->barcode = '';
        
        if (empty($barcode)) {
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
                        ->title(__('Sudah Discan'))
                        ->body(__('Barcode lama ini sudah dikonversi dan dicetak label barunya!'))
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
                        ->body(__('Could not find product code ' . $productCode))
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
                            ->label(__('Lokasi Gudang Temuan'))
                            ->options(\App\Models\Warehouse::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('barcode')
                            ->label(__('Barcode (Optional)'))
                            ->helperText(__('Leave blank to generate automatically.'))
                            ->columnSpanFull(),
                        Forms\Components\Select::make('product_id')
                            ->label(__('Produk'))
                            ->options(\App\Models\Product::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('grade_id')
                            ->label(__('Grade'))
                            ->options(\App\Models\Grade::pluck('name', 'id'))
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
                            ->label(__('pH'))
                            ->numeric()
                            ->minValue(5.4)
                            ->maxValue(5.7)
                            ->step(0.1),
                        Forms\Components\DatePicker::make('pack_date')
                            ->label(__('Pack Date'))
                            ->default(now()),
                        Forms\Components\Toggle::make('show_exp')
                            ->label(__('Tampilkan Exp Date di Label?'))
                            ->default(false),
                        Forms\Components\Toggle::make('print_label')
                            ->label(__('Cetak Label Baru?'))
                            ->helperText(__('Cetak label barcode baru untuk item ini.'))
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
                            ->label(__('Catatan'))
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

                    $prefix = $origin . $dateStr;
                    // Find latest barcode with this prefix in BeefStock to avoid collision
                    $latestItem = \App\Models\BeefStock::where('barcode', 'like', $prefix . '%')
                        ->orderBy('barcode', 'desc')
                        ->first();
                    
                    $counter = ($latestItem && strlen($latestItem->barcode) >= 26) ? ((int) substr($latestItem->barcode, -4) + 1) : 1;
                    $counterStr = str_pad($counter, 4, '0', STR_PAD_LEFT);

                    $barcode = $origin . $dateStr . $productCode . $gradeId . $weightStr . $pcsStr . $phStr . $counterStr;
                    
                    // Append legacy barcode to note for tracking
                    if (!empty($data['barcode']) && strlen($data['barcode']) !== 26) {
                        $currentNote = $data['note'] ?? '';
                        $data['note'] = trim($currentNote . " (Legacy: " . $data['barcode'] . ")");
                    }
                }

                $packDate = \Carbon\Carbon::parse($data['pack_date'] ?? now());
                
                // Grade 1 (Chill) = 3 months, others = 1 year
                $expDate = ((int)$data['grade_id'] === 1) 
                    ? $packDate->copy()->addMonths(3)
                    : $packDate->copy()->addYear();

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
                    'exp_date' => $expDate->format('Y-m-d'),
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
                        'MATCHED' => 'Matched (Scanned)',
                        'UNEXPECTED' => 'Unexpected (Found)',
                    ])
            ])
            ->actions([
                Tables\Actions\Action::make('cancel_scan')
                    ->label(__('Batal Scan'))
                    ->icon('heroicon-o-x-mark')
                    ->iconButton()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (StockTakeItem $record) => $record->status === 'MATCHED')
                    ->action(fn (StockTakeItem $record) => $record->update(['status' => 'MISSING'])),
                Tables\Actions\DeleteAction::make()
                    ->label(__('Hapus'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (StockTakeItem $record) => $record->status === 'UNEXPECTED')
                    ->iconButton(),
            ]);
    }
}
