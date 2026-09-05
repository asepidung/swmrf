<?php

namespace App\Filament\Admin\Resources\MaterialStockTakeResource\Pages;

use App\Filament\Admin\Resources\MaterialStockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Material;
use App\Models\MaterialStockTakeItem;
use Illuminate\Support\Facades\DB;

class CreateMaterialStockTake extends CreateRecord
{
    protected static string $resource = MaterialStockTakeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
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
        $berjalan = \App\Models\MaterialStockTake::whereIn(
            'status',
            \App\Models\MaterialStockTake::STATUS_SEDANG_MENGHITUNG,
        )->first();

        if ($berjalan) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.period' => __('A stock count is already running (:doc). Finish it first.', [
                    'doc' => $berjalan->document_number,
                ]),
            ]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        // Fetch all materials that are shown in stock
        $materials = Material::where('show_in_stock', true)->get();

        $items = [];
        foreach ($materials as $material) {
            // Get current system stock
            $systemQty = \App\Models\MaterialStock::where('material_id', $material->id)->sum('qty') ?? 0;

            $items[] = [
                'material_stock_take_id' => $record->id,
                'material_id' => $material->id,
                'system_qty' => $systemQty,
                'physical_qty' => null,
                'difference_qty' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Satu transaksi untuk seluruh snapshot, dipecah per bagian.
        //
        // Sebelumnya seluruh barisnya disisipkan sekaligus tanpa transaksi.
        // Snapshot separuh tidak terlihat sebagai kerusakan: ia terbaca
        // sebagai opname yang materialnya memang cuma segitu.
        if (!empty($items)) {
            DB::transaction(function () use ($items) {
                foreach (array_chunk($items, 500) as $bagian) {
                    MaterialStockTakeItem::insert($bagian);
                }
            });
        }
    }
}
