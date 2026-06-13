<?php

namespace App\Policies;

use App\Models\Tally;
use App\Models\User;

class TallyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_tallies');
    }

    public function view(User $user, Tally $model): bool
    {
        return $user->hasPermission('view_tallies');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_tallies');
    }

    public function update(User $user, Tally $model): bool
    {
        return $user->hasPermission('edit_tallies');
    }

    public function delete(User $user, Tally $model): bool
    {
        return $user->hasPermission('delete_tallies');
    }

    public function restore(User $user, Tally $model): bool
    {
        return $user->hasPermission('delete_tallies');
    }

    public function forceDelete(User $user, Tally $model): bool
    {
        return $user->hasPermission('delete_tallies');
    }
}
