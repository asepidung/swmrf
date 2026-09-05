<?php

namespace App\Policies;

use App\Models\BeefStock;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BeefStockAgingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny($user): bool
    {
        return $user->hasPermission('view_beef_stock_aging');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view($user, $model): bool
    {
        return $user->hasPermission('view_beef_stock_aging');
    }

    /**
     * Laporan ini TIDAK BISA diubah oleh siapa pun, dan itu memang disengaja.
     *
     * Sebelumnya di sini disebut izin `create_beef_stock_aging`. Izin itu tidak pernah
     * ada -- tidak di seeder, tidak di migrasi mana pun -- sehingga
     * `hasPermission()` atasnya selalu `false`. Hasilnya sama, tetapi
     * kodenya terbaca seolah ada hak yang bisa diberikan, padahal tidak ada
     * yang bisa mencentangnya.
     */
    public function create($user): bool
    {
        return false;
    }

    /** Lihat catatan di `create()`. Dulu menyebut `edit_beef_stock_aging`. */
    public function update($user, $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete($user, $model): bool
    {
        return $user->isProgrammer();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore($user, $model): bool
    {
        return $user->isProgrammer();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete($user, $model): bool
    {
        return $user->isProgrammer();
    }
}
