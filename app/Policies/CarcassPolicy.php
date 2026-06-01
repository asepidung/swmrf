<?php

namespace App\Policies;

use App\Models\Carcass;
use App\Models\User;

class CarcassPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_carcasses'); }
    public function view(User $user, Carcass $model): bool { return $user->hasPermission('view_carcasses'); }
    public function create(User $user): bool { return $user->hasPermission('create_carcasses'); }
    public function update(User $user, Carcass $model): bool { return $user->hasPermission('edit_carcasses'); }
    public function delete(User $user, Carcass $model): bool { return $user->hasPermission('delete_carcasses'); }
    public function restore(User $user, Carcass $model): bool { return $user->hasPermission('delete_carcasses'); }
    public function forceDelete(User $user, Carcass $model): bool { return $user->hasPermission('delete_carcasses'); }
}
