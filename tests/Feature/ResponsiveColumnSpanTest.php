<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `columnSpan()` dan `columns()` mengisi kunci breakpoint yang BERBEDA untuk
 * angka polos yang sama, dan itu jebakan yang sudah menjerat lima Resource.
 *
 * `Concerns\HasColumns::columns(7)` (angka polos, dipakai Section/Repeater
 * untuk menentukan jumlah kolom WADAHNYA) diam-diam menjadi `['lg' => 7]` --
 * 1 kolom di breakpoint `default`, 7 kolom mulai `lg`. Sebaliknya,
 * `Concerns\CanSpanColumns::columnSpan(4)` (angka polos, dipakai ANAK untuk
 * menentukan lebarnya sendiri) menjadi `['default' => 4]` -- 4 kolom di
 * SEMUA breakpoint, termasuk `default` yang wadahnya cuma 1.
 *
 * Akibatnya: field yang minta span N (N > 1) tanpa breakpoint array selalu
 * bentrok dengan wadah bare-`columns()`-nya di layar sempit -- grid CSS
 * terpaksa membuat kolom TERSISAT untuk memuaskan permintaan itu, dan
 * hasilnya field-field di baris yang sama gepeng/bertabrakan. Ditemukan
 * pertama di Full Address (`CustomerResource`, #350), lalu ternyata sudah
 * menular ke CarcassResource (dikira sebabnya `columns()`, ternyata
 * `columnSpan()`), dan ke lima Resource lain yang belum pernah disentuh
 * sama sekali. #354 menyapu semuanya sekaligus.
 *
 * **Perbaikan yang benar:** `columnSpan(['default' => 1, 'lg' => N])` --
 * `default` mengikuti jumlah kolom wadahnya sendiri di breakpoint itu (1,
 * kecuali wadahnya memang ditulis array sejak awal seperti QcReportResource
 * yang sengaja memakai `md`), bukan disamakan dengan N di breakpoint lebar.
 *
 * Test ini memindai SELURUH `app/Filament/`, bukan cuma modul yang sudah
 * ketahuan -- pola yang sama gampang ditulis ulang di modul berikutnya
 * tanpa ada yang sadar, persis seperti riwayatnya sejauh ini.
 */
class ResponsiveColumnSpanTest extends TestCase
{
    /**
     * `columnSpan(N)` angka polos (N > 1) -- selalu span N di SEMUA
     * breakpoint termasuk `default`, tak peduli wadahnya berapa kolom.
     */
    protected function bareIntOffenders(string $content, string $file): array
    {
        $offenders = [];

        if (preg_match_all('/->columnSpan\(\s*([2-9]|[1-9][0-9]+)\s*\)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $offenders[] = $this->baris($content, $match[1]) . "  {$file}  {$match[0]}";
            }
        }

        return $offenders;
    }

    /**
     * `columnSpan(['default' => N, ...])` dengan N > 1 -- kunci `default`
     * ditulis eksplisit tapi nilainya menyamakan diri dengan kolom desktop,
     * bukan dengan kolom wadahnya sendiri di breakpoint `default`.
     */
    protected function arrayDefaultOffenders(string $content, string $file): array
    {
        $offenders = [];

        if (preg_match_all("/->columnSpan\(\[\s*'default'\s*=>\s*([2-9]|[1-9][0-9]+)\b/", $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $offenders[] = $this->baris($content, $match[1]) . "  {$file}  {$match[0]}...";
            }
        }

        return $offenders;
    }

    /**
     * `columnSpan(fn (...) => ...)` -- closure ditaruh LANGSUNG, tidak
     * dibungkus array breakpoint. Apa pun yang dikembalikan closure itu
     * jatuh ke kunci `default` juga, persis seperti angka polos.
     */
    protected function bareClosureOffenders(string $content, string $file): array
    {
        $offenders = [];

        if (preg_match_all('/->columnSpan\(\s*fn\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $offenders[] = $this->baris($content, $match[1]) . "  {$file}  {$match[0]}...";
            }
        }

        return $offenders;
    }

    /** @test */
    public function no_columnspan_defaults_to_more_than_one_column_below_its_container_breakpoint()
    {
        $offenders = [];

        foreach ($this->filamentPhpFiles() as $file) {
            $content = $this->tanpaKomentar(file_get_contents($file));
            $relatif = $this->relative($file);

            $offenders = array_merge(
                $offenders,
                $this->bareIntOffenders($content, $relatif),
                $this->arrayDefaultOffenders($content, $relatif),
                $this->bareClosureOffenders($content, $relatif),
            );
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "columnSpan() berikut menyamakan lebar breakpoint `default` dengan lebar "
            . "desktop, padahal wadah `columns()`-nya cuma 1 kolom di situ. Bungkus "
            . "jadi columnSpan(['default' => 1, 'lg' => N]) -- 'lg' kalau wadahnya "
            . "columns() angka polos, atau breakpoint yang SAMA dengan yang dipakai "
            . "wadahnya kalau wadahnya sudah array sejak awal:\n"
            . implode("\n", $offenders),
        );
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
                // Diganti spasi sepanjang isinya, bukan dibuang, supaya
                // offset karakter (dipakai menghitung nomor baris) tetap
                // menunjuk posisi yang sama seperti berkas aslinya.
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
