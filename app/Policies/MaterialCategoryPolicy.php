<?php

namespace App\Policies;

use App\Models\MaterialCategory;
use App\Models\User;

class MaterialCategoryPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_materials'); }
    public function view(User $user, MaterialCategory $model): bool { return $user->hasPermission('view_materials'); }
    public function create(User $user): bool { return $user->hasPermission('create_materials'); }
    public function update(User $user, MaterialCategory $model): bool { return $user->hasPermission('edit_materials'); }
    public function delete(User $user, MaterialCategory $model): bool { return $user->hasPermission('delete_materials'); }
    public function restore(User $user, MaterialCategory $model): bool { return $user->hasPermission('delete_materials'); }
    public function forceDelete(User $user, MaterialCategory $model): bool { return $user->hasPermission('delete_materials'); }
}
