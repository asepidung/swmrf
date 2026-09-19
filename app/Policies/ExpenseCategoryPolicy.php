<?php

namespace App\Policies;

/**
 * Master kecil, satu izin tunggal untuk semuanya -- sama seperti pola
 * "manage_..." untuk master data ringan lain, bukan empat izin
 * view/create/edit/delete terpisah (issue #453 eksplisit meminta ini).
 */
class ExpenseCategoryPolicy
{
    public function viewAny($user): bool
    {
        return $user->hasPermission('manage_expense_categories');
    }

    public function view($user, $model): bool
    {
        return $user->hasPermission('manage_expense_categories');
    }

    public function create($user): bool
    {
        return $user->hasPermission('manage_expense_categories');
    }

    public function update($user, $model): bool
    {
        return $user->hasPermission('manage_expense_categories');
    }

    public function delete($user, $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('manage_expense_categories');
    }

    public function restore($user, $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('manage_expense_categories');
    }

    public function forceDelete($user, $model): bool
    {
        return $user->isProgrammer();
    }

    public function deleteAny($user): bool
    {
        return $user->isProgrammer() || $user->hasPermission('manage_expense_categories');
    }

    public function restoreAny($user): bool
    {
        return $user->isProgrammer() || $user->hasPermission('manage_expense_categories');
    }

    public function forceDeleteAny($user): bool
    {
        return $user->isProgrammer();
    }
}
