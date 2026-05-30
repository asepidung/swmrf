<?php

namespace App\Filament\Resources\CattleWeighingResource\Pages;

use App\Filament\Resources\CattleWeighingResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\CattleReceiving;
use App\Models\CattleReceivingItem;
use App\Models\CattleWeighing;
use App\Models\CattleWeighingLoss;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCattleWeighing extends CreateRecord
{
    protected static string $resource = CattleWeighingResource::class;

    protected function getFormActions(): array
    {
        return [$this->getCreateFormAction(), $this->getCancelFormAction()];
    }

    public function mount(): void
    {
        $grcId = request()->query('grc_id');
        if (!$grcId) {
            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        parent::mount();

        $grc = CattleReceiving::with(['items', 'purchaseOrder', 'supplier'])->find($grcId);

        if ($grc) {
            $generatedRows = [];
            foreach ($grc->items as $item) {
                $generatedRows[(string) Str::uuid()] = [
                    'cattle_receiving_item_id' => $item->id,
                    'eartag_display' => $item->eartag,
                    'initial_weight_display' => $item->initial_weight,
                    'weight' => null,
                    'notes' => null,
                ];
            }

            $this->form->fill([
                'cattle_receiving_id' => $grc->id,
                'grc_number_display' => $grc->receiving_number,
                'po_number_display' => $grc->purchaseOrder->po_number,
                'supplier_name_display' => $grc->supplier->name,
                'weigh_date' => now()->format('Y-m-d'),
                'weighing_items' => $generatedRows,
            ]);
        }
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return DB::transaction(function () use ($data) {
            $currentYear2Digit = date('y');
            $currentYear4Digit = date('Y');

            $latest = CattleWeighing::withTrashed()
                ->whereYear('created_at', $currentYear4Digit)
                ->latest('id')
                ->first();

            $urut = 1;
            if ($latest && preg_match('/CWG-' . $currentYear2Digit . '(\d{3})/', $latest->weigh_no, $matches)) {
                $urut = (int)$matches[1] + 1;
            }
            $wghNumber = 'CWG-' . $currentYear2Digit . str_pad((string)$urut, 3, '0', STR_PAD_LEFT);

            $headerData = collect($data)->except(['weighing_items', 'grc_number_display', 'po_number_display', 'supplier_name_display'])->toArray();
            $headerData['weigh_no'] = $wghNumber;
            $headerData['created_by'] = (int) Auth::id();

            // 1. Simpan Header Timbangan
            $weighing = CattleWeighing::create($headerData);

            // --- PERSIAPAN AUTO-LOSS: Tarik Harga dari PO ---
            $receiving = CattleReceiving::with('purchaseOrder.items')->find($data['cattle_receiving_id']);
            $poPrices = $receiving->purchaseOrder->items->pluck('price_per_kg', 'cattle_category_id');

            $totalRecv = 0;
            $totalAct = 0;
            $totalLossWt = 0;
            $totalCost = 0;
            $lossItemsData = [];

            // 2. Simpan Detail Timbangan & Hitung Selisih
            if (isset($data['weighing_items'])) {
                foreach ($data['weighing_items'] as $item) {
                    $wItem = $weighing->items()->create([
                        'cattle_receiving_item_id' => $item['cattle_receiving_item_id'],
                        'weight' => $item['weight'],
                        'notes' => $item['notes'] ?? null,
                    ]);

                    // Kalkulasi Loss per sapi
                    $grcItem = CattleReceivingItem::find($item['cattle_receiving_item_id']);
                    if ($grcItem && !empty($item['weight'])) {
                        $recvWt = (float)$grcItem->initial_weight;
                        $actWt = (float)$item['weight'];
                        $lossWt = $recvWt - $actWt;
                        $catId = $grcItem->cattle_category_id;

                        $price = (float)($poPrices[$catId] ?? 0);
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

            // --- 3. AUTO CREATE HEADER LOSS ---
            // Langsung inject nomor CWG, tidak perlu generate CWL lagi
            $lossHeader = CattleWeighingLoss::create([
                'cattle_weighing_id' => $weighing->id,
                'loss_number' => $wghNumber,
                'loss_date' => $data['weigh_date'],
                'total_receive_weight' => $totalRecv,
                'total_actual_weight' => $totalAct,
                'total_loss_weight' => $totalLossWt,
                'total_loss_cost' => $totalCost,
                'created_by' => (int) Auth::id(),
            ]);

            // Insert Detail Loss
            foreach ($lossItemsData as $lData) {
                $lossHeader->items()->create($lData);
            }

            // --- 4. AUTO CREATE FINANCIAL LOSS (Terminal) ---
            $lossHeader->financialLoss()->create([
                'reference_number' => $wghNumber,
                'loss_date' => $data['weigh_date'],
                'total_amount' => $totalCost,
                'status' => 'POSTED',
                'note' => 'Susut Timbang Ulang Sapi',
                'created_by' => (int) Auth::id(),
            ]);

            return $weighing;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
