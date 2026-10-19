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
            // icon: ikon besar berwarna, kanan. WAJIB diisi -- tanpa ini
            // Android Chrome membuat avatar huruf dari nama domain ('C' dari
            // coba.wijayameat.co.id) yang disangka inisial pengirim. Sudah
            // dicoba dilepas DUA KALI dengan hasil sama; lihat sw.js.
            //
            // Percobaan ketiga (19 Oktober 2026): gambar TRANSPARAN supaya hanya
            // satu logo yang terlihat -> HyperOS merendernya sebagai kotak biru
            // polos. Jangan dicoba lagi. Logo kiri adalah ikon aplikasi dari
            // Android dan tidak bisa dilepas; gambar kanan kini piktogram per
            // jenis notifikasi (lihat NotificationKind), penuh warna sampai
            // tepi dan aman bila dipotong menjadi lingkaran.
            ->icon(\App\Support\NotificationKind::iconFor($this->tag))
            // badge: ikon kecil di status bar, kiri. Android HANYA membaca
            // kanal alpha gambar ini lalu mewarnainya sendiri (putih) --
            // memberinya logo berwarna penuh membuat seluruh kanvas dianggap
            // "isi" dan tampil sebagai blok padat, bukan siluet yang bisa
            // dikenali. Aset ini dibuat khusus: siluet putih di atas latar
            // transparan, bukan logo warna aslinya.
            ->badge('/img/pwalogo-badge-192.png')
            ->data([
                'url' => $this->url,
                // `tag` membuat notifikasi sejenis saling menimpa alih-alih
                // menumpuk, supaya layar penerima tidak penuh oleh dokumen
                // yang sebenarnya sama.
                'tag' => $this->tag,
            ])
            // urgency high: tanpa ini layanan push memperlakukannya sebagai "normal" dan Android yang layarnya mati
            // (mode hemat daya / Doze) boleh menundanya sampai perangkat aktif lagi -- terlalu lambat untuk tugas yang
            // menunggu persetujuan seseorang.
            ->options(['TTL' => 3600, 'urgency' => 'high']);
    }
}
