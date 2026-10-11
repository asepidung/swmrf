<?php

namespace App\Policies;

/**
 * Layar Nilai Barcode (HPP tahap 2, issue #522): satu izin untuk melihat dan
 * mengisi. Tidak ada tambah atau hapus -- baris lahir dari kejadian stok.
 */
class BarcodeCostPolicy
{
    public function viewAny($user): bool
    {
        return $user->hasPermission('manage_barcode_costs');
    }

    public function view($user, $model): bool
    {
        return $user->hasPermission('manage_barcode_costs');
    }

    public function create($user): bool
    {
        return false;
    }

    public function update($user, $model): bool
    {
        return $user->hasPermission('manage_barcode_costs');
    }

    public function delete($user, $model): bool
    {
        return false;
    }

    public function deleteAny($user): bool
    {
        return false;
    }

    public function restoreAny($user): bool
    {
        return false;
    }

    public function forceDeleteAny($user): bool
    {
        return false;
    }
}
