<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Akun programmer (mis. saepullrock) TIDAK terlihat oleh siapa pun di dalam aplikasi, kecuali pemiliknya sendiri
 * (keputusan Owner, 19 Oktober 2026: keuangan baru boleh masuk sistem bila akun ini tertutup, bahkan bagi IT).
 *
 * Satu aturan pada model User, jadi berlaku di mana pun pengguna dibaca lewat aplikasi: daftar pengguna, pilihan
 * pengguna di formulir, filter, dan relasi pembuat/pengunci/penyetuju (nama tampil kosong atau "-").
 *
 * - Tanpa pengguna yang sedang masuk (login, perintah artisan, antrean) aturan ini TIDAK berlaku. Memakai
 *   `Auth::hasUser()` yang tidak memicu pembacaan pengguna lagi (menghindari rekursi saat sesi dibaca).
 * - Yang memang harus melihat programmer (mis. mengirim notifikasi ke pemegang izin, termasuk programmer) memakai
 *   `User::withoutGlobalScope(HideProgrammerAccounts::class)`.
 * - Ini penutup di LAPISAN APLIKASI. Siapa pun yang punya akses langsung ke server atau basis data tetap bisa
 *   membaca tabelnya.
 */
class HideProgrammerAccounts implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::hasUser()) {
            return;
        }

        $viewerId = Auth::id();

        $builder->where(function (Builder $query) use ($model, $viewerId): void {
            $query->whereNull($model->qualifyColumn('role'))
                ->orWhere($model->qualifyColumn('role'), '!=', 'programmer')
                ->orWhere($model->getQualifiedKeyName(), $viewerId);
        });
    }

    /**
     * Id akun programmer yang TIDAK boleh dilihat penonton saat ini (semua programmer kecuali dirinya sendiri).
     * Dipakai untuk menyaring tabel yang hanya menyimpan id pengguna (mis. riwayat aktivitas).
     *
     * @return array<int, int>
     */
    public static function hiddenIds(): array
    {
        return \App\Models\User::withoutGlobalScope(self::class)
            ->where('role', 'programmer')
            ->when(Auth::hasUser(), fn (Builder $q) => $q->whereKeyNot(Auth::id()))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
