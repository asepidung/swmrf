<?php

namespace App\Filament\Admin\Widgets;

use App\Console\Commands\NotifyDuePayables;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

/**
 * Apakah pemeriksaan jatuh tempo benar-benar berjalan?
 *
 * Peringatan hutang jatuh tempo bergantung pada satu baris cron di hPanel
 * Hostinger. Kalau baris itu tidak pernah dipasang, terhapus, atau berhenti
 * karena apa pun, akibatnya TIDAK menghasilkan gejala sama sekali: tidak ada
 * error, tidak ada halaman rusak, hanya notifikasi yang tidak pernah datang
 * lagi. Tagihan lewat jatuh tempo baru ketahuan saat supplier menagih.
 *
 * Ini pelajaran yang sama dengan penghitung langganan notifikasi: kegagalan
 * yang tidak dihitung tidak akan pernah disadari. Karena itu waktu
 * pemeriksaan terakhir ditampilkan, bukan diasumsikan berjalan.
 *
 * Yang ditandai adalah PEMERIKSAANNYA, bukan pengirimannya. Hari tanpa
 * tagihan jatuh tempo memang tidak mengirim apa-apa, dan itu benar.
 */
class ScheduledReminderHealthWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.scheduled-reminder-health-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    /** Hanya ditampilkan kepada yang mengawasi sistem. */
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && ($user->isProgrammer() || $user->hasPermission('view_users'));
    }

    public function getLastRun(): ?Carbon
    {
        $stamp = Cache::get(NotifyDuePayables::LAST_RUN_CACHE_KEY);

        return $stamp ? Carbon::parse($stamp) : null;
    }

    /**
     * Sehat bila pemeriksaan terakhir belum lewat dua hari.
     *
     * Diberi kelonggaran satu hari dari jadwal hariannya, supaya keterlambatan
     * sesaat -- server sibuk, jadwal bergeser sedikit -- tidak langsung
     * dilaporkan sebagai kerusakan.
     */
    public function isHealthy(): bool
    {
        $lastRun = $this->getLastRun();

        return $lastRun !== null && $lastRun->greaterThan(now()->subDays(2));
    }

    /** @return array{overdue:int, today:int, soon:int, total:int} */
    public function getSummary(): array
    {
        return NotifyDuePayables::summary();
    }
}
