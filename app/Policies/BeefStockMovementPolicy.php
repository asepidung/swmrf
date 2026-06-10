<?php

namespace App\Policies;

use App\Models\BeefStockMovement;
use App\Models\User;

class BeefStockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_beef_stock_movements');
    }

    public function view(User $user, BeefStockMovement $model): bool
    {
        return $user->hasPermission('view_beef_stock_movements');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_beef_stock_movements');
    }

    public function update(User $user, BeefStockMovement $model): bool
    {
        return $user->hasPermission('edit_beef_stock_movements');
    }

    public function delete(User $user, BeefStockMovement $model): bool
    {
        return $user->hasPermission('delete_beef_stock_movements');
    }
}
