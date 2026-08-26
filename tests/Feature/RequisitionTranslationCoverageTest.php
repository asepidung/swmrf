<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Setiap teks modul Request Beef wajib terdaftar di kedua berkas bahasa.
 *
 * Ini menutup celah yang baru saja terjadi: satu alur notifikasi ditambahkan
 * lengkap dengan sepuluh teks ber-__(), tetapi tidak satu pun didaftarkan.
 * Gejalanya tidak terlihat sebagai error -- Laravel menampilkan kuncinya apa
 * adanya, sehingga pengguna yang memilih Indonesia tetap melihat kalimat
 * bahasa Inggris dan tidak ada yang menyadarinya.
 *
 * Polanya sengaja meniru NavigationTerminologyTest: memindai kode jauh lebih
 * murah daripada mengaudit manual, dan tidak bisa lupa.
 */
class RequisitionTranslationCoverageTest extends TestCase
{
    /** @return array<int, string> */
    protected function scannedFiles(): array
    {
        $base = app_path('Filament/Admin/Resources/ProductRequisitionResource');

        return array_merge(
            [app_path('Filament/Admin/Resources/ProductRequisitionResource.php')],
            glob($base . '/Pages/*.php') ?: [],
        );
    }

    /**
     * Hanya teks yang benar-benar sampai ke pengguna sebagai pemberitahuan.
     *
     * Sengaja TIDAK memindai seluruh __() di modul ini: label formulirnya
     * memang sudah lama banyak yang belum terdaftar, dan membereskannya
     * pekerjaan tersendiri. Yang dijaga di sini adalah lubang yang baru saja
     * menggigit -- teks notifikasi yang ditambahkan tanpa didaftarkan.
     *
     * @return array<int, string>
     */
    protected function translationKeys(): array
    {
        $keys = [];

        foreach ($this->scannedFiles() as $file) {
            $source = file_get_contents($file);

            // Argumen judul dan isi pada pemanggilan TaskNotifier.
            preg_match_all('/TaskNotifier::notify\w+\((.*?)\);/s', $source, $calls);

            // Judul dan isi toast Filament.
            preg_match_all('/Notification::make\(\)(.*?)->send\(\)/s', $source, $toasts);

            foreach (array_merge($calls[1], $toasts[1]) as $block) {
                preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $block, $matches);

                foreach ($matches[1] as $key) {
                    $keys[] = stripslashes($key);
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /** @test */
    public function it_finds_translation_keys_to_check()
    {
        $this->assertNotEmpty(
            $this->translationKeys(),
            'Tidak menemukan satu pun __() -- pemindainya kemungkinan rusak, bukan modulnya yang bersih.',
        );
    }

    /** @test */
    public function it_registers_every_requisition_string_in_both_languages()
    {
        $missing = [];

        foreach (['id', 'en'] as $locale) {
            $strings = json_decode(file_get_contents(lang_path($locale . '.json')), true);

            foreach ($this->translationKeys() as $key) {
                if (! array_key_exists($key, $strings)) {
                    $missing[] = $locale . '.json: ' . $key;
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Teks berikut dipakai di modul Request Beef tapi belum terdaftar:\n" . implode("\n", $missing),
        );
    }
}
