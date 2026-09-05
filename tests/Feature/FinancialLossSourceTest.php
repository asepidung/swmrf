<?php

namespace Tests\Feature;

use App\Models\FinancialLoss;
use Tests\TestCase;

/**
 * Daftar sumber kerugian hanya boleh punya SATU rumah.
 *
 * Saringan di layar dulu menuliskan pilihannya sendiri dan hanya memuat
 * 'Cattle Weighing', dengan komentar "More can be added here later" yang
 * tidak pernah ditepati. Susut kirim sudah ditulis sejak lama dengan sumber
 * 'Delivery Order' -- barisnya ada di tabel, hanya tidak pernah bisa dipilih.
 *
 * Ini bentuk kesalahan yang sudah berulang di proyek ini: daftar yang ditulis
 * tangan selalu ketinggalan dari yang benar-benar ditulis kode, dan
 * ketinggalannya tidak menimbulkan galat apa pun -- hanya baris yang tidak
 * terlihat.
 */
class FinancialLossSourceTest extends TestCase
{
    /**
     * Tidak ada yang menulis sumber kerugian sebagai teks lepas.
     *
     * Yang dijaga di sini POLANYA, bukan dua berkas yang kebetulan sudah
     * ketahuan -- penulis ketiga akan lahir dengan menyalin tetangganya.
     */
    public function test_no_loss_is_written_with_a_hand_typed_source(): void
    {
        $pelanggar = [];

        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($berkas as $satu) {
            if (! $satu->isFile() || $satu->getExtension() !== 'php') {
                continue;
            }

            $isi = $this->tanpaKomentar(file_get_contents($satu->getPathname()));

            if (! str_contains($isi, 'financialLoss()')) {
                continue;
            }

            // Hanya sumber yang DITULIS ke baris kerugian. Berkas yang sama
            // sering juga menulis `transaction_type` untuk pergerakan stok --
            // kolom bernama sama, tabel berbeda, dan bukan urusan daftar ini.
            if (preg_match(
                "/financialLoss\(\)->updateOrCreate\(\s*\[[^\]]*'transaction_type'\s*=>\s*'([^']+)'/s",
                $isi,
                $cocok,
            )) {
                $pelanggar[] = basename($satu->getPathname()).'  (\''.$cocok[1].'\')';
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Sumber kerugian berikut ditulis sebagai teks lepas. Pakai tetapan di "
            ."`App\\Models\\FinancialLoss` supaya saringan di layar ikut mengetahuinya:\n"
            .implode("\n", $pelanggar),
        );
    }

    /** Saringan di layar membaca daftar itu, bukan menulis ulang isinya. */
    public function test_the_screen_filter_reads_the_shared_list(): void
    {
        $isi = file_get_contents(
            app_path('Filament/Admin/Resources/FinancialLossResource.php')
        );

        $this->assertStringContainsString(
            'FinancialLoss::SEMUA_SUMBER',
            $isi,
            'Saringan sumber kerugian menulis pilihannya sendiri lagi.',
        );
    }

    /** Setiap sumber punya terjemahannya, karena pilihannya lewat `__()`. */
    public function test_every_source_can_be_translated(): void
    {
        $en = json_decode(file_get_contents(base_path('lang/en.json')), true);
        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);

        foreach (FinancialLoss::SEMUA_SUMBER as $sumber) {
            $this->assertArrayHasKey($sumber, $en, "Sumber '$sumber' belum terdaftar di lang/en.json.");
            $this->assertArrayHasKey($sumber, $id, "Sumber '$sumber' belum terdaftar di lang/id.json.");
        }
    }

    /** Komentar dibuang supaya keterangannya tidak ikut tertuduh. */
    private function tanpaKomentar(string $isi): string
    {
        $hasil = '';

        foreach (@token_get_all($isi) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $hasil .= is_array($token) ? $token[1] : $token;
        }

        return $hasil;
    }
}
