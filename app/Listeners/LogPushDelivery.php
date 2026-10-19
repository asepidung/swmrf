<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\Events\NotificationFailed;

/**
 * Mencatat pengiriman notifikasi push yang GAGAL.
 *
 * Paket webpush hanya mengirim `NotificationFailed` tanpa pendengar mana pun, dan menghapus langganan yang
 * kedaluwarsa (404/410) tanpa jejak -- sehingga "notifikasi tidak masuk" tidak meninggalkan petunjuk apa pun.
 * Kini setiap kegagalan tercatat: langganan mana, milik siapa, status dari layanan push, dan alasannya.
 * Isi pesan dan kunci langganan sengaja tidak dicatat.
 *
 * Tidak perlu didaftarkan manual: Laravel menemukan pendengar di app/Listeners dari tipe parameter `handle()`
 * (mendaftarkannya lagi membuat setiap kegagalan tercatat dua kali).
 */
class LogPushDelivery
{
    public function handle(NotificationFailed $event): void
    {
        $response = $event->report->getResponse();

        Log::warning('Notifikasi push gagal terkirim', [
            'subscription_id' => $event->subscription->id,
            'user_id' => $event->subscription->subscribable_id,
            'host' => parse_url($event->report->getEndpoint(), PHP_URL_HOST),
            'status' => $response?->getStatusCode(),
            'reason' => $event->report->getReason(),
            'expired_and_removed' => $event->report->isSubscriptionExpired(),
        ]);
    }
}
