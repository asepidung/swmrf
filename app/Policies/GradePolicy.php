<?php

namespace App\Policies;

use App\Models\User;

class GradePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, $model): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, $model): bool { return true; }
    public function delete(User $user, $model): bool { return true; }
}
