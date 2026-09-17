<?php

namespace App\Policies;

use App\Models\Vehicle;
use App\Models\User;

class VehiclePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_vehicles'); }
    public function view(User $user, Vehicle $model): bool { return $user->hasPermission('view_vehicles'); }
    public function create(User $user): bool { return $user->hasPermission('create_vehicles'); }
    public function update(User $user, Vehicle $model): bool { return $user->hasPermission('edit_vehicles'); }
    public function delete(User $user, Vehicle $model): bool { return $user->hasPermission('delete_vehicles'); }
    public function restore(User $user, Vehicle $model): bool { return $user->hasPermission('delete_vehicles'); }
    public function forceDelete(User $user, Vehicle $model): bool { return $user->hasPermission('delete_vehicles'); }
    public function deleteAny(User $user): bool { return $user->hasPermission('delete_vehicles'); }
    public function restoreAny(User $user): bool { return $user->hasPermission('delete_vehicles'); }
    public function forceDeleteAny(User $user): bool { return $user->hasPermission('delete_vehicles'); }
}
