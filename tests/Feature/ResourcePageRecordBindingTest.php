<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Halaman Resource menerima MODEL, bukan angka id.
 *
 * Filament memasang route binding untuk `{record}` di setiap route Resource,
 * jadi saat `mount()` berjalan parameternya sudah berupa objek model. Halaman
 * yang menulis
 *
 *     public function mount($record) { $this->record = Model::findOrFail($record); }
 *
 * memanggil findOrFail dengan OBJEK sebagai id. Tidak pernah ketemu, dan
 * findOrFail menjawab dengan 404.
 *
 * Ini bentuk kegagalan yang paling menyesatkan yang bisa dipilih:
 *
 *  - Laravel TIDAK mencatat 404 ke log -- `ModelNotFoundException` ada di
 *    daftar `dontReport` bawaan -- jadi di produksi halamannya hanya menjawab
 *    "404 Not Found" tanpa meninggalkan satu baris pun di Log Viewer;
 *  - terlihat persis seperti halaman yang memang tidak ada, padahal kodenya
 *    berjalan dan datanya lengkap.
 *
 * **DAN PENGUJIANNYA IKUT TERTIPU.** `Livewire::test($page, ['record' => $id])`
 * menyerahkan ANGKA, bukan model -- jalur yang tidak pernah dilalui peramban.
 * Jadi pengujiannya hijau sementara halamannya 404 bagi setiap orang yang
 * membukanya. Ditemukan pada halaman Receive Payment, 3 September 2026, dari
 * laporan Project Owner.
 *
 * Karena itu halaman berparameter record WAJIB diuji lewat HTTP, bukan hanya
 * lewat Livewire::test.
 */
class ResourcePageRecordBindingTest extends TestCase
{
    /**
     * Tidak ada halaman yang mencari ulang recordnya dengan findOrFail.
     *
     * Filament sudah menyerahkan modelnya. Mencarinya lagi bukan cuma
     * mubazir -- ia justru yang menghasilkan 404 itu.
     */
    public function test_no_resource_page_looks_its_record_up_again(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if (! preg_match('/function mount\s*\([^)]*\$record/', $source)) {
                continue;
            }

            // Yang aman: memeriksa dulu apakah yang datang memang sudah model.
            if (str_contains($source, 'instanceof')) {
                continue;
            }

            if (preg_match('/::findOrFail\(\$record\)|::find\(\$record\)/', $source)) {
                $offenders[] = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Halaman berikut mencari ulang recordnya dengan findOrFail, padahal Filament "
            ."sudah menyerahkan modelnya. Hasilnya findOrFail dipanggil dengan objek "
            ."sebagai id, tidak pernah ketemu, dan halamannya menjawab 404 -- tanpa satu "
            .'baris pun di log.',
        );
    }
}
