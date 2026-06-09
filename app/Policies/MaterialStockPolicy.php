<?php

namespace App\Policies;

use App\Models\MaterialStock;
use App\Models\User;

class MaterialStockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_material_stocks');
    }

    public function view(User $user, MaterialStock $model): bool
    {
        return $user->hasPermission('view_material_stocks');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MaterialStock $model): bool
    {
        return false;
    }

    public function delete(User $user, MaterialStock $model): bool
    {
        return false;
    }

    public function restore(User $user, MaterialStock $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, MaterialStock $model): bool
    {
        return false;
    }
}
