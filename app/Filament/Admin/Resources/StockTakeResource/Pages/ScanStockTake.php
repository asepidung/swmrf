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

class ScanStockTake extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = StockTakeResource::class;

    protected static string $view = 'filament.admin.resources.stock-take-resource.pages.scan-stock-take';

    public StockTake $record;

    public ?string $barcode = '';

    public function mount(StockTake $record): void
    {
        $this->record = $record;
        
        // Block scanning if not IN_PROGRESS
        if ($this->record->status !== 'IN_PROGRESS') {
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
            // Unexpected barcode. Let's try to parse it.
            if (strlen($barcode) !== 26) {
                \Filament\Notifications\Notification::make()
                    ->title(__('Invalid Barcode Format'))
                    ->body(__('Barcode length must be exactly 26 digits. Use Manual Input.'))
                    ->danger()
                    ->send();
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

                StockTakeItem::create([
                    'stock_take_id' => $this->record->id,
                    'barcode' => $barcode,
                    'product_id' => $productId,
                    'grade_id' => $gradeId,
                    'weight' => $weight,
                    'qty_pcs' => $pcs,
                    'ph_level' => $ph,
                    'pack_date' => $packDate,
                    'status' => 'UNEXPECTED',
                    'is_manual' => false,
                ]);

                \Filament\Notifications\Notification::make()
                    ->title(__('Unexpected Item Scanned'))
                    ->success()
                    ->send();
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
            ->label(__('Input Manual Temuan'))
            ->color('warning')
            ->icon('heroicon-o-pencil-square')
            ->form([
                Forms\Components\TextInput::make('barcode')
                    ->label(__('Barcode (Opsional)'))
                    ->helperText(__('Jika kosong, sistem akan generate otomatis.')),
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
                Forms\Components\TextInput::make('weight')
                    ->label(__('Berat (Kg)'))
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('qty_pcs')
                    ->label(__('Qty (Pcs)'))
                    ->numeric()
                    ->default(1)
                    ->required(),
                Forms\Components\DatePicker::make('pack_date')
                    ->label(__('Pack Date'))
                    ->default(now()),
                Forms\Components\Textarea::make('note')
                    ->label(__('Catatan'))
                    ->rows(2),
            ])
            ->action(function (array $data) {
                $barcode = $data['barcode'] ?? null;
                
                if (empty($barcode)) {
                    // Generate dummy barcode for manual item
                    $prefix = '9'; // '9' for manual stock take finding
                    $dateStr = \Carbon\Carbon::parse($data['pack_date'] ?? now())->format('dmy');
                    $barcode = $prefix . $dateStr . 'MANUAL' . rand(1000, 9999);
                }

                StockTakeItem::create([
                    'stock_take_id' => $this->record->id,
                    'barcode' => $barcode,
                    'product_id' => $data['product_id'],
                    'grade_id' => $data['grade_id'],
                    'weight' => $data['weight'],
                    'qty_pcs' => $data['qty_pcs'],
                    'pack_date' => $data['pack_date'] ?? now(),
                    'note' => $data['note'] ?? null,
                    'status' => 'UNEXPECTED',
                    'is_manual' => true,
                ]);

                \Filament\Notifications\Notification::make()
                    ->title(__('Manual item added'))
                    ->success()
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(StockTakeItem::query()->where('stock_take_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->searchable()
                    ->formatStateUsing(fn (string $state, StockTakeItem $record) => 
                        $record->status === 'MISSING' ? '**********' . substr($state, -4) : $state
                    )
                    ->color(fn (StockTakeItem $record) => $record->status === 'MISSING' ? 'gray' : null),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Produk'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Berat'))
                    ->numeric(2),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'MISSING' => 'danger',
                        'MATCHED' => 'success',
                        'UNEXPECTED' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'MISSING' => 'Waiting (Missing)',
                        'MATCHED' => 'Matched (Scanned)',
                        'UNEXPECTED' => 'Unexpected (Found)',
                    ])
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (StockTakeItem $record) => $record->status === 'UNEXPECTED')
                    ->iconButton(),
            ]);
    }
}
