<?php

namespace App\Policies;

use App\Models\GoodsReceiptProduct;
use App\Models\User;

class GoodsReceiptProductPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_goods_receipt_products'); }
    public function view(User $user, GoodsReceiptProduct $model): bool { return $user->hasPermission('view_goods_receipt_products'); }
    public function create(User $user): bool { return $user->hasPermission('create_goods_receipt_products'); }
    public function update(User $user, GoodsReceiptProduct $model): bool { return $user->hasPermission('edit_goods_receipt_products'); }
    public function delete(User $user, GoodsReceiptProduct $model): bool { return $user->hasPermission('delete_goods_receipt_products'); }
    public function restore(User $user, GoodsReceiptProduct $model): bool { return $user->hasPermission('delete_goods_receipt_products'); }
    public function forceDelete(User $user, GoodsReceiptProduct $model): bool { return $user->hasPermission('delete_goods_receipt_products'); }
}
