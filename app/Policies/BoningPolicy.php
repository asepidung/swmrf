<?php

namespace App\Policies;

use App\Models\Boning;
use App\Models\User;

class BoningPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_bonings');
    }

    public function view(User $user, Boning $model): bool
    {
        return $user->hasPermission('view_bonings');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_bonings');
    }

    public function update(User $user, Boning $model): bool
    {
        return $user->hasPermission('edit_bonings');
    }

    public function delete(User $user, Boning $model): bool
    {
        return $user->hasPermission('delete_bonings');
    }

    public function restore(User $user, Boning $model): bool
    {
        return $user->hasPermission('delete_bonings');
    }

    public function forceDelete(User $user, Boning $model): bool
    {
        return $user->hasPermission('delete_bonings');
    }

    public function lock(User $user, Boning $model): bool
    {
        return $user->hasPermission('lock_bonings');
    }
}
