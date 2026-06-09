<?php

namespace App\Observers;

use App\Models\GoodsReceiptMaterialItem;

class GoodsReceiptMaterialItemObserver
{
    public function saving(GoodsReceiptMaterialItem $item): void
    {
        if ($item->isDirty('qty_received') || $item->isDirty('price')) {
            $item->subtotal = $item->qty_received * $item->price;
        }
    }

    public function created(GoodsReceiptMaterialItem $item): void
    {
        $gr = $item->goodsReceiptMaterial;
        if ($gr) {
            \App\Services\StockService::adjustStock(
                $item->material_id,
                (float) $item->qty_received,
                'GR',
                $gr->gr_number,
                "Penerimaan barang dari GR " . $gr->gr_number
            );
        }
    }

    public function updated(GoodsReceiptMaterialItem $item): void
    {
        if ($item->wasChanged('qty_received')) {
            $delta = (float) $item->qty_received - (float) $item->getOriginal('qty_received');
            $gr = $item->goodsReceiptMaterial;
            if ($gr) {
                \App\Services\StockService::adjustStock(
                    $item->material_id,
                    $delta,
                    'ADJUSTMENT',
                    $gr->gr_number,
                    "Koreksi kuantitas GR " . $gr->gr_number
                );
            }
        }
    }
}
