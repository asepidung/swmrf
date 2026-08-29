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
    /**
     * Kedua modul request sekaligus. Alurnya kembar, jadi teksnya juga tumbuh
     * berbarengan -- dan lubang yang sama gampang terulang di sebelahnya.
     *
     * @return array<int, string>
     */
    protected function scannedFiles(): array
    {
        $files = [];

        foreach (['ProductRequisitionResource', 'MaterialRequisitionResource'] as $resource) {
            $files[] = app_path('Filament/Admin/Resources/' . $resource . '.php');
            $files = array_merge(
                $files,
                glob(app_path('Filament/Admin/Resources/' . $resource . '/Pages/*.php')) ?: [],
            );
        }

        return $files;
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

    /**
     * Halaman DP/pembayaran: SELURUH __() diperiksa, bukan cuma notifikasi.
     *
     * Berbeda dari modul Request yang label formulirnya memang sudah lama
     * banyak yang belum terdaftar, ketiga halaman ini baru dan masih bersih,
     * jadi bisa dijaga penuh sejak awal. Ditemukan 28 Agustus 2026: sebelas
     * kuncinya terdaftar di id.json tetapi TIDAK di en.json.
     *
     * @return array<int, string>
     */
    protected function paymentPageFiles(): array
    {
        return [
            app_path('Filament/Admin/Resources/PurchaseProductResource/Pages/ViewPurchaseProduct.php'),
            app_path('Filament/Admin/Resources/PurchaseMaterialResource/Pages/ViewPurchaseMaterial.php'),
            app_path('Filament/Admin/Resources/PayableResource/Pages/ViewPayable.php'),
        ];
    }

    /** @test */
    public function it_registers_every_payment_page_string_in_both_languages()
    {
        $missing = [];

        $strings = [
            'id' => json_decode(file_get_contents(lang_path('id.json')), true),
            'en' => json_decode(file_get_contents(lang_path('en.json')), true),
        ];

        foreach ($this->paymentPageFiles() as $file) {
            if (! file_exists($file)) {
                continue;
            }

            preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file), $matches);

            foreach (array_unique($matches[1]) as $key) {
                $key = stripslashes($key);

                foreach ($strings as $locale => $registered) {
                    if (! array_key_exists($key, $registered)) {
                        $missing[] = $locale . '.json: ' . $key;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($missing)),
            "Teks berikut dipakai di halaman pembayaran tapi belum terdaftar:\n"
            . implode("\n", array_unique($missing)),
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
