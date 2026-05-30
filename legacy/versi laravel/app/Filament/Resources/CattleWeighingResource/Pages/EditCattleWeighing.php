<?php

namespace App\Filament\Resources\CattleWeighingResource\Pages;

use App\Filament\Resources\CattleWeighingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\CattleReceiving;
use App\Models\CattleReceivingItem;
use App\Models\CattleWeighingLoss;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class EditCattleWeighing extends EditRecord
{
    protected static string $resource = CattleWeighingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $record->load(['items.receivingItem', 'receiving.purchaseOrder', 'receiving.supplier']);

        $data['grc_number_display'] = $record->receiving->receiving_number ?? '-';
        $data['po_number_display'] = $record->receiving->purchaseOrder->po_number ?? '-';
        $data['supplier_name_display'] = $record->receiving->supplier->name ?? '-';

        $weighingItems = [];
        foreach ($record->items as $item) {
            $weighingItems[(string) Str::uuid()] = [
                'cattle_receiving_item_id' => $item->cattle_receiving_item_id,
                'eartag_display' => $item->receivingItem->eartag ?? '-',
                'initial_weight_display' => $item->receivingItem->initial_weight ?? 0,
                'weight' => $item->weight,
                'notes' => $item->notes,
            ];
        }
        $data['weighing_items'] = $weighingItems;

        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        DB::transaction(function () use ($record, $data) {
            // --- 1. BACKUP HARGA MANUAL SEBELUM DIHAPUS ---
            // Kita cari dokumen loss lama, trus simpan harga custom-nya berdasarkan id penerimaan sapi
            $existingLoss = CattleWeighingLoss::with('items.weighingItem')->where('cattle_weighing_id', $record->id)->first();
            $preservedPrices = [];

            if ($existingLoss) {
                foreach ($existingLoss->items as $lItem) {
                    if ($lItem->weighingItem) {
                        $preservedPrices[$lItem->weighingItem->cattle_receiving_item_id] = $lItem->price_per_kg;
                    }
                }
            }

            // Harga PO sebagai fallback kalau ternyata sapinya belum punya history harga manual
            $receiving = CattleReceiving::with('purchaseOrder.items')->find($record->cattle_receiving_id);
            $poPrices = $receiving->purchaseOrder->items->pluck('price_per_kg', 'cattle_category_id');

            // --- 2. UPDATE HEADER & HAPUS DETAIL LAMA ---
            $record->update([
                'weigh_date' => $data['weigh_date'],
                'note' => $data['note'] ?? null,
            ]);

            // Ingat: Saat ini dihapus, cattle_weighing_loss_items juga akan otomatis ikut terhapus karena Cascade!
            $record->items()->forceDelete();

            $totalRecv = 0;
            $totalAct = 0;
            $totalLossWt = 0;
            $totalCost = 0;
            $lossItemsData = [];

            // --- 3. RECREATE DETAIL & HITUNG ULANG ---
            if (isset($data['weighing_items'])) {
                foreach ($data['weighing_items'] as $item) {
                    $wItem = $record->items()->create([
                        'cattle_receiving_item_id' => $item['cattle_receiving_item_id'],
                        'weight' => $item['weight'],
                        'notes' => $item['notes'] ?? null,
                    ]);

                    $grcItem = CattleReceivingItem::find($item['cattle_receiving_item_id']);
                    if ($grcItem && !empty($item['weight'])) {
                        $recvWt = (float)$grcItem->initial_weight;
                        $actWt = (float)$item['weight'];
                        $lossWt = $recvWt - $actWt;
                        $catId = $grcItem->cattle_category_id;

                        // KUNCI: Pakai harga backup dulu. Kalau kosong, baru comot dari PO.
                        $price = $preservedPrices[$grcItem->id] ?? ($poPrices[$catId] ?? 0);
                        $cost = $lossWt * $price;

                        $totalRecv += $recvWt;
                        $totalAct += $actWt;
                        $totalLossWt += $lossWt;
                        $totalCost += $cost;

                        $lossItemsData[] = [
                            'cattle_weighing_item_id' => $wItem->id,
                            'cattle_category_id' => $catId,
                            'eartag' => $grcItem->eartag,
                            'receive_weight' => $recvWt,
                            'actual_weight' => $actWt,
                            'loss_weight' => $lossWt,
                            'price_per_kg' => $price,
                            'loss_cost' => $cost,
                        ];
                    }
                }
            }

            // --- 4. UPDATE DOKUMEN LOSS HEADER ---
            if ($existingLoss) {
                $existingLoss->update([
                    'loss_date' => $data['weigh_date'],
                    'total_receive_weight' => $totalRecv,
                    'total_actual_weight' => $totalAct,
                    'total_loss_weight' => $totalLossWt,
                    'total_loss_cost' => $totalCost,
                ]);

                // Masukkan ulang detail yang sudah bawa harga aman tadi
                foreach ($lossItemsData as $lData) {
                    $existingLoss->items()->create($lData);
                }

                // Update Terminal Financial Loss
                if ($existingLoss->financialLoss) {
                    $existingLoss->financialLoss->update([
                        'loss_date' => $data['weigh_date'],
                        'total_amount' => $totalCost,
                    ]);
                }
            }
        });

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
