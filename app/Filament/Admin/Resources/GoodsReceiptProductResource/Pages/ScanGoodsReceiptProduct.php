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
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Actions;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ScanGoodsReceiptProduct extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = GoodsReceiptProductResource::class;

    protected static string $view = 'filament.admin.resources.goods-receipt-product-resource.pages.scan-goods-receipt-product';

    public GoodsReceiptProduct $record;
    public ?string $barcode = '';
    public ?int $warehouse_id = null;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function getHeading(): string
    {
        return ''; // Custom header in blade view
    }

    public function mount(GoodsReceiptProduct $record): void
    {
        $this->record = $record;

        if ($this->record->is_locked) {
            $this->redirect(GoodsReceiptProductResource::getUrl('input', ['record' => $this->record->id]));
            return;
        }

        $this->warehouse_id = session('gr_warehouse_id', 1);
    }

    public function updatedWarehouseId($value): void
    {
        if ($value) {
            session(['gr_warehouse_id' => (int) $value]);
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(GoodsReceiptProductItem::query()->where('goods_receipt_product_id', $this->record->id))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->weight('bold')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->weight('semibold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => in_array($state, ['CHILL', 'A']) ? 'info' : 'danger'),

                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight'))
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', ',')),

                Tables\Columns\TextColumn::make('qty_pcs')
                    ->label(__('Pcs'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('ph_level')
                    ->label(__('pH'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('origin')
                    ->label(__('Origin'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Time'))
                    ->time('H:i:s')
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->requiresConfirmation()
                    ->tooltip(__('Delete Data'))
                    ->action(function (GoodsReceiptProductItem $item) {
                        DB::transaction(function () use ($item) {
                            $stock = BeefStock::where('barcode', $item->barcode)->first();
                            $warehouseId = $stock ? $stock->warehouse_id : 1;

                            BeefStockMovement::create([
                                'product_id' => $item->product_id,
                                'warehouse_id' => $warehouseId,
                                'condition' => $item->grade_id,
                                'barcode' => $item->barcode,
                                'transaction_type' => 'VOID_GR_BEEF',
                                'reference_document' => $this->record->gr_number ?? 'DELETED',
                                'weight_in' => -$item->weight,
                                'pcs_in' => -$item->qty_pcs,
                                'created_by' => Auth::id(),
                            ]);

                            BeefStock::where('barcode', $item->barcode)->delete();
                            $item->delete();
                        });

                        Notification::make()
                            ->title(__('Data voided and removed from stock!'))
                            ->success()
                            ->send();

                        $this->dispatch('focus-barcode');
                    }),
            ]);
    }

    public function scan()
    {
        if ($this->record->is_locked) {
            return;
        }

        $barcode = trim($this->barcode);
        if (empty($barcode)) {
            return;
        }

        // Barcode standard: 25 characters
        if (strlen($barcode) !== 25 || !is_numeric(substr($barcode, 1))) {
            Notification::make()
                ->title(__('Gagal Scan'))
                ->body(__('Format barcode tidak valid (Harus 25 karakter)'))
                ->danger()
                ->send();
            $this->barcode = '';
            $this->dispatch('focus-barcode');
            return;
        }

        // 1. Cek duplikat barcode di goods_receipt_product_items (double scan)
        $existsInGr = $this->record->items()->where('barcode', $barcode)->exists();
        if ($existsInGr) {
            Notification::make()
                ->title(__('Gagal Scan'))
                ->body(__('Barang Sudah Terinput'))
                ->warning()
                ->send();
            $this->barcode = '';
            $this->dispatch('focus-barcode');
            return;
        }

        // 2. Cek duplikat barcode di beef_stocks (double scan di stock secara umum)
        $existsInStock = BeefStock::where('barcode', $barcode)->exists();
        if ($existsInStock) {
            Notification::make()
                ->title(__('Gagal Scan'))
                ->body(__('Barcode sudah terdaftar di stock (Duplikat)'))
                ->warning()
                ->send();
            $this->barcode = '';
            $this->dispatch('focus-barcode');
            return;
        }

        // Parse data
        $originCode = substr($barcode, 0, 1);
        $dateStr = substr($barcode, 1, 6);
        $productCode = substr($barcode, 7, 6);
        $gradeId = (int) substr($barcode, 13, 1);
        $weightVal = ((float) substr($barcode, 14, 4)) / 100;
        $pcsVal = (int) substr($barcode, 18, 2);
        $phVal = ((float) substr($barcode, 20, 2)) / 10;

        // Find product
        $product = Product::where('code', $productCode)->first();
        if (!$product) {
            Notification::make()
                ->title(__('Gagal Scan'))
                ->body(__('Produk tidak terdaftar'))
                ->danger()
                ->send();
            $this->barcode = '';
            $this->dispatch('focus-barcode');
            return;
        }

        // 3. Pastikan product_id ada di purchase_product_items (PO)
        $poItem = $this->record->purchaseProduct->items()->where('product_id', $product->id)->first();
        if (!$poItem) {
            Notification::make()
                ->title(__('Gagal Scan'))
                ->body(__('Barang Tidak ada di PO'))
                ->danger()
                ->send();
            $this->barcode = '';
            $this->dispatch('focus-barcode');
            return;
        }

        // Validate grade
        $grade = Grade::find($gradeId);
        if (!$grade) {
            Notification::make()
                ->title(__('Gagal Scan'))
                ->body(__('Grade tidak valid'))
                ->danger()
                ->send();
            $this->barcode = '';
            $this->dispatch('focus-barcode');
            return;
        }

        // Process insertion
        try {
            DB::transaction(function () use ($barcode, $product, $gradeId, $weightVal, $pcsVal, $phVal, $originCode, $poItem, $dateStr) {
                $price = $poItem ? $poItem->price : 0;
                $subtotal = $price * $weightVal;
                
                try {
                    $defaultPackDate = Carbon::createFromFormat('dmy', $dateStr)->format('Y-m-d');
                } catch (\Exception $e) {
                    $defaultPackDate = now()->format('Y-m-d');
                }
                
                $date = Carbon::parse($defaultPackDate);
                if (in_array((int)$gradeId, [1, 3])) { // CHILL or A
                    $expDate = $date->addMonths(3)->format('Y-m-d');
                } else {
                    $expDate = $date->addYear()->format('Y-m-d');
                }

                // 1. Create GoodsReceiptProductItem
                GoodsReceiptProductItem::create([
                    'goods_receipt_product_id' => $this->record->id,
                    'product_id' => $product->id,
                    'grade_id' => $gradeId,
                    'weight' => $weightVal,
                    'qty_pcs' => $pcsVal,
                    'ph_level' => $phVal > 0 ? $phVal : null,
                    'pack_date' => $defaultPackDate,
                    'barcode' => $barcode,
                    'origin' => $originCode === '7' ? 'LOCAL' : 'IMPORT',
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);

                // 2. Create BeefStock
                BeefStock::create([
                    'barcode' => $barcode,
                    'product_id' => $product->id,
                    'warehouse_id' => $this->warehouse_id,
                    'grade_id' => $gradeId,
                    'weight' => $weightVal,
                    'qty_pcs' => $pcsVal,
                    'ph_level' => $phVal > 0 ? $phVal : null,
                    'pack_date' => $defaultPackDate,
                    'exp_date' => $expDate,
                    'origin' => 'GR_BEEF',
                    'status' => 'IN_STOCK',
                ]);

                // 3. Create BeefStockMovement
                BeefStockMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $this->warehouse_id,
                    'condition' => $gradeId,
                    'barcode' => $barcode,
                    'transaction_type' => 'IN_GR_BEEF',
                    'reference_document' => $this->record->gr_number,
                    'weight_in' => $weightVal,
                    'pcs_in' => $pcsVal,
                    'created_by' => Auth::id(),
                ]);
            });

            Notification::make()
                ->title(__('Berhasil Scan'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Error!'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->barcode = '';
        $this->dispatch('focus-barcode');
    }

    public function getSummaryData(): array
    {
        $poItems = $this->record->purchaseProduct->items()->with('product')->get();
        $summary = [];
        foreach ($poItems as $item) {
            $scannedWeight = (float) $this->record->items()->where('product_id', $item->product_id)->sum('weight');
            $scannedPcs = $this->record->items()->where('product_id', $item->product_id)->sum('qty_pcs');
            $balance = $scannedWeight - (float) $item->qty;
            $summary[] = [
                'product_name' => $item->product?->name ?? 'Unknown',
                'po_weight' => (float) $item->qty,
                'scanned_weight' => $scannedWeight,
                'scanned_pcs' => $scannedPcs,
                'balance' => $balance,
                'notes' => $item->note,
            ];
        }
        return $summary;
    }
}
