<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Batas akhir bawaan filter tanggal untuk dokumen yang bersifat PLAN (Sales Order, PO, Delivery Plan): tanggalnya
 * sering jatuh di masa depan, jadi "tanggal 1 sampai hari ini" menyembunyikan dokumen yang baru dibuat.
 *
 * Keputusan Owner 28 Oktober 2026 (pelatihan: marketing membuat SO tanggal 29 tiga kali dan tidak muncul): batas
 * akhirnya = yang paling akhir antara hari ini dan tanggal terjauh dokumen itu sendiri. Dokumen untuk tanggal 29
 * muncul seketika; kalau tidak ada dokumen mendatang, tetap "sampai hari ini" seperti modul lain.
 */
class PlanDateFilter
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    public static function until(string $model, string $column): Carbon
    {
        $today = now()->startOfDay();
        $max = $model::query()->max($column);

        if (! $max) {
            return $today;
        }

        $latest = Carbon::parse($max)->startOfDay();

        return $latest->gt($today) ? $latest : $today;
    }
}
