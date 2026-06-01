<?php

namespace App\Policies;

use App\Models\PurchaseCattle;
use App\Models\User;

class PurchaseCattlePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_purchase_cattles'); }
    public function view(User $user, PurchaseCattle $model): bool { return $user->hasPermission('view_purchase_cattles'); }
    public function create(User $user): bool { return $user->hasPermission('create_purchase_cattles'); }
    public function update(User $user, PurchaseCattle $model): bool { return $user->hasPermission('edit_purchase_cattles'); }
    public function delete(User $user, PurchaseCattle $model): bool { return $user->hasPermission('delete_purchase_cattles'); }
    public function restore(User $user, PurchaseCattle $model): bool { return $user->hasPermission('delete_purchase_cattles'); }
    public function forceDelete(User $user, PurchaseCattle $model): bool { return $user->hasPermission('delete_purchase_cattles'); }
}