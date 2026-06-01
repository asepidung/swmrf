<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_suppliers'); }
    public function view(User $user, Supplier $model): bool { return $user->hasPermission('view_suppliers'); }
    public function create(User $user): bool { return $user->hasPermission('create_suppliers'); }
    public function update(User $user, Supplier $model): bool { return $user->hasPermission('edit_suppliers'); }
    public function delete(User $user, Supplier $model): bool { return $user->hasPermission('delete_suppliers'); }
    public function restore(User $user, Supplier $model): bool { return $user->hasPermission('delete_suppliers'); }
    public function forceDelete(User $user, Supplier $model): bool { return $user->hasPermission('delete_suppliers'); }
}