<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_grades'); }
    public function view(User $user, Grade $model): bool { return $user->hasPermission('view_grades'); }
    public function create(User $user): bool { return $user->hasPermission('create_grades'); }
    public function update(User $user, Grade $model): bool { return $user->hasPermission('edit_grades'); }
    public function delete(User $user, Grade $model): bool { return $user->hasPermission('delete_grades'); }
    public function restore(User $user, Grade $model): bool { return $user->hasPermission('delete_grades'); }
    public function forceDelete(User $user, Grade $model): bool { return $user->hasPermission('delete_grades'); }
}
