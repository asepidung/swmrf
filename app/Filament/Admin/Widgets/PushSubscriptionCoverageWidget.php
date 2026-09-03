<?php

namespace App\Filament\Admin\Widgets;

use App\Support\TaskNotifier;
use Filament\Widgets\Widget;

/**
 * Berapa pengguna yang benar-benar berlangganan notifikasi perangkat.
 *
 * Widget ini ada karena satu pelajaran mahal dari proyek sebelumnya: fitur
 * notifikasinya sempurna secara teknis — kunci benar, kode benar, semuanya
 * jalan — tetapi hanya 3 dari 193 orang yang pernah menekan "Izinkan".
 * Selama berbulan-bulan praktis tidak ada notifikasi yang sampai ke siapa pun,
 * dan tidak ada yang menyadarinya karena tidak ada yang menghitung.
 *
 * Angkanya ditampilkan sejak hari pertama supaya kegagalan itu terlihat,
 * bukan tersembunyi.
 *
 * MUNCUL HANYA SAAT BELUM SEMUANYA BERLANGGANAN. "3 / 3 (100%)" tidak
 * menyuruh siapa pun melakukan apa pun, dan pengumuman yang tidak menuntut
 * tindakan justru mengajari orang melewati baris itu -- termasuk pada hari
 * angkanya turun. Yang dijaga tetap sama: kegagalannya terlihat. Yang berubah
 * hanya keberhasilannya berhenti minta tempat.
 */
class PushSubscriptionCoverageWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.push-subscription-coverage-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -2;

    /** Hanya kepada yang mengawasi sistem, dan hanya saat masih ada yang tertinggal. */
    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user || ! ($user->isProgrammer() || $user->hasPermission('view_users'))) {
            return false;
        }

        $coverage = TaskNotifier::subscriptionCoverage();

        // Tanpa satu pun pengguna aktif tidak ada yang bisa ditagih, jadi
        // tidak ada yang perlu dikatakan.
        if ($coverage['total'] === 0) {
            return false;
        }

        return $coverage['subscribed'] < $coverage['total'];
    }

    /** @return array{subscribed: int, total: int} */
    public function getCoverage(): array
    {
        return TaskNotifier::subscriptionCoverage();
    }

    public function getPercentage(): int
    {
        $coverage = $this->getCoverage();

        if ($coverage['total'] === 0) {
            return 0;
        }

        return (int) round($coverage['subscribed'] / $coverage['total'] * 100);
    }

    public function isCurrentUserSubscribed(): bool
    {
        return auth()->user()?->pushSubscriptions()->exists() ?? false;
    }
}
