<?php

namespace App\Policies;

use App\Models\CattleClass;
use App\Models\User;

class CattleClassPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_cattle_classes'); }
    public function view(User $user, CattleClass $model): bool { return $user->hasPermission('view_cattle_classes'); }
    public function create(User $user): bool { return $user->hasPermission('create_cattle_classes'); }
    public function update(User $user, CattleClass $model): bool { return $user->hasPermission('edit_cattle_classes'); }
    public function delete(User $user, CattleClass $model): bool { return $user->hasPermission('delete_cattle_classes'); }
    public function restore(User $user, CattleClass $model): bool { return $user->hasPermission('delete_cattle_classes'); }
    public function forceDelete(User $user, CattleClass $model): bool { return $user->hasPermission('delete_cattle_classes'); }
}