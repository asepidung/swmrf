<?php

namespace App\Filament\Resources\TallySheetResource\Pages;

use App\Filament\Resources\TallySheetResource;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\TallyItem;
use App\Models\TallySheet;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class ScanTally extends Page
{
    use WithPagination;

    protected static string $resource = TallySheetResource::class;
    protected static string $view = 'filament.resources.tally-sheet-resource.pages.scan-tally';
    protected ?string $heading = '';

    public TallySheet $record;
    public $barcode = '';

    public function mount(TallySheet $record): void
    {
        $this->record = $record;
    }

    /* Mengambil data item tally dengan pagination 10 baris per halaman */
    public function getScannedItemsProperty()
    {
        return $this->record->items()
            ->with(['product', 'grade'])
            ->latest()
            ->paginate(10);
    }

    public function submitBarcode()
    {
        $barcodeInput = trim($this->barcode);
        if (empty($barcodeInput)) return;

        DB::beginTransaction();
        try {
            $stock = BeefStock::where('barcode', $barcodeInput)->first();

            if (!$stock) {
                Notification::make()
                    ->title('Gagal!')
                    ->body('Barcode tidak terdaftar di Stock aktif.')
                    ->danger()
                    ->send();
                $this->barcode = '';
                return;
            }

            /* Insert data salinan dari beef_stocks ke tally_items */
            TallyItem::create([
                'tally_sheet_id' => $this->record->id,
                'barcode' => $stock->barcode,
                'product_id' => $stock->product_id,
                'actual_weight' => $stock->weight,
                'grade_id' => $stock->grade_id,
                'warehouse_id' => $stock->warehouse_id,
                'qty_pcs' => $stock->qty_pcs,
                'ph_level' => $stock->ph_level,
                'pack_date' => $stock->pack_date,
                'exp_date' => $stock->exp_date,
                'origin' => $stock->origin,
            ]);

            /* Mencatat pergerakan barang keluar di ledger */
            BeefStockMovement::create([
                'product_id' => $stock->product_id,
                'warehouse_id' => $stock->warehouse_id,
                'grade_id' => $stock->grade_id,
                'barcode' => $stock->barcode,
                'transaction_type' => 'TALLY_OUT',
                'reference_document' => $this->record->tally_number,
                'weight_out' => $stock->weight,
                'pcs_out' => $stock->qty_pcs,
                'created_by' => auth()->id(),
            ]);

            /* Menghapus barang dari stok aktif setelah berhasil masuk tally */
            $stock->delete();

            DB::commit();

            Notification::make()->title('Berhasil Masuk Tally')->success()->send();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }

        $this->barcode = '';
    }

    public function hapusItem($itemId)
    {
        DB::beginTransaction();
        try {
            $tallyItem = TallyItem::find($itemId);
            if (!$tallyItem) return;

            /* Mengembalikan barang ke tabel stok aktif dengan data utuh */
            BeefStock::create([
                'barcode' => $tallyItem->barcode,
                'product_id' => $tallyItem->product_id,
                'warehouse_id' => $tallyItem->warehouse_id,
                'grade_id' => $tallyItem->grade_id,
                'weight' => $tallyItem->actual_weight,
                'qty_pcs' => $tallyItem->qty_pcs,
                'ph_level' => $tallyItem->ph_level,
                'pack_date' => $tallyItem->pack_date,
                'exp_date' => $tallyItem->exp_date,
                'origin' => $tallyItem->origin,
                'status' => 'IN_STOCK',
            ]);

            /* Mencatat pergerakan barang masuk kembali ke ledger */
            BeefStockMovement::create([
                'product_id' => $tallyItem->product_id,
                'warehouse_id' => $tallyItem->warehouse_id,
                'grade_id' => $tallyItem->grade_id,
                'barcode' => $tallyItem->barcode,
                'transaction_type' => 'TALLY_RETURN',
                'reference_document' => $this->record->tally_number,
                'weight_in' => $tallyItem->actual_weight,
                'pcs_in' => $tallyItem->qty_pcs,
                'created_by' => auth()->id(),
            ]);

            /* Menghapus data dari tally_items */
            $tallyItem->delete();

            DB::commit();

            Notification::make()->title('Item Dikembalikan ke Stock')->warning()->send();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }
}
