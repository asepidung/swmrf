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
}
