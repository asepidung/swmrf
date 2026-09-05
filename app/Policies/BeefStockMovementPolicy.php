<?php

namespace App\Policies;

use App\Models\BeefStockMovement;
use App\Models\User;

class BeefStockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_beef_stock_movements');
    }

    public function view(User $user, BeefStockMovement $model): bool
    {
        return $user->hasPermission('view_beef_stock_movements');
    }

    /**
     * Laporan ini TIDAK BISA diubah oleh siapa pun, dan itu memang disengaja.
     *
     * Sebelumnya di sini disebut izin `create/edit/delete_beef_stock_movements`. Izin itu tidak pernah
     * ada -- tidak di seeder, tidak di migrasi mana pun -- sehingga
     * `hasPermission()` atasnya selalu `false`. Hasilnya sama, tetapi
     * kodenya terbaca seolah ada hak yang bisa diberikan, padahal tidak ada
     * yang bisa mencentangnya.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, BeefStockMovement $model): bool
    {
        return false;
    }

    public function delete(User $user, BeefStockMovement $model): bool
    {
        return false;
    }
}
