<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kegagalan yang ditangkap tetap harus meninggalkan jejak.
 *
 * Laravel mencatat setiap pengecualian yang TIDAK ditangkap: tanggalnya,
 * siapa yang mengalaminya, dan jejak tumpukannya. Begitu sebuah `catch`
 * memasangnya, semua itu hilang -- dan yang tersisa cuma notifikasi merah
 * yang lenyap begitu layarnya ditutup.
 *
 * Dua puluh lima `catch` di aplikasi ini berada dalam keadaan itu. Bentuknya
 * selalu sama dan selalu terlihat lengkap:
 *
 *     } catch (\Exception $e) {
 *         Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();
 *     }
 *
 * Penggunanya diberi tahu, jadi tidak ada yang terasa salah. Yang tidak ada
 * hanya kemampuan menemukannya lagi besok pagi -- persis ketika orang gudang
 * bilang "tadi gagal" dan tidak ingat pesannya.
 *
 * `report($e)` mengembalikan pencatatannya tanpa mengubah apa pun yang
 * dilihat pengguna. Ia juga menghormati daftar jangan-catat Laravel, jadi
 * `ValidationException` -- misalnya penolakan stok minus -- tidak ikut
 * membanjiri log.
 *
 * `catch` yang isinya BENAR-BENAR kosong tidak dijaga di sini. Empat yang ada
 * semuanya sengaja dan sudah berkomentar: nilai tampilan yang gagal dibaca,
 * barcode yang tidak terbaca lalu diisi tangan, dan tanggal yang dibiarkan
 * ditolak Filament sendiri. Kekosongannya terlihat langsung oleh siapa pun
 * yang membacanya -- berbeda dengan yang di atas, yang justru terlihat sudah
 * lengkap.
 */
class SwallowedFailureTest extends TestCase
{
    public function test_no_caught_failure_disappears_without_a_trace(): void
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

            if (! preg_match_all('/\}\s*catch\s*\(([^)]*)\)\s*\{/', $isi, $cocok, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($cocok[0] as $nomor => [$teks, $posisi]) {
                // Tanpa variabel tidak ada yang bisa dilaporkan. Bentuk itu
                // dipakai untuk kegagalan yang memang sudah selesai di
                // tempatnya -- salah ketik tanggal di baris perintah, misalnya.
                if (! str_contains($cocok[1][$nomor][0], '$')) {
                    continue;
                }

                $blok = $this->isiBlok($isi, $posisi + strlen($teks) - 1);

                if (trim($blok) === '') {
                    continue;
                }

                if (preg_match('/Log::|logger\(|report\(|throw\b/', $blok)) {
                    continue;
                }

                $pelanggar[] = str_replace(app_path().DIRECTORY_SEPARATOR, '', $satu->getPathname())
                    .':'.(substr_count(substr($isi, 0, $posisi), "\n") + 1);
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Kegagalan berikut ditangkap tanpa meninggalkan jejak apa pun. Notifikasinya hilang "
            ."begitu layarnya ditutup, dan sesudah itu tidak ada tanggal, tidak ada siapa yang "
            ."mengalaminya, dan tidak ada jejak tumpukan. Tambahkan `report(\$e);`:\n"
            .implode("\n", $pelanggar),
        );
    }

    /** Isi kurung kurawal yang dibuka pada posisi ini. */
    private function isiBlok(string $isi, int $buka): string
    {
        $dalam = 0;

        for ($j = $buka; $j < strlen($isi); $j++) {
            if ($isi[$j] === '{') {
                $dalam++;
            } elseif ($isi[$j] === '}') {
                $dalam--;

                if ($dalam === 0) {
                    return substr($isi, $buka + 1, $j - $buka - 1);
                }
            }
        }

        return '';
    }

    /**
     * Komentar diganti spasi dengan PANJANG YANG SAMA.
     *
     * Bukan dibuang: nomor baris dan pencocokan kurung dihitung dari teks
     * yang sama, jadi panjangnya tidak boleh bergeser satu karakter pun.
     */
    private function tanpaKomentar(string $isi): string
    {
        $hasil = '';

        foreach (@token_get_all($isi) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $hasil .= preg_replace('/[^\n]/', ' ', $token[1]);

                continue;
            }

            $hasil .= is_array($token) ? $token[1] : $token;
        }

        return $hasil;
    }
}
