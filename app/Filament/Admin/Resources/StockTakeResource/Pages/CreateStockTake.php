<?php

namespace App\Filament\Admin\Resources\StockTakeResource\Pages;

use App\Filament\Admin\Resources\StockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Support\DocumentNumber;
use App\Models\StockTake;
use App\Models\BeefStock;
use App\Models\StockTakeItem;
use Illuminate\Support\Facades\DB;

class CreateStockTake extends CreateRecord
{
    protected static string $resource = StockTakeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function canCreateAnother(): bool
    {
        return false;
    }

    /**
     * Tidak boleh ada DUA opname berjalan sekaligus.
     *
     * Dua dokumen berarti dua snapshot dan dua penerapan selisih ke stok yang
     * sama -- angka yang sama dipotong atau ditambah dua kali. Pembekuan tidak
     * menahannya, karena yang dibekukan penulisan STOK, bukan pembuatan
     * dokumen opnamenya.
     *
     * Ditolak di sini, dengan menyebut dokumen mana yang sedang berjalan --
     * bukan dengan menyembunyikan tombolnya, yang hanya membuat orang bertanya
     * ke mana perginya.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $berjalan = StockTake::whereIn('status', StockTake::STATUS_SEDANG_MENGHITUNG)->first();

        if ($berjalan) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.period' => __('A stock count is already running (:doc). Finish it first.', [
                    'doc' => $berjalan->document_number,
                ]),
            ]);
        }

        $date = \Carbon\Carbon::parse($data['date']);
        $yymm = $date->format('ym');
        
        // Bulannya sudah terkandung di prefix (ST#yymm), jadi penyaring
        // whereYear/whereMonth tidak lagi diperlukan.
        $data['document_number'] = DocumentNumber::next(
            query: StockTake::withTrashed(),
            column: 'document_number',
            prefix: 'ST#'.$yymm,
            padding: 3,
        );
        $data['created_by'] = auth()->id();
        $data['status'] = StockTake::STATUS_IN_PROGRESS;
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // Take a snapshot of ALL active warehouse stock (Global Stock Take)
        // Insert them as MISSING into stock_take_items
        
        $stocks = BeefStock::where('status', 'IN_STOCK')->get();
            
        $itemsToInsert = [];
        foreach ($stocks as $stock) {
            $itemsToInsert[] = [
                'stock_take_id' => $record->id,
                'barcode' => $stock->barcode,
                'product_id' => $stock->product_id,
                'warehouse_id' => $stock->warehouse_id,
                'grade_id' => $stock->grade_id,
                'weight' => $stock->weight,
                'qty_pcs' => $stock->qty_pcs,
                'ph_level' => $stock->ph_level,
                'pack_date' => $stock->pack_date,
                'status' => 'MISSING',
                'is_manual' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // Satu transaksi untuk seluruh snapshot.
        //
        // Sebelumnya ribuan baris disisipkan tanpa transaksi. Kalau gagal di
        // tengah, dokumennya tetap ada dengan snapshot SEPARUH -- dan snapshot
        // separuh tidak terlihat sebagai kerusakan: ia terbaca sebagai opname
        // yang barangnya memang cuma segitu, lalu selisihnya dihapus dari stok
        // saat opnamenya diselesaikan.
        if (!empty($itemsToInsert)) {
            DB::transaction(function () use ($itemsToInsert) {
                foreach (array_chunk($itemsToInsert, 500) as $chunk) {
                    StockTakeItem::insert($chunk);
                }
            });
        }
    }
}
