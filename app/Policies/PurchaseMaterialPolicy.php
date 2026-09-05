<?php

namespace App\Policies;

use App\Models\PurchaseMaterial;
use App\Models\User;

class PurchaseMaterialPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_purchase_materials'); }
    public function view(User $user, PurchaseMaterial $model): bool { return $user->hasPermission('view_purchase_materials'); }
    public function create(User $user): bool { return false; }
    public function update(User $user, PurchaseMaterial $model): bool { return false; }
        /** Dulu menyebut `delete_purchase_materials`, izin yang tidak pernah ada. */
    public function delete(User $user, PurchaseMaterial $model): bool { return false; }
    public function restore(User $user, PurchaseMaterial $model): bool { return false; }
    public function forceDelete(User $user, PurchaseMaterial $model): bool { return false; }
}
