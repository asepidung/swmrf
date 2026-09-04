<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isProgrammer() || $user->hasPermission('view_users');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('view_users');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isProgrammer() || $user->hasPermission('create_users');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isProgrammer() || $user->hasPermission('edit_users');
    }

    /**
     * Pengguna TIDAK BISA DIHAPUS. Keputusan Project Owner, 5 September 2026:
     * "user mah jangan ada hapus aktif non aktif aja".
     *
     * Alasannya kuat dan bukan sekadar kehati-hatian. Ada 37 kunci asing yang
     * menunjuk ke tabel ini, dan tiga di antaranya dulu memakai CASCADE --
     * menghapus satu pengguna akan ikut menghapus permintaan bahan dan
     * permintaan produk yang pernah ia buat. Lima belas lainnya memakai
     * `nullOnDelete()`, yang diam-diam menghapus jejak "siapa yang
     * mengerjakan" dari dokumen yang tetap ada.
     *
     * Pengguna juga tidak memakai hapus lunak, jadi tidak ada yang bisa
     * dipulihkan.
     *
     * Yang menggantikannya sudah ada sejak awal: kolom `is_active`. Pengguna
     * yang berhenti bekerja dinonaktifkan -- ia tidak bisa masuk lagi
     * (`canAccessPanel()`), sementara seluruh jejaknya di dokumen lama tetap
     * utuh dan tetap bisa dibaca.
     *
     * Izin `delete_users` sengaja TIDAK dihapus dari basis data; ia masih
     * dipakai modul lain sebagai contoh penamaan, dan mencabutnya di sini
     * tidak menambah apa pun.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
