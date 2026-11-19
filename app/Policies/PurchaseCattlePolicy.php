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
    public function restore(User $user, PurchaseCattle $model): bool { return false; }
    public function forceDelete(User $user, PurchaseCattle $model): bool {
        // FD-01 (keputusan Owner): hapus permanen dokumen transaksi hanya programmer.
        return $user->isProgrammer();
    }
    public function deleteAny(User $user): bool { return $user->hasPermission('delete_purchase_cattles'); }
    public function restoreAny(User $user): bool { return false; }
    public function forceDeleteAny(User $user): bool {
        // FD-01 (keputusan Owner): hapus permanen dokumen transaksi hanya programmer.
        return $user->isProgrammer();
    }
}