<?php

namespace App\Policies;

class SalesReturnPlanPolicy
{
    public function viewAny($user): bool
    {
        return $user->hasPermission('view_sales_return_plans');
    }

    public function view($user, $model): bool
    {
        return $user->hasPermission('view_sales_return_plans');
    }

    public function create($user): bool
    {
        return $user->hasPermission('create_sales_return_plans');
    }

    public function update($user, $model): bool
    {
        return $user->hasPermission('edit_sales_return_plans');
    }

    public function delete($user, $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_sales_return_plans');
    }

    public function restore($user, $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_sales_return_plans');
    }

    public function forceDelete($user, $model): bool
    {
        return $user->isProgrammer();
    }

    public function deleteAny($user): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_sales_return_plans');
    }

    public function restoreAny($user): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_sales_return_plans');
    }

    public function forceDeleteAny($user): bool
    {
        return $user->isProgrammer();
    }
}
