<?php

namespace App\Policies;

use App\Models\CattleWeighing;
use App\Models\User;

class CattleWeighingPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_cattle_weighings'); }
    public function view(User $user, CattleWeighing $model): bool { return $user->hasPermission('view_cattle_weighings'); }
    public function create(User $user): bool { return $user->hasPermission('create_cattle_weighings'); }
    public function update(User $user, CattleWeighing $model): bool { return $user->hasPermission('edit_cattle_weighings'); }
    public function delete(User $user, CattleWeighing $model): bool { return $user->hasPermission('delete_cattle_weighings'); }
    public function restore(User $user, CattleWeighing $model): bool { return $user->hasPermission('delete_cattle_weighings'); }
    public function forceDelete(User $user, CattleWeighing $model): bool { return $user->hasPermission('delete_cattle_weighings'); }
}