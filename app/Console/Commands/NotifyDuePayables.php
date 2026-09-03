<?php

namespace App\Console\Commands;

use App\Filament\Admin\Resources\PayableResource;
use App\Models\Payable;
use App\Support\ScheduledRun;
use App\Support\TaskNotifier;
use Illuminate\Console\Command;

/**
 * Mengingatkan hutang yang mendekati atau melewati jatuh tempo.
 *
 * SATU RINGKASAN PER HARI, bukan satu notifikasi per dokumen. Hari dengan
 * sepuluh tagihan akan mengirim sepuluh notifikasi, dan orang berhenti
 * membacanya justru pada hari yang paling perlu dibaca.
 *
 * Kalau tidak ada satu pun yang mendekati atau lewat jatuh tempo, perintah
 * ini TIDAK mengirim apa-apa. Notifikasi "hari ini aman" setiap pagi akan
 * mengubah lonceng menjadi kebisingan yang diabaikan.
 *
 * Penerimanya pemegang `pay_payables` -- orang yang benar-benar bisa
 * membayar, bukan semua yang bisa melihat daftar hutang.
 */
class NotifyDuePayables extends Command
{
    /** Berapa hari sebelum jatuh tempo mulai diingatkan. */
    public const REMINDER_DAYS = 3;

    /**
     * Jejak terakhir perintah ini berjalan.
     *
     * Dibaca Dashboard. Tanpa penanda ini, cron yang mati atau lupa dipasang
     * TIDAK menghasilkan gejala apa pun: tidak ada error, tidak ada notifikasi,
     * dan tidak ada yang menyadari bahwa peringatan jatuh tempo sudah lama
     * berhenti terkirim.
     *
     * Disimpan di basis data, BUKAN di cache. Deploy kami menjalankan
     * `optimize:clear`, dan penanda yang tersimpan di cache ikut terhapus
     * setiap rilis -- membuat Dashboard mengumumkan kerusakan yang tidak
     * pernah terjadi. Lihat App\Support\ScheduledRun.
     */
    public const LAST_RUN_KEY = 'payables.due_reminder.last_run';

    protected $signature = 'payables:notify-due';

    protected $description = 'Kirim satu ringkasan harian tentang hutang yang mendekati atau melewati jatuh tempo';

    public function handle(): int
    {
        $summary = static::summary();

        // Dicatat lebih dulu, apa pun hasilnya. Yang perlu diketahui Dashboard
        // adalah "pemeriksaannya berjalan", bukan "ada yang dikirim" -- hari
        // tanpa tagihan jatuh tempo memang tidak mengirim apa-apa.
        ScheduledRun::stamp(static::LAST_RUN_KEY);

        if ($summary['total'] === 0) {
            $this->info('Tidak ada hutang yang mendekati atau melewati jatuh tempo.');

            return self::SUCCESS;
        }

        $sent = TaskNotifier::notifyPermissionHolders(
            permissions: 'pay_payables',
            title: __('Payables due'),
            body: static::describe($summary),
            url: PayableResource::getUrl('index'),
            // Tag yang sama setiap hari: notifikasi hari ini menggantikan
            // yang kemarin di layar, alih-alih menumpuk jadi antrean lama.
            tag: 'payables-due',
        );

        $this->info("Ringkasan terkirim ke {$sent} penerima: ".static::describe($summary));

        return self::SUCCESS;
    }

    /**
     * Hitungan hutang menurut kedekatan jatuh temponya.
     *
     * Hutang yang sudah lunas jelas tidak dihitung. Yang belum lunas tapi
     * sudah dicicil TETAP dihitung -- sisanya masih harus dibayar, dan
     * justru itu yang gampang terlupakan.
     *
     * @return array{overdue:int, today:int, soon:int, total:int}
     */
    public static function summary(): array
    {
        $open = Payable::query()->where('status', '!=', 'paid');

        $overdue = (clone $open)->whereDate('due_date', '<', today())->count();
        $today = (clone $open)->whereDate('due_date', today())->count();
        $soon = (clone $open)
            ->whereDate('due_date', '>', today())
            ->whereDate('due_date', '<=', today()->addDays(static::REMINDER_DAYS))
            ->count();

        return [
            'overdue' => $overdue,
            'today' => $today,
            'soon' => $soon,
            'total' => $overdue + $today + $soon,
        ];
    }

    /** Ringkasan dalam satu kalimat, hanya menyebut yang benar-benar ada. */
    public static function describe(array $summary): string
    {
        $parts = [];

        if ($summary['overdue'] > 0) {
            $parts[] = __(':count overdue', ['count' => $summary['overdue']]);
        }

        if ($summary['today'] > 0) {
            $parts[] = __(':count due today', ['count' => $summary['today']]);
        }

        if ($summary['soon'] > 0) {
            $parts[] = __(':count due within :days days', [
                'count' => $summary['soon'],
                'days' => static::REMINDER_DAYS,
            ]);
        }

        return implode(', ', $parts);
    }
}
