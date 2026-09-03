<?php

namespace App\Filament\Admin\Widgets;

use App\Console\Commands\NotifyDuePayables;
use App\Console\Commands\NotifyUnlockedGoodsReceipts;
use App\Support\ScheduledRun;
use Carbon\Carbon;
use Filament\Widgets\Widget;

/**
 * Apakah pemeriksaan terjadwal benar-benar berjalan?
 *
 * Peringatan hutang jatuh tempo dan Goods Receipt menggantung bergantung pada
 * satu baris cron di hPanel Hostinger. Kalau baris itu tidak pernah dipasang,
 * terhapus, atau berhenti karena apa pun, akibatnya TIDAK menghasilkan gejala
 * sama sekali: tidak ada error, tidak ada halaman rusak, hanya notifikasi yang
 * tidak pernah datang lagi. Tagihan lewat jatuh tempo baru ketahuan saat
 * pemasok menagih.
 *
 * Ini pelajaran yang sama dengan penghitung langganan notifikasi: kegagalan
 * yang tidak dihitung tidak akan pernah disadari.
 *
 * MUNCUL HANYA SAAT ADA YANG SALAH. Sebelumnya widget ini memakai satu baris
 * penuh setiap hari untuk mengatakan "semuanya normal", dan pengumuman yang
 * selalu sama akan berhenti dibaca -- termasuk pada hari isinya berubah.
 * Dashboard yang bersih di sini berarti pemeriksaannya sehat.
 *
 * Yang ditandai adalah PEMERIKSAANNYA, bukan pengirimannya. Hari tanpa
 * tagihan jatuh tempo memang tidak mengirim apa-apa, dan itu benar.
 *
 * Hitungan "berapa yang perlu ditangani" sengaja TIDAK ada di sini. Itu sudah
 * menjadi tugas PendingTaskWidget, dan menampilkan angka yang sama dua kali
 * di satu halaman hanya membuat orang bertanya-tanya mana yang benar.
 */
class ScheduledReminderHealthWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.scheduled-reminder-health-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    /**
     * Hanya kepada yang mengawasi sistem, dan hanya saat ada yang salah.
     */
    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user || ! ($user->isProgrammer() || $user->hasPermission('view_users'))) {
            return false;
        }

        return ! static::isHealthy();
    }

    /**
     * Semua peringatan terjadwal yang kesehatannya diawasi di sini.
     *
     * Keduanya menumpang satu baris cron yang sama, tetapi masing-masing
     * menandai waktu jalannya sendiri. Kalau salah satunya melempar error
     * tiap hari sementara yang lain baik-baik saja, penanda "cron hidup"
     * tidak akan memperlihatkannya.
     *
     * @return array<int, string>
     */
    protected static function watchedCommands(): array
    {
        return [
            NotifyDuePayables::LAST_RUN_KEY,
            NotifyUnlockedGoodsReceipts::LAST_RUN_KEY,
        ];
    }

    /**
     * Waktu pemeriksaan terakhir, diambil yang PALING TERTINGGAL.
     *
     * Kalau satu perintah berjalan hari ini dan satunya sudah seminggu
     * berhenti, yang perlu terlihat adalah yang seminggu itu. Satu saja yang
     * belum pernah berjalan sudah cukup untuk menyatakan pemeriksaannya
     * tidak utuh.
     */
    public static function getLastRun(): ?Carbon
    {
        $waktu = [];

        foreach (static::watchedCommands() as $key) {
            $stamp = ScheduledRun::lastRunAt($key);

            if (! $stamp) {
                return null;
            }

            $waktu[] = $stamp;
        }

        return $waktu === [] ? null : min($waktu);
    }

    /**
     * Sehat bila pemeriksaan terakhir belum lewat dua hari.
     *
     * Diberi kelonggaran satu hari dari jadwal hariannya, supaya keterlambatan
     * sesaat -- server sibuk, jadwal bergeser sedikit -- tidak langsung
     * dilaporkan sebagai kerusakan.
     */
    public static function isHealthy(): bool
    {
        $lastRun = static::getLastRun();

        return $lastRun !== null && $lastRun->greaterThan(now()->subDays(2));
    }
}
