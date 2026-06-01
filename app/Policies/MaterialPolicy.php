<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;

class MaterialPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_materials'); }
    public function view(User $user, Material $model): bool { return $user->hasPermission('view_materials'); }
    public function create(User $user): bool { return $user->hasPermission('create_materials'); }
    public function update(User $user, Material $model): bool { return $user->hasPermission('edit_materials'); }
    public function delete(User $user, Material $model): bool { return $user->hasPermission('delete_materials'); }
    public function restore(User $user, Material $model): bool { return $user->hasPermission('delete_materials'); }
    public function forceDelete(User $user, Material $model): bool { return $user->hasPermission('delete_materials'); }
}