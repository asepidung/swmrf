<?php

namespace App\Policies;

use App\Models\Warehouse;
use App\Models\User;

class WarehousePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_warehouses'); }
    public function view(User $user, Warehouse $model): bool { return $user->hasPermission('view_warehouses'); }
    public function create(User $user): bool { return $user->hasPermission('create_warehouses'); }
    public function update(User $user, Warehouse $model): bool { return $user->hasPermission('edit_warehouses'); }
    public function delete(User $user, Warehouse $model): bool { return $user->hasPermission('delete_warehouses'); }
    public function restore(User $user, Warehouse $model): bool { return $user->hasPermission('delete_warehouses'); }
    public function forceDelete(User $user, Warehouse $model): bool { return $user->hasPermission('delete_warehouses'); }
}
