<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penanda "perintah terjadwal ini benar-benar berjalan", yang tahan rilis.
 *
 * Jangan pindahkan kembali ke Cache. Penanda ini pernah disimpan di sana
 * dengan Cache::forever, dan hilang setiap kali deploy menjalankan
 * `php artisan optimize:clear` -- sehingga Dashboard mengumumkan "belum
 * pernah berjalan" tiap habis rilis, walaupun cron-nya sehat.
 *
 * Bedanya halus tapi menentukan: cache boleh hilang karena isinya bisa
 * dihitung ulang. Angka ini TIDAK bisa dihitung ulang. Kalau ia hilang,
 * yang tersisa hanyalah ketidaktahuan -- dan ketidaktahuan di sini terbaca
 * sama persis dengan kerusakan.
 */
class ScheduledRun
{
    protected const TABLE = 'scheduled_run_marks';

    /** Catat bahwa perintah ini baru saja berjalan. */
    public static function stamp(string $key): void
    {
        static::stampAt($key, now());
    }

    /** Catat waktu tertentu. Dipakai pengujian untuk meniru cron yang tertinggal. */
    public static function stampAt(string $key, Carbon $waktu): void
    {
        DB::table(static::TABLE)->updateOrInsert(
            ['key' => $key],
            ['ran_at' => $waktu],
        );
    }

    /** Kapan terakhir ia berjalan, atau null kalau belum pernah sama sekali. */
    public static function lastRunAt(string $key): ?Carbon
    {
        $stamp = DB::table(static::TABLE)->where('key', $key)->value('ran_at');

        return $stamp ? Carbon::parse($stamp) : null;
    }

    /** Hapus jejaknya. Dipakai pengujian untuk meniru cron yang tidak pernah dipasang. */
    public static function forget(string $key): void
    {
        DB::table(static::TABLE)->where('key', $key)->delete();
    }
}
