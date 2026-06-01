<?php

namespace App\Policies;

use App\Models\CustomerSegment;
use App\Models\User;

class CustomerSegmentPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_customer_segments'); }
    public function view(User $user, CustomerSegment $model): bool { return $user->hasPermission('view_customer_segments'); }
    public function create(User $user): bool { return $user->hasPermission('create_customer_segments'); }
    public function update(User $user, CustomerSegment $model): bool { return $user->hasPermission('edit_customer_segments'); }
    public function delete(User $user, CustomerSegment $model): bool { return $user->hasPermission('delete_customer_segments'); }
    public function restore(User $user, CustomerSegment $model): bool { return $user->hasPermission('delete_customer_segments'); }
    public function forceDelete(User $user, CustomerSegment $model): bool { return $user->hasPermission('delete_customer_segments'); }
}