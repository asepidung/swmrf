<?php

namespace App\Policies;

use App\Models\MaterialStockMovement;
use App\Models\User;

class MaterialStockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_material_stock_movements');
    }

    public function view(User $user, MaterialStockMovement $model): bool
    {
        return $user->hasPermission('view_material_stock_movements');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MaterialStockMovement $model): bool
    {
        return false;
    }

    public function delete(User $user, MaterialStockMovement $model): bool
    {
        return false;
    }

    public function restore(User $user, MaterialStockMovement $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, MaterialStockMovement $model): bool
    {
        return false;
    }
}
