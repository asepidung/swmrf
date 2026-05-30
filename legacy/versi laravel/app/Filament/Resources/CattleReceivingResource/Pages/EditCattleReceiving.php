<?php

namespace App\Filament\Resources\CattleReceivingResource\Pages;

use App\Filament\Resources\CattleReceivingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\CattleReceiving;

class EditCattleReceiving extends EditRecord
{
    protected static string $resource = CattleReceivingResource::class;

    // 1. SUNTIK DATA BIAR BISA DIEDIT
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        // FIX RELASI: Ubah 'items.category' jadi 'items.cattleCategory' menyesuaikan nama di Model
        $record->load(['items.cattleCategory', 'purchaseOrder', 'supplier']);

        $data['po_number_display'] = $record->purchaseOrder->po_number ?? '-';
        $data['supplier_name_display'] = $record->supplier->name ?? '-';

        $receivingItems = [];
        foreach ($record->items as $item) {
            $receivingItems[(string) Str::uuid()] = [
                'cattle_category_id' => $item->cattle_category_id,
                'eartag' => $item->eartag,
                'initial_weight' => $item->initial_weight,
                'notes' => $item->notes,
            ];
        }
        $data['receiving_items'] = $receivingItems;

        return $data;
    }

    // 2. SIMPAN MANUAL PERUBAHANNYA KE DATABASE
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        DB::transaction(function () use ($record, $data) {
            // Update tabel cattle_receivings (Header)
            $record->update([
                'receive_date' => $data['receive_date'],
                'doc_no' => $data['doc_no'] ?? null,
                'sv_ok' => $data['sv_ok'] ?? false,
                'skkh_ok' => $data['skkh_ok'] ?? false,
                'note' => $data['note'] ?? null,
            ]);

            // Bersihkan baris sapi lama, timpa dengan data sapi yang baru di-edit
            $record->items()->forceDelete();

            // --- PERSIAPAN AP: AMBIL HARGA DARI PO ITEMS ---
            $poItems = \App\Models\CattlePurchaseOrderItem::where('cattle_purchase_order_id', $record->cattle_purchase_order_id)
                ->pluck('price_per_kg', 'cattle_category_id');

            $newTotalDpp = 0;

            if (isset($data['receiving_items'])) {
                foreach ($data['receiving_items'] as $item) {
                    if (!empty($item['eartag'])) {
                        $record->items()->create([
                            'cattle_category_id' => $item['cattle_category_id'],
                            'eartag' => strtoupper(trim($item['eartag'])),
                            'initial_weight' => $item['initial_weight'],
                            'notes' => $item['notes'],
                        ]);

                        // --- HITUNG DPP BARU PER SAPI ---
                        if (!empty($item['initial_weight'])) {
                            $hargaPerKg = $poItems[$item['cattle_category_id']] ?? 0;
                            $newTotalDpp += ((float)$item['initial_weight'] * (float)$hargaPerKg);
                        }
                    }
                }
            }

            // ==========================================
            // 3. UPDATE ACCOUNT PAYABLE (HUTANG)
            // ==========================================
            $ap = \App\Models\AccountPayable::where('payable_type', CattleReceiving::class)
                ->where('payable_id', $record->id)
                ->first();

            if ($ap) {
                $supplier = \App\Models\Supplier::find($record->supplier_id);

                $newTaxAmount = 0;
                if ($supplier && $supplier->has_tax == 1) {
                    $newTaxAmount = $newTotalDpp * 0.11; // PPN 11%
                }

                $newTotalAmount = $newTotalDpp + $newTaxAmount;
                $newDueDate = \Carbon\Carbon::parse($data['receive_date'])->addDays($supplier->term_of_payment ?? 0);

                // Saldo sisa = Total Baru dikurangi nominal yang udah pernah dicicil/dibayar
                $newBalanceDue = $newTotalAmount - $ap->paid_amount;

                // Update data AP yang sudah ada
                $ap->update([
                    'dpp_amount' => $newTotalDpp,
                    'tax_amount' => $newTaxAmount,
                    'total_amount' => $newTotalAmount,
                    'balance_due' => $newBalanceDue,
                    'due_date' => $newDueDate,
                    'invoice_number' => !empty($data['doc_no']) ? $data['doc_no'] : $record->receiving_number,
                ]);
            }
        });

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    // Redirect ke halaman index setelah selesai edit
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
