<?php

namespace App\Policies;

use Spatie\Activitylog\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_activity_logs'); }
    public function view(User $user, Activity $model): bool { return $user->hasPermission('view_activity_logs'); }
    public function create(User $user): bool { return false; }
    public function update(User $user, Activity $model): bool { return false; }
    public function delete(User $user, Activity $model): bool { return false; }
    public function restore(User $user, Activity $model): bool { return false; }
    public function forceDelete(User $user, Activity $model): bool { return false; }
}
