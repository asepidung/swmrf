<?php

namespace App\Policies;

use App\Models\Repack;
use App\Models\User;

class RepackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_repacks');
    }

    public function view(User $user, Repack $model): bool
    {
        return $user->hasPermission('view_repacks');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_repacks');
    }

    public function update(User $user, Repack $model): bool
    {
        return $user->hasPermission('edit_repacks');
    }

    public function delete(User $user, Repack $model): bool
    {
        return $user->hasPermission('delete_repacks');
    }

    public function restore(User $user, Repack $model): bool
    {
        return $user->hasPermission('delete_repacks');
    }

    public function forceDelete(User $user, Repack $model): bool
    {
        return $user->hasPermission('delete_repacks');
    }

    public function lock(User $user, Repack $model): bool
    {
        return $user->hasPermission('lock_repacks');
    }
}
