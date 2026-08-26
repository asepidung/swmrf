<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Bentuk ikon PWA dan tampilan notifikasi.
 *
 * Ketiganya cacat yang hanya kelihatan di perangkat sungguhan, tidak pernah
 * muncul sebagai error: ikon terpotong di layar utama, logo yang sama tampil
 * dua kali dalam satu notifikasi, dan pesan yang terpotong karena kepanjangan.
 */
class PwaIconAndNotificationShapeTest extends TestCase
{
    protected function manifest(): array
    {
        return json_decode(file_get_contents(public_path('manifest.json')), true);
    }

    /** @test */
    public function it_keeps_the_manifest_valid_json()
    {
        $this->assertIsArray($this->manifest());
    }

    /**
     * Satu file tidak boleh merangkap dua peruntukan.
     *
     * "maskable" menyuruh sistem memotong ikon ke dalam bentuk mask dan hanya
     * menjamin lingkaran di tengah. Logo yang menyentuh tepi kanvas PASTI
     * terpotong. Sebelumnya satu file yang sama didaftarkan sebagai
     * "any maskable", sehingga tidak ada versi yang aman dipotong.
     *
     * @test
     */
    public function it_never_declares_one_icon_as_both_any_and_maskable()
    {
        foreach ($this->manifest()['icons'] as $icon) {
            $this->assertNotSame(
                'any maskable',
                $icon['purpose'] ?? null,
                'Ikon ' . $icon['src'] . ' merangkap dua peruntukan sekaligus.',
            );
        }
    }

    /** @test */
    public function it_provides_both_an_any_and_a_maskable_icon()
    {
        $purposes = array_column($this->manifest()['icons'], 'purpose');

        $this->assertContains('any', $purposes);
        $this->assertContains('maskable', $purposes);
    }

    /**
     * Ukuran yang didaftarkan harus benar-benar ukuran filenya. Sebelumnya satu
     * file 512x512 didaftarkan sekaligus sebagai 192x192.
     *
     * @test
     */
    public function it_declares_the_real_size_of_every_icon()
    {
        foreach ($this->manifest()['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));

            $this->assertFileExists($path);

            [$width, $height] = getimagesize($path);
            [$declaredWidth, $declaredHeight] = array_map('intval', explode('x', $icon['sizes']));

            $this->assertSame($declaredWidth, $width, $icon['src'] . ' lebarnya tidak sesuai deklarasi.');
            $this->assertSame($declaredHeight, $height, $icon['src'] . ' tingginya tidak sesuai deklarasi.');
        }
    }

    /**
     * Isi ikon maskable wajib berada di dalam area aman.
     *
     * Area aman hanyalah lingkaran di tengah, sekitar 80% kanvas. Kalau piksel
     * di tepi masih ada isinya, bentuk mask apa pun akan memotongnya.
     *
     * @test
     */
    public function it_keeps_maskable_icons_clear_of_the_edges()
    {
        foreach ($this->manifest()['icons'] as $icon) {
            if (($icon['purpose'] ?? null) !== 'maskable') {
                continue;
            }

            $path = public_path(ltrim($icon['src'], '/'));
            $image = @imagecreatefrompng($path);
            $size = imagesx($image);

            // Latar diambil dari pojok, yang menurut definisi berada di luar
            // area aman dan karenanya memang boleh terpotong.
            $background = imagecolorat($image, 0, 0);

            $margin = (int) round($size * 0.10);
            $different = 0;

            for ($x = 0; $x < $size; $x++) {
                foreach ([0, $margin, $size - 1 - $margin, $size - 1] as $y) {
                    if (imagecolorat($image, $x, $y) !== $background) {
                        $different++;
                    }
                }
            }

            imagedestroy($image);

            $this->assertSame(
                0,
                $different,
                $icon['src'] . ' masih punya isi di pinggir kanvas, jadi akan terpotong saat di-mask.',
            );
        }
    }

    /**
     * Notifikasi WAJIB membawa ikon eksplisit.
     *
     * Sempat diputuskan sebaliknya, dengan alasan logo tampil ganda -- di kiri
     * dari manifest, di kanan dari "icon". Keputusan itu DIBATALKAN setelah
     * diuji di perangkat sungguhan: bila "icon" kosong, Android Chrome membuat
     * avatar huruf dari nama domain (huruf "C" dari coba.wijayameat.co.id),
     * dan pengguna menyangkanya inisial nama pengirim. Logo ganda lebih baik
     * daripada huruf yang menyesatkan.
     *
     * Dua sisi diperiksa sekaligus: TaskAlert boleh saja mengirim "icon", tapi
     * tidak ada gunanya bila service worker membuangnya. Itu persis yang
     * sempat terjadi.
     *
     * @test
     */
    public function it_always_sends_an_explicit_notification_icon()
    {
        $worker = file_get_contents(public_path('sw.js'));

        $options = substr(
            $worker,
            strpos($worker, 'showNotification'),
            strpos($worker, 'notificationclick') - strpos($worker, 'showNotification'),
        );

        $this->assertStringContainsString('icon:', $options, 'Service worker membuang ikon yang dikirim server.');
        $this->assertStringContainsString('badge:', $options);

        $taskAlert = file_get_contents(app_path('Notifications/TaskAlert.php'));

        $this->assertStringContainsString(
            '->icon(',
            $taskAlert,
            'TaskAlert tidak mengirim ikon, sehingga browser memunculkan avatar huruf.',
        );

        // Android memotong ikon besar notifikasi menjadi LINGKARAN. Versi "any"
        // isinya menyentuh tepi kanvas, jadi sisi kiri-kanannya pasti terpangkas.
        // Yang boleh dipakai hanya versi beralas yang punya area aman.
        foreach (['->icon(', '->badge('] as $call) {
            preg_match('/' . preg_quote($call, '/') . "'([^']+)'/", $taskAlert, $match);

            $this->assertStringContainsString(
                'maskable',
                $match[1] ?? '',
                'Notifikasi memakai ikon yang isinya menyentuh tepi, jadi akan terpotong lingkaran.',
            );
        }
    }

    /**
     * Judul dan isi notifikasi sama-sama terpotong di layar HP bila panjang.
     *
     * @test
     */
    public function it_keeps_the_review_alert_short_enough_to_read_at_a_glance()
    {
        foreach (['id', 'en'] as $locale) {
            $strings = json_decode(file_get_contents(lang_path($locale . '.json')), true);

            foreach (['New Beef Request', 'Waiting for your review.'] as $key) {
                $this->assertArrayHasKey($key, $strings, "Kunci '$key' belum terdaftar di $locale.json.");
                $this->assertLessThanOrEqual(
                    40,
                    mb_strlen($strings[$key]),
                    "Teks notifikasi '$key' di $locale.json terlalu panjang untuk layar HP.",
                );
            }
        }
    }
}
