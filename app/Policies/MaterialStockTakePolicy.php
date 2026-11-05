<?php

namespace App\Policies;

use App\Models\MaterialStockTake;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MaterialStockTakePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny($user): bool
    {
        return $user->hasPermission('view_material_stock_takes');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view($user, $model): bool
    {
        return $user->hasPermission('view_material_stock_takes');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create($user): bool
    {
        return $user->hasPermission('create_material_stock_takes');
    }

    /**
     * Determine whether the user can update the model.
     */
    /**
     * Pemegang izin Ubah, atau peninjau (izin Selesaikan dan terapkan ke stok) selama dokumennya REVIEW. Tombol Tolak /
     * Hitung Ulang dan Complete ada di halaman Ubah; tanpa ini peninjau yang hanya punya View + Selesaikan tidak bisa
     * membukanya (ditemukan 5 November 2026).
     */
    public function update($user, $model): bool
    {
        if ($user->hasPermission('edit_material_stock_takes')) {
            return true;
        }

        return $model instanceof MaterialStockTake
            && $model->status === MaterialStockTake::STATUS_REVIEW
            && ($user->isProgrammer() || $user->hasPermission('finish_material_stock_takes'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete($user, $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_material_stock_takes');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore($user, $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete($user, $model): bool
    {
        return $user->isProgrammer();
    }

    public function deleteAny($user): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_material_stock_takes');
    }

    public function restoreAny($user): bool
    {
        return false;
    }

    public function forceDeleteAny($user): bool
    {
        return $user->isProgrammer();
    }
}
