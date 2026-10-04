<?php

namespace App\Policies;

use App\Models\MaterialFinding;
use App\Models\User;

/**
 * Policy untuk Temuan Material.
 *
 * Modul ini menambah stok bahan dari isian orang, tanpa dokumen asal. Tanpa
 * Policy, Laravel mengizinkan apa saja pada modelnya (fail-open), sehingga
 * pemeriksaan `canViewAny()` di Resource-nya satu-satunya penjaga -- dan
 * `authorize()` di jalur lain lolos begitu saja.
 *
 * Semua aksi yang sah memakai satu izin, `record_material_findings`.
 * Temuan tidak boleh disunting (stoknya sudah terlanjur bergerak), dan tidak
 * ada pemulihan: menghapusnya sudah menarik stok kembali, jadi memulihkan
 * barisnya akan membuat stok dan dokumen berbeda.
 */
class MaterialFindingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('record_material_findings');
    }

    public function view(User $user, MaterialFinding $model): bool
    {
        return $user->hasPermission('record_material_findings');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('record_material_findings');
    }

    public function update(User $user, MaterialFinding $model): bool
    {
        return false;
    }

    public function delete(User $user, MaterialFinding $model): bool
    {
        return $user->hasPermission('record_material_findings');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('record_material_findings');
    }

    public function restore(User $user, MaterialFinding $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, MaterialFinding $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
