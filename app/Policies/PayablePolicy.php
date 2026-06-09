<?php

namespace App\Policies;

use App\Models\Payable;
use App\Models\User;

class PayablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_payables');
    }

    public function view(User $user, Payable $model): bool
    {
        return $user->hasPermission('view_payables');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payable $model): bool
    {
        return false;
    }

    public function delete(User $user, Payable $model): bool
    {
        return false;
    }

    public function restore(User $user, Payable $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payable $model): bool
    {
        return false;
    }
}
