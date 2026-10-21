<?php

namespace App\Policies;

use App\Models\CustomerProductSpec;
use App\Models\User;

/**
 * Spek customer per produk punya izin sendiri (keputusan Owner, 21 Oktober
 * 2026, setelah menunya pindah ke grup QC): `view_customer_specs` untuk
 * melihat dan `manage_customer_specs` untuk mengisi, mengubah, dan menghapus.
 * Berlaku juga untuk tombol "Simpan sebagai spek tetap" di Sales Order.
 */
class CustomerProductSpecPolicy
{
    private function canSee(User $user): bool
    {
        return $user->hasPermission('view_customer_specs') || $user->hasPermission('manage_customer_specs');
    }

    private function canWrite(User $user): bool
    {
        return $user->hasPermission('manage_customer_specs');
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
