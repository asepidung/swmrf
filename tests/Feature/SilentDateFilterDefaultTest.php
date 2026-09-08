<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Silent date filter itu STANDAR TERDOKUMENTASI (`project.md:112`, wajib
 * untuk halaman Detail/Flat List), bukan sekadar kebiasaan. Satu-satunya
 * implementasi yang benar-benar mengikutinya sejak awal adalah
 * `CashBookResource` (ditulis Project Owner sendiri, 30 Agustus 2026):
 *
 *     form  -> DatePicker punya ->default(bulan berjalan)
 *     query -> membaca $data apa adanya (form sudah menjamin ada isi)
 *     badge -> cuma tampil kalau nilainya BEDA dari default
 *
 * Bug yang ditemukan 7 September 2026 BUKAN soal "ada default" atau "tidak
 * ada default" -- soal form dan query TIDAK SEPAKAT. 15 modul lain menyalin
 * pola CashBookResource tapi kehilangan `->default()`-nya di jalan: form
 * KOSONG kalau panel dibuka (terlihat netral), padahal `query()`-nya tetap
 * diam-diam jatuh ke `now()->startOfMonth()`. User tidak punya cara
 * mengetahui data sedang dibatasi, bahkan lewat pemeriksaan manual --
 * berbeda dari CashBookResource yang tetap jujur kalau diperiksa.
 *
 * Perbaikan PERTAMA (PR #374) mengambil arah yang salah: menghapus
 * `->default()`-nya sama sekali, sehingga kosong = tanpa batasan sama
 * sekali. Itu mengubah balik perilaku bawaan (dari "bulan berjalan" jadi
 * "sepanjang masa"), bukan memperbaiki ketidaksepakatannya -- dan
 * melanggar `project.md:112`. PR berikutnya mengoreksi ke pola
 * CashBookResource yang benar.
 *
 * Test ini memindai SELURUH `app/Filament/` mencari Filter tanggal yang
 * `query()`-nya punya fallback `?? now(...)` TANPA `->default()` yang
 * sepadan di form-nya -- pagar supaya "telepon rusak" ini tidak terulang
 * di modul berikutnya.
 */
class SilentDateFilterDefaultTest extends TestCase
{
    /** @test */
    public function every_date_filter_whose_query_falls_back_to_now_has_a_matching_default_in_its_form()
    {
        $offenders = [];

        foreach ($this->filamentPhpFiles() as $file) {
            $content = $this->tanpaKomentar(file_get_contents($file));
            $relatif = $this->relative($file);

            foreach ($this->filterBlocks($content) as [$blockStart, $block]) {
                $punyaFallbackNow = (bool) preg_match('/\?\?\s*now\(/', $block);

                if (! $punyaFallbackNow) {
                    continue;
                }

                $punyaDefault = (bool) preg_match('/->default\(/', $block);

                if (! $punyaDefault) {
                    $offenders[] = $this->baris($content, $blockStart) . "  {$relatif}";
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Filter tanggal berikut punya fallback `?? now(...)` di query() tanpa "
            . "`->default()` yang sepadan di form-nya -- form terlihat kosong/netral "
            . "sementara query tetap diam-diam membatasi ke bulan berjalan. Rujukan "
            . "polanya CashBookResource: DatePicker `->default(now()->startOfMonth())`"
            . " / `->default(now())`, dan indicateUsing menampilkan badge cuma kalau "
            . "nilainya beda dari default:\n"
            . implode("\n", $offenders),
        );
    }

    /**
     * Potong file jadi blok-blok per `Filter::make(...)`, dari titik itu
     * sampai `Filter::make(`/`SelectFilter::make(`/`TrashedFilter::make(`
     * berikutnya atau akhir berkas -- cukup untuk menangkap satu filter
     * tanggal tanpa mem-parse AST penuh.
     *
     * @return array<int, array{0: int, 1: string}> pasangan [offset, isi blok]
     */
    protected function filterBlocks(string $content): array
    {
        $blocks = [];

        if (! preg_match_all('/(?<!Select|Trashed)Filter::make\(/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $blocks;
        }

        $starts = array_map(fn ($m) => $m[1], $matches[0]);

        if (! preg_match_all('/(?:Select|Trashed)?Filter::make\(/', $content, $allMatches, PREG_OFFSET_CAPTURE)) {
            return $blocks;
        }

        $allStarts = array_map(fn ($m) => $m[1], $allMatches[0]);
        sort($allStarts);

        foreach ($starts as $start) {
            $nextIndex = null;

            foreach ($allStarts as $candidate) {
                if ($candidate > $start) {
                    $nextIndex = $candidate;
                    break;
                }
            }

            $end = $nextIndex ?? strlen($content);
            $blocks[] = [$start, substr($content, $start, $end - $start)];
        }

        return $blocks;
    }

    /** Nomor baris di FILE ASLI (sebelum komentar dibuang), supaya tetap presisi. */
    protected function baris(string $tanpaKomentar, int $offset): string
    {
        $sebelum = substr($tanpaKomentar, 0, $offset);

        return 'baris ' . (substr_count($sebelum, "\n") + 1);
    }

    /** Isi berkas PHP tanpa komentarnya, disusun ulang dari tokennya. */
    protected function tanpaKomentar(string $isi): string
    {
        $hasil = '';

        foreach (@token_get_all($isi) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $hasil .= str_repeat(' ', strlen($token[1]));

                continue;
            }

            $hasil .= is_array($token) ? $token[1] : $token;
        }

        return $hasil;
    }

    /** @return \Generator<string> */
    protected function filamentPhpFiles(): \Generator
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    protected function relative(string $path): string
    {
        return str_replace(['\\', base_path() . '/'], ['/', ''], $path);
    }
}
