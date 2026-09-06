<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Stock Overview memakai SALINAN view tabel Filament, dan salinan tidak ikut
 * berubah waktu paketnya naik versi.
 *
 * Ini bentuk utang yang paling sepi. `composer update` menarik Filament baru,
 * seluruh aplikasi memakai tabel versi baru, dan satu halaman ini tetap
 * memakai tabel versi lama -- tanpa galat, tanpa peringatan, tanpa apa pun
 * yang menandai bahwa ia tertinggal.
 *
 * Sudah sekali meledak. Fork-nya memindahkan header action ke baris toolbar,
 * lalu ikut membuang `:actions-position` bersama `:actions`. Komponen
 * headernya tetap membacanya, jadi halamannya mati dengan "Undefined variable
 * $actionsPosition" -- tetapi blok itu hanya dirender kalau tabelnya punya
 * heading atau description, dan tabel ini tidak punya keduanya. Bug-nya
 * karena itu TIDUR sejak hari fork dibuat sampai tabelnya diberi description
 * di #279, berbulan-bulan kemudian, dan penyebabnya terlihat sama sekali
 * tidak nyambung dengan perubahan yang memicunya.
 *
 * Berkas ini tidak membuat fork-nya hilang. Ia membuat KETERTINGGALANNYA
 * berisik: begitu berkas asli di vendor berubah satu karakter pun, uji ini
 * gagal dan menyebutkan apa yang harus dikerjakan. Menyamakannya jadi
 * keputusan, bukan kelalaian.
 */
class ForkedTableViewTest extends TestCase
{
    /** Berkas asli yang disalin. */
    private const ASLI = 'vendor/filament/tables/resources/views/index.blade.php';

    /** Salinannya. */
    private const SALINAN = 'resources/views/filament/admin/resources/beef-stock/table.blade.php';

    /**
     * Sidik jari berkas asli saat salinan ini terakhir disamakan.
     *
     * Filament v3.3.54, 6 September 2026. Kalau uji ini gagal, JANGAN
     * langsung memperbarui angkanya: bandingkan dulu berkas aslinya yang
     * baru dengan salinan ini, terapkan lagi lima penyimpangan yang
     * didaftar di kepala salinan, baru perbarui angkanya.
     */
    private const SIDIK_JARI_ASLI = '12f8b39bafbe09ed170a454556e50970f99effeb54636f2fe794189644f87852';

    public function test_the_upstream_view_has_not_changed_since_the_fork_was_synced(): void
    {
        $asli = base_path(self::ASLI);

        $this->assertFileExists(
            $asli,
            'Berkas asli tidak ada lagi di tempatnya. Filament kemungkinan mengubah letaknya; '
            .'cari letak barunya dan perbarui '.self::class.'::ASLI.',
        );

        $this->assertSame(
            self::SIDIK_JARI_ASLI,
            hash_file('sha256', $asli),
            "Berkas asli view tabel Filament BERUBAH.\n\n"
            ."Salinan di ".self::SALINAN." tidak ikut berubah, jadi Stock Overview sekarang "
            ."memakai tabel versi lama sementara seluruh aplikasi memakai yang baru.\n\n"
            ."Yang harus dikerjakan:\n"
            ."  1. Bandingkan berkas asli yang baru dengan salinannya.\n"
            ."  2. Terapkan lagi lima penyimpangan yang didaftar di kepala salinan.\n"
            ."  3. Baru perbarui SIDIK_JARI_ASLI di berkas uji ini.\n\n"
            ."Jangan cuma memperbarui angkanya -- itu membungkam penjaganya tanpa "
            ."menyelesaikan apa pun.",
        );
    }

    /**
     * `:actions-position` tidak boleh hilang lagi dari komponen header.
     *
     * Inilah bug yang sudah pernah terjadi, dan bentuknya akan lahir lagi
     * kalau salinannya disamakan dengan terburu-buru.
     */
    public function test_the_header_component_still_receives_its_actions_position(): void
    {
        $salinan = file_get_contents(base_path(self::SALINAN));

        $this->assertStringContainsString(
            ':actions-position="$actionsPosition"',
            $salinan,
            'Komponen header di salinan ini tidak lagi menerima `:actions-position`. '
            .'Halaman akan mati dengan "Undefined variable $actionsPosition" begitu tabelnya '
            .'punya heading atau description.',
        );
    }

    /**
     * CSS tidak boleh kembali masuk ke dalam salinan.
     *
     * Seratus lima baris gaya dulu tinggal di dalamnya dan membuat selisih
     * terhadap berkas asli hampir dua kali lipat -- padahal tidak satu pun
     * di antaranya ada urusannya dengan versi Filament.
     */
    public function test_no_styling_lives_inside_the_fork(): void
    {
        $salinan = file_get_contents(base_path(self::SALINAN));

        $this->assertStringNotContainsString(
            '<style>',
            $salinan,
            'Ada CSS lagi di dalam salinan view tabel. Tempatnya di '
            .'resources/views/filament/admin/stock-overview-table-style.blade.php, '
            .'dan setiap aturannya wajib dibatasi pada `.fi-resource-beef-stocks`.',
        );
    }

    /**
     * Gayanya wajib dibatasi pada halamannya sendiri.
     *
     * Aturan seperti `.fi-ta-table td { padding: 0.25rem }` tanpa pembatas
     * akan mengubah SETIAP tabel di aplikasi ini. Selama gayanya tinggal di
     * dalam view, ia terbatas dengan sendirinya; sekarang ia dimuat di setiap
     * halaman, jadi pembatasnya yang menggantikan peran itu.
     */
    public function test_every_style_rule_is_scoped_to_the_stock_overview_page(): void
    {
        $gaya = file_get_contents(
            base_path('resources/views/filament/admin/stock-overview-table-style.blade.php')
        );

        // Komentar Blade dibuang; ia menyebut nama kelas sebagai penjelasan.
        $gaya = preg_replace('/\{\{--.*?--\}\}/s', ' ', $gaya);

        $isiStyle = preg_match('/<style>(.*?)<\/style>/s', $gaya, $cocok) ? $cocok[1] : '';

        $this->assertNotSame('', trim($isiStyle), 'Berkas gayanya kosong.');

        // Komentar CSS juga dibuang, lalu tiap pemilih diperiksa satu per satu.
        $isiStyle = preg_replace('/\/\*.*?\*\//s', ' ', $isiStyle);

        $tanpaPembatas = [];

        foreach (explode('}', $isiStyle) as $aturan) {
            $pemilih = trim(explode('{', $aturan)[0]);

            if ($pemilih === '') {
                continue;
            }

            foreach (explode(',', $pemilih) as $satu) {
                $satu = trim($satu);

                if ($satu === '' || str_starts_with($satu, '@')) {
                    continue;
                }

                if (! str_contains($satu, '.fi-resource-beef-stocks')) {
                    $tanpaPembatas[] = $satu;
                }
            }
        }

        sort($tanpaPembatas);

        $this->assertSame(
            [],
            $tanpaPembatas,
            "Pemilih berikut tidak dibatasi pada `.fi-resource-beef-stocks`, jadi ia mengubah "
            ."tabel di SELURUH aplikasi:\n".implode("\n", $tanpaPembatas),
        );
    }
}
