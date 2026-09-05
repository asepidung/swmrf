<?php

namespace App\Policies;

use App\Models\BeefStock;
use App\Models\User;

class BeefStockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_beef_stocks');
    }

    public function view(User $user, BeefStock $model): bool
    {
        return $user->hasPermission('view_beef_stocks');
    }

    /**
     * Stok TIDAK dibuat dan TIDAK disunting lewat layar.
     *
     * Barisnya lahir dari dokumen -- penerimaan, boning, repack, retur,
     * temuan -- dan berubah lewat dokumen pula. Dulu di sini disebut
     * `create_beef_stocks` dan `edit_beef_stocks`; keduanya tidak pernah ada,
     * jadi jawabannya memang selalu `false`. Sekarang tertulis apa adanya.
     *
     * `delete()` di bawah lain ceritanya: hapus stok memang dibutuhkan, untuk
     * barang yang tercatat tetapi fisiknya tidak ada. Izinnya kini sungguhan.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, BeefStock $model): bool
    {
        return false;
    }

    public function delete(User $user, BeefStock $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('delete_beef_stocks');
    }
}
