<?php

namespace App\Filament\Resources\LogisticReceivingResource\Pages;

use App\Filament\Resources\LogisticReceivingResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\LogisticReceiving;
use App\Models\LogisticPurchaseOrder;
use App\Models\LogisticReceivingItem;
use App\Models\AccountPayable;
use Illuminate\Support\Facades\Auth;

class CreateLogisticReceiving extends CreateRecord
{
    protected static string $resource = LogisticReceivingResource::class;

    // Properti sementara buat nyimpen data item dari form UI
    public array $tempItems = [];

    public function mount(): void
    {
        $poId = request()->query('po_id');

        if (!$poId) {
            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        parent::mount();

        /** @var LogisticPurchaseOrder|null $po */
        $po = LogisticPurchaseOrder::with(['items.item', 'supplier'])->find($poId);

        if ($po) {
            $items = [];

            foreach ($po->items as $poItem) {
                $received = LogisticReceivingItem::whereHas('receiving', function ($q) use ($poId) {
                    $q->where('logistic_purchase_order_id', $poId);
                })
                    ->where('logistic_item_id', $poItem->logistic_item_id)
                    ->sum('qty_received');

                $sisa = $poItem->qty - $received;

                $items[] = [
                    'logistic_item_id'   => $poItem->logistic_item_id,
                    'item_name_display'  => $poItem->item->name ?? 'Unknown Item',
                    'qty_ordered'        => $sisa > 0 ? $sisa : 0,
                    'qty_received'       => null,
                ];
            }

            $this->form->fill([
                'logistic_purchase_order_id' => $po->id,
                'po_number_display'          => $po->po_number,
                'supplier_id'                => $po->supplier_id,
                'supplier_name_display'      => $po->supplier->name ?? '',
                'receive_date'               => now()->format('Y-m-d'),
                'items'                      => $items,
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        $currentYear2Digit = date('y');
        $currentYear4Digit = date('Y');
        $countThisYear = LogisticReceiving::whereYear('created_at', $currentYear4Digit)->count();
        $urut = $countThisYear + 1;

        $data['receiving_number'] = 'GRL#' . $currentYear2Digit . str_pad($urut, 3, '0', STR_PAD_LEFT);

        if (isset($data['items'])) {
            $this->tempItems = $data['items'];
            unset($data['items']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var \App\Models\LogisticReceiving $receiving */
        $receiving = $this->record;
        $poId = $receiving->logistic_purchase_order_id;

        /** @var \App\Models\LogisticPurchaseOrder|null $po */
        $po = \App\Models\LogisticPurchaseOrder::with(['items', 'supplier', 'receivings.items'])->find($poId);

        if (!$po) {
            return;
        }

        // ==========================================
        // 1. SIMPAN BARANG FISIK & UPDATE STOK
        // ==========================================
        foreach ($this->tempItems as $item) {
            $poItem = $po->items->where('logistic_item_id', $item['logistic_item_id'])->first();
            $price = $poItem ? $poItem->price : 0;
            $qty = (int) $item['qty_received'];

            if ($qty > 0) {
                $subtotal = $price * $qty;

                // Simpan ke detail GR
                \App\Models\LogisticReceivingItem::create([
                    'logistic_receiving_id' => $receiving->id,
                    'logistic_item_id' => $item['logistic_item_id'],
                    'qty_received' => $qty,
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);

                // Update Stok
                $stockRecord = \App\Models\LogisticStock::firstOrCreate(
                    ['logistic_item_id' => $item['logistic_item_id']],
                    ['qty' => 0]
                );

                $currentStock = $stockRecord->qty;
                $newStock = $currentStock + $qty;

                $stockRecord->update(['qty' => $newStock]);

                // Catat Mutasi
                \App\Models\LogisticStockMovement::create([
                    'logistic_item_id' => $item['logistic_item_id'],
                    'transaction_type' => 'GR',
                    'reference_document' => $receiving->receiving_number,
                    'qty_in' => $qty,
                    'qty_out' => 0,
                    'balance' => $newStock,
                    'note' => "Penerimaan dari '" . ($po->supplier->name ?? 'Unknown') . "' " . $po->po_number,
                    'created_by' => \Illuminate\Support\Facades\Auth::id(),
                ]);
            }
        }

        // ==========================================
        // 2. MESIN AUTO-STATUS & AUTO-TAGIHAN
        // ==========================================
        $po->load('receivings.items', 'supplier');

        $totalQtyOrdered = $po->items->sum('qty');
        $totalQtyReceived = 0;
        $dpp = 0;

        foreach ($po->receivings as $gr) {
            foreach ($gr->items as $grItem) {
                $totalQtyReceived += $grItem->qty_received;
                $dpp += $grItem->subtotal;
            }
        }

        $taxRate = $po->supplier->has_tax ? 11 : 0;
        $taxAmount = $dpp * ($taxRate / 100);
        $grandTotal = $dpp + $taxAmount;

        $topDays = $po->supplier->term_of_payment ?? 0;
        $dueDate = now()->addDays($topDays);

        // 3. LOGIKA KEPUTUSAN STATUS & UNIVERSAL AP (Polymorphic)
        if ($totalQtyReceived >= $totalQtyOrdered) {
            $po->update(['status' => 'COMPLETED']);

            // MENCARI AP SECARA POLYMORPHIC
            $existingAp = AccountPayable::where('payable_id', $po->id)
                ->where('payable_type', get_class($po))
                ->first();

            if ($existingAp) {
                $existingAp->update([
                    'dpp_amount'   => $dpp,
                    'tax_amount'   => $taxAmount,
                    'total_amount' => $grandTotal,
                    'balance_due'  => $grandTotal - $existingAp->paid_amount,
                    'due_date'     => $dueDate,
                ]);
            } else {
                if ($grandTotal > 0) {
                    AccountPayable::create([
                        'payable_id'   => $po->id,
                        'payable_type' => get_class($po),
                        'supplier_id'  => $po->supplier_id,
                        'dpp_amount'   => $dpp,
                        'tax_amount'   => $taxAmount,
                        'total_amount' => $grandTotal,
                        'paid_amount'  => 0,
                        'balance_due'  => $grandTotal,
                        'status'       => 'UNPAID',
                        'due_date'     => $dueDate,
                        'note'         => $receiving->note,
                        'created_by'   => \Illuminate\Support\Facades\Auth::id(),
                    ]);
                }
            }
        } else {
            $po->update(['status' => 'PARTIAL']);
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
