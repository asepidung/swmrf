<?php

namespace App\Policies;

use App\Models\Receivable;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReceivablePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny($user): bool
    {
        return $user->hasPermission('view_receivables');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view($user, $model): bool
    {
        return $user->hasPermission('view_receivables');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create($user): bool
    {
        return $user->hasPermission('create_receivables');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update($user, $model): bool
    {
        return $user->hasPermission('edit_receivables');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete($user, $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_receivables');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore($user, $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_receivables');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete($user, $model): bool
    {
        return $user->isProgrammer();
    }
}
