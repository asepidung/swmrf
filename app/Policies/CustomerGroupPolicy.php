<?php

namespace App\Policies;

use App\Models\CustomerGroup;
use App\Models\User;

class CustomerGroupPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_customer_groups'); }
    public function view(User $user, CustomerGroup $model): bool { return $user->hasPermission('view_customer_groups'); }
    public function create(User $user): bool { return $user->hasPermission('create_customer_groups'); }
    public function update(User $user, CustomerGroup $model): bool { return $user->hasPermission('edit_customer_groups'); }
    public function delete(User $user, CustomerGroup $model): bool { return $user->hasPermission('delete_customer_groups'); }
    public function restore(User $user, CustomerGroup $model): bool { return $user->hasPermission('delete_customer_groups'); }
    public function forceDelete(User $user, CustomerGroup $model): bool { return $user->hasPermission('delete_customer_groups'); }
}