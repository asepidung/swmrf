<?php

namespace App\Policies;

class ExpensePolicy
{
    public function viewAny($user): bool
    {
        return $user->hasPermission('view_expenses');
    }

    public function view($user, $model): bool
    {
        return $user->hasPermission('view_expenses');
    }

    public function create($user): bool
    {
        return $user->hasPermission('create_expenses');
    }

    public function update($user, $model): bool
    {
        return $user->hasPermission('edit_expenses');
    }

    public function delete($user, $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_expenses');
    }

    public function restore($user, $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_expenses');
    }

    public function forceDelete($user, $model): bool
    {
        return $user->isProgrammer();
    }

    public function deleteAny($user): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_expenses');
    }

    public function restoreAny($user): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_expenses');
    }

    public function forceDeleteAny($user): bool
    {
        return $user->isProgrammer();
    }
}
