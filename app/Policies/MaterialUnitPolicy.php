<?php

namespace App\Policies;

use App\Models\MaterialUnit;
use App\Models\User;

class MaterialUnitPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_materials'); }
    public function view(User $user, MaterialUnit $model): bool { return $user->hasPermission('view_materials'); }
    public function create(User $user): bool { return $user->hasPermission('create_materials'); }
    public function update(User $user, MaterialUnit $model): bool { return $user->hasPermission('edit_materials'); }
    public function delete(User $user, MaterialUnit $model): bool { return $user->hasPermission('delete_materials'); }
    public function restore(User $user, MaterialUnit $model): bool { return $user->hasPermission('delete_materials'); }
    public function forceDelete(User $user, MaterialUnit $model): bool { return $user->hasPermission('delete_materials'); }
}
