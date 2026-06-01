<?php

namespace App\Policies;

use App\Models\CattleReceiving;
use App\Models\User;

class CattleReceivingPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_cattle_receivings'); }
    public function view(User $user, CattleReceiving $model): bool { return $user->hasPermission('view_cattle_receivings'); }
    public function create(User $user): bool { return $user->hasPermission('create_cattle_receivings'); }
    public function update(User $user, CattleReceiving $model): bool { return $user->hasPermission('edit_cattle_receivings'); }
    public function delete(User $user, CattleReceiving $model): bool { return $user->hasPermission('delete_cattle_receivings'); }
    public function restore(User $user, CattleReceiving $model): bool { return $user->hasPermission('delete_cattle_receivings'); }
    public function forceDelete(User $user, CattleReceiving $model): bool { return $user->hasPermission('delete_cattle_receivings'); }
}