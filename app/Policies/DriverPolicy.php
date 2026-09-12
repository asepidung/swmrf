<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_drivers'); }
    public function view(User $user, Driver $model): bool { return $user->hasPermission('view_drivers'); }
    public function create(User $user): bool { return $user->hasPermission('create_drivers'); }
    public function update(User $user, Driver $model): bool { return $user->hasPermission('edit_drivers'); }
    public function delete(User $user, Driver $model): bool { return $user->hasPermission('delete_drivers'); }
    public function restore(User $user, Driver $model): bool { return $user->hasPermission('delete_drivers'); }
    public function forceDelete(User $user, Driver $model): bool { return $user->hasPermission('delete_drivers'); }
}
