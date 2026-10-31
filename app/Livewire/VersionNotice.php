<?php

namespace App\Livewire;

use App\Support\AppVersion;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Jendela versi aplikasi (permintaan Ayah, 31 Oktober 2026):
 *  - riwayat lengkap, dibuka dengan mengklik "Ver ..." di footer;
 *  - "Yang baru": muncul SEKALI per pengguna per versi, saat ia membuka aplikasi sesudah ada versi yang belum dilihatnya.
 *
 * Pengguna yang belum pernah tercatat (`seen_version` kosong) TIDAK dibanjiri riwayat: versi sekarang langsung dianggap
 * sudah dilihat, dan jendela baru muncul untuk versi berikutnya.
 */
class VersionNotice extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $unseen = [];

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        if (blank($user->seen_version)) {
            $this->markSeen(AppVersion::current());

            return;
        }

        $this->unseen = AppVersion::entriesAfter($user->seen_version);
    }

    public function acknowledge(): void
    {
        $this->markSeen(AppVersion::current());
        $this->unseen = [];
    }

    private function markSeen(string $version): void
    {
        // Tanpa model: mencatat "sudah melihat" tidak boleh ikut menulis riwayat aktivitas atau mengubah `updated_at`.
        DB::table('users')->where('id', auth()->id())->update(['seen_version' => $version]);
    }

    public function render()
    {
        return view('livewire.version-notice', [
            'current' => AppVersion::current(),
            'entries' => AppVersion::entries(),
        ]);
    }
}
