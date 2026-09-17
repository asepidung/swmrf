<?php

namespace App\Policies;

use App\Models\MaterialUsageHeader;
use App\Models\User;

/**
 * MaterialUsageHeader tidak punya tabel sendiri (dibaca dari VIEW), tapi
 * tetap ini yang diperiksa `Resource::canCreate()`/`canViewAny()` -- dan
 * sebelumnya tidak ada Policy sama sekali di sini. Tanpa Policy TERDAFTAR
 * dan tanpa `Gate::before`, `authorize()` jatuh ke Response::allow() --
 * siapa pun yang login bisa membuka "Create Manual Usage" dan membuat
 * penyesuaian stok sungguhan, terlepas dari izin apa pun yang dipegangnya.
 */
class MaterialUsageHeaderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_material_usages');
    }

    public function view(User $user, MaterialUsageHeader $model): bool
    {
        return $user->hasPermission('view_material_usages');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_material_usages');
    }

    public function update(User $user, MaterialUsageHeader $model): bool
    {
        return $user->hasPermission('edit_material_usages');
    }

    public function delete(User $user, MaterialUsageHeader $model): bool
    {
        return $user->hasPermission('delete_material_usages');
    }

    public function restore(User $user, MaterialUsageHeader $model): bool
    {
        return $user->hasPermission('delete_material_usages');
    }

    public function forceDelete(User $user, MaterialUsageHeader $model): bool
    {
        return $user->hasPermission('delete_material_usages');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_material_usages');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermission('delete_material_usages');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermission('delete_material_usages');
    }
}
