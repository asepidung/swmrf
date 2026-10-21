<?php

namespace App\Policies;

use App\Models\CustomerProductSpec;
use App\Models\User;

/**
 * Spek customer per produk tidak punya izin sendiri (#579, keputusan Owner):
 * siapa pun yang boleh membuat atau mengubah Sales Order boleh mengisi dan
 * mengubah spek, dan siapa pun yang boleh melihat Sales Order atau Tally
 * boleh melihatnya.
 */
class CustomerProductSpecPolicy
{
    private function canSee(User $user): bool
    {
        return $user->hasPermission('view_sales_orders') || $user->hasPermission('view_tallies');
    }

    private function canWrite(User $user): bool
    {
        return $user->hasPermission('create_sales_orders') || $user->hasPermission('edit_sales_orders');
    }

    public function viewAny(User $user): bool { return $this->canSee($user); }
    public function view(User $user, CustomerProductSpec $model): bool { return $this->canSee($user); }
    public function create(User $user): bool { return $this->canWrite($user); }
    public function update(User $user, CustomerProductSpec $model): bool { return $this->canWrite($user); }
    public function delete(User $user, CustomerProductSpec $model): bool { return $this->canWrite($user); }
    public function restore(User $user, CustomerProductSpec $model): bool { return $this->canWrite($user); }
    public function forceDelete(User $user, CustomerProductSpec $model): bool { return $this->canWrite($user); }
    public function deleteAny(User $user): bool { return $this->canWrite($user); }
    public function restoreAny(User $user): bool { return $this->canWrite($user); }
    public function forceDeleteAny(User $user): bool { return $this->canWrite($user); }
}
