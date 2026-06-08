<?php

namespace App\Policies;

use App\Models\GoodsReceiptMaterial;
use App\Models\User;

class GoodsReceiptMaterialPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_gr_materials'); }
    public function view(User $user, GoodsReceiptMaterial $model): bool { return $user->hasPermission('view_gr_materials'); }
    public function create(User $user): bool { return $user->hasPermission('create_gr_materials'); }
    public function update(User $user, GoodsReceiptMaterial $model): bool { return $user->hasPermission('edit_gr_materials'); }
    public function delete(User $user, GoodsReceiptMaterial $model): bool { return $user->hasPermission('delete_gr_materials'); }
    public function restore(User $user, GoodsReceiptMaterial $model): bool { return $user->hasPermission('delete_gr_materials'); }
    public function forceDelete(User $user, GoodsReceiptMaterial $model): bool { return $user->hasPermission('delete_gr_materials'); }
}
