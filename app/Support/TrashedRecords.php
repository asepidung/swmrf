<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Siapa yang boleh melihat dokumen yang sudah dihapus.
 *
 * Sepuluh Resource dulu menulis ini sendiri-sendiri, dan semuanya menulisnya
 * dengan cara yang sama salahnya:
 *
 *     return parent::getEloquentQuery()
 *         ->withoutGlobalScopes([SoftDeletingScope::class]);
 *
 * Baris terhapus dibawa masuk untuk SEMUA ORANG, lalu yang diandalkan
 * menyaringnya kembali adalah `TrashedFilter` yang dibungkus
 * `->visible(fn () => auth()->user()->hasPermission('view_deleted_...'))`.
 *
 * **Filter yang tidak terlihat tidak menyaring apa pun.** Filament membuang
 * filter yang `isVisible()`-nya false sebelum query dijalankan, jadi izin itu
 * hanya menyembunyikan TOMBOLNYA -- bukan datanya. Pengguna tanpa hak melihat
 * dokumen terhapus bercampur dengan yang hidup, tanpa penanda apa pun, dan
 * tanpa cara membedakannya.
 *
 * Ini bentuk kegagalan yang paling sulit disadari: izinnya ada, namanya benar,
 * dan pemasangannya kelihatan masuk akal. Yang tidak kelihatan adalah bahwa
 * penjagaannya dipasang di lapisan yang salah -- persis pola yang sama dengan
 * temuan-temuan lain di proyek ini.
 *
 * Sekarang IZIN ITULAH batasnya. Baris terhapus hanya ikut terbawa kalau
 * penggunanya memang berhak melihatnya; `TrashedFilter` kembali menjadi apa
 * yang seharusnya, yaitu kemudahan tampilan, bukan pengaman.
 *
 * Programmer lolos dengan sendirinya lewat `User::hasPermission()`.
 */
class TrashedRecords
{
    public static function visibleTo(Builder $query, string $permission): Builder
    {
        if (auth()->user()?->hasPermission($permission)) {
            return $query->withoutGlobalScopes([SoftDeletingScope::class]);
        }

        // Tanpa pengguna sama sekali -- perintah artisan, pekerjaan terjadwal --
        // yang paling aman adalah perlakuan paling ketat.
        return $query;
    }
}
