<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_sales_orders');
    }

    public function view(User $user, SalesOrder $model): bool
    {
        return $user->hasPermission('view_sales_orders');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_sales_orders');
    }

    public function update(User $user, SalesOrder $model): bool
    {
        return $user->hasPermission('edit_sales_orders');
    }

    public function delete(User $user, SalesOrder $model): bool
    {
        return $user->hasPermission('delete_sales_orders') && in_array($model->status, [SalesOrder::STATUS_WAITING, SalesOrder::STATUS_CANCELLED], true);
    }

    public function restore(User $user, SalesOrder $model): bool
    {
        return $user->hasPermission('delete_sales_orders') && in_array($model->status, [SalesOrder::STATUS_WAITING, SalesOrder::STATUS_CANCELLED], true);
    }

    public function forceDelete(User $user, SalesOrder $model): bool
    {
        return $user->hasPermission('delete_sales_orders') && in_array($model->status, [SalesOrder::STATUS_WAITING, SalesOrder::STATUS_CANCELLED], true);
    }
}
