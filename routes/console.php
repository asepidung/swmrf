<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Peringatan hutang jatuh tempo, sekali setiap pagi.
 *
 * Membutuhkan SATU baris cron di hPanel Hostinger yang menjalankan
 * `php artisan schedule:run` tiap menit; `crontab` tidak tersedia dari SSH
 * di shared hosting ini. Setelah baris itu terpasang, seluruh penjadwalan
 * diatur dari berkas ini dan panel tidak perlu disentuh lagi.
 *
 * `withoutOverlapping()` menjaga bila suatu saat perintahnya menjadi lambat:
 * dua salinan yang berjalan bersamaan akan mengirim ringkasan ganda.
 */
Schedule::command('payables:notify-due')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();
