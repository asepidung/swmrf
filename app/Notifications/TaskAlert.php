<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Pemberitahuan tugas antar-peran, dikirim sebagai Web Push ke perangkat.
 *
 * Menggantikan toast lintas-pengguna yang dulu dipancarkan GlobalTaskPoller.
 * Toast bawaan Filament untuk pelaku aksinya sendiri — "berhasil disimpan",
 * "gagal" — sengaja TIDAK diganti, karena itu umpan balik untuk dirinya
 * sendiri, bukan pemberitahuan untuk orang lain.
 *
 * SENGAJA TIDAK memakai ShouldQueue. Alasannya di .agents/agents.md:
 * QUEUE_CONNECTION masih `sync` dan tidak ada queue worker di shared hosting,
 * sehingga notifikasi yang diantre justru akan menumpuk diam-diam tanpa error.
 */
class TaskAlert extends Notification
{
    public function __construct(
        protected string $title,
        protected string $body,
        protected string $url,
        protected ?string $tag = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title($this->title)
            ->body($this->body)
            // Kiri: sudah otomatis diisi browser dari manifest.
            // Kanan (besar): kita isi eksplisit, jika tidak Android Chrome akan
            // membuat ikon huruf (misal 'C' dari coba.wijayameat.co.id).
            // Versi BERALAS, bukan pwalogo-192.png yang isinya menyentuh tepi.
            // Android memotong ikon besar notifikasi menjadi lingkaran, jadi
            // logo yang mepet tepi kiri-kanan pasti terpangkas.
            ->icon('/img/pwalogo-maskable-192.png')
            ->badge('/img/pwalogo-maskable-192.png')
            ->data([
                'url' => $this->url,
                // `tag` membuat notifikasi sejenis saling menimpa alih-alih
                // menumpuk, supaya layar penerima tidak penuh oleh dokumen
                // yang sebenarnya sama.
                'tag' => $this->tag,
            ])
            ->options(['TTL' => 3600]);
    }
}
