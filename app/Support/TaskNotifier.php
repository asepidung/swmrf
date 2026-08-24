<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\TaskAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim pemberitahuan tugas ke pengguna yang berhak menindaklanjutinya.
 *
 * Dipusatkan di satu tempat supaya saat modul lain menyusul, pemanggilnya
 * cukup satu baris dan aturan siapa-menerima-apa tidak tersebar.
 */
class TaskNotifier
{
    /**
     * Kirim ke semua pengguna aktif yang memegang salah satu permission.
     *
     * Programmer selalu ikut menerima, mengikuti perilaku hasPermission().
     *
     * Pengiriman dibungkus try/catch: notifikasi TIDAK BOLEH menggagalkan aksi
     * bisnis yang memicunya. Kalau layanan push sedang bermasalah, dokumen
     * tetap harus tersimpan.
     */
    public static function notifyPermissionHolders(
        string|array $permissions,
        string $title,
        string $body,
        string $url,
        ?string $tag = null,
        ?int $exceptUserId = null,
    ): int {
        $recipients = static::permissionHolders((array) $permissions, $exceptUserId);

        if ($recipients->isEmpty()) {
            return 0;
        }

        $sent = 0;

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new TaskAlert($title, $body, $url, $tag));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Gagal mengirim notifikasi tugas', [
                    'user_id' => $recipient->id,
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * @param  array<int, string>  $permissions
     * @return Collection<int, User>
     */
    protected static function permissionHolders(array $permissions, ?int $exceptUserId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->where(function ($query) use ($permissions) {
                $query->where('role', 'programmer')
                    ->orWhereHas('permissions', fn ($q) => $q->whereIn('name', $permissions));
            })
            // Tanpa langganan, notifikasi tidak akan sampai ke mana pun.
            // Menyaringnya di sini menghindari percobaan kirim yang sia-sia.
            ->whereHas('pushSubscriptions')
            ->get();
    }

    /**
     * Berapa pengguna aktif yang benar-benar berlangganan notifikasi.
     *
     * Angka ini ditampilkan di Dashboard dengan sengaja. Pada proyek
     * sebelumnya, fiturnya sempurna secara teknis tetapi hanya 3 dari 193
     * orang yang menekan "Izinkan" — jadi berbulan-bulan tidak ada notifikasi
     * yang sampai ke siapa pun, dan tidak ada yang menyadarinya.
     *
     * @return array{subscribed: int, total: int}
     */
    public static function subscriptionCoverage(): array
    {
        $total = User::query()->where('is_active', true)->count();

        $subscribed = User::query()
            ->where('is_active', true)
            ->whereHas('pushSubscriptions')
            ->count();

        return ['subscribed' => $subscribed, 'total' => $total];
    }
}
