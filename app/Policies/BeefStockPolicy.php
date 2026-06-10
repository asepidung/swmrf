<?php

namespace App\Policies;

use App\Models\BeefStock;
use App\Models\User;

class BeefStockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_beef_stocks');
    }

    public function view(User $user, BeefStock $model): bool
    {
        return $user->hasPermission('view_beef_stocks');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_beef_stocks');
    }

    public function update(User $user, BeefStock $model): bool
    {
        return $user->hasPermission('edit_beef_stocks');
    }

    public function delete(User $user, BeefStock $model): bool
    {
        return $user->hasPermission('delete_beef_stocks');
    }
}
