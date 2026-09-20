<?php

namespace App\Policies;

class CostingPolicy
{
    public function viewAny($user): bool
    {
        return $user->hasPermission('view_costings');
    }

    public function view($user, $model): bool
    {
        return $user->hasPermission('view_costings');
    }

    public function create($user): bool
    {
        return $user->hasPermission('create_costings');
    }

    public function update($user, $model): bool
    {
        return $user->hasPermission('edit_costings');
    }

    public function delete($user, $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_costings');
    }

    public function restore($user, $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_costings');
    }

    public function forceDelete($user, $model): bool
    {
        return $user->isProgrammer();
    }

    public function deleteAny($user): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_costings');
    }

    public function restoreAny($user): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_costings');
    }

    public function forceDeleteAny($user): bool
    {
        return $user->isProgrammer();
    }
}
