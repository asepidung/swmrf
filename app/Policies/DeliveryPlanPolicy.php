<?php

namespace App\Policies;

use App\Models\DeliveryPlan;
use App\Models\User;

class DeliveryPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_delivery_plans');
    }

    public function view(User $user, DeliveryPlan $model): bool
    {
        return $user->hasPermission('view_delivery_plans');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_delivery_plans');
    }

    public function update(User $user, DeliveryPlan $model): bool
    {
        return $user->hasPermission('edit_delivery_plans');
    }

    public function delete(User $user, DeliveryPlan $model): bool
    {
        return $user->hasPermission('delete_delivery_plans');
    }

    public function restore(User $user, DeliveryPlan $model): bool
    {
        return $user->hasPermission('delete_delivery_plans');
    }

    public function forceDelete(User $user, DeliveryPlan $model): bool
    {
        return $user->hasPermission('delete_delivery_plans');
    }
}
