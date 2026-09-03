<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Rentang kolom harus disebut per breakpoint, sama seperti kolomnya.
 *
 * Pemetaan Filament tidak simetris, dan ini yang merusak seluruh form di
 * layar HP:
 *
 *     ->columns(12)      // -> ['lg' => 12]      HANYA dari lg ke atas
 *     ->columnSpan(3)    // -> ['default' => 3]  SEMUA ukuran
 *
 * `Grid::columns(int)` dipetakan ke breakpoint **lg**, sementara
 * `columnSpan(int)` dipetakan ke **default**. Jadi di layar sempit gridnya
 * menjadi satu kolom sementara isinya tetap meminta rentang tiga.
 *
 * CSS tidak mengabaikan permintaan itu. Kolom eksplisitnya cuma satu, jadi
 * browser membuat kolom implisit untuk menampung rentangnya -- dan kolom
 * implisit berukuran `auto`, menyusut ke isinya. Hasilnya kotak-kotak sempit
 * berjajar di kiri yang tidak bisa dibaca maupun diisi.
 *
 * Tidak ada error, tidak ada peringatan. Di layar lebar semuanya tampak
 * benar, jadi kerusakannya hanya terlihat oleh yang membukanya dari HP.
 *
 * Aturannya: kalau `columns()` disebut sebagai angka, `columnSpan()` WAJIB
 * disebut per breakpoint -- `['default' => 'full', 'lg' => N]`.
 */
class ResponsiveColumnSpanTest extends TestCase
{
    /** @return array<string, string> berkas => isinya */
    private function filamentSources(): array
    {
        $sources = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname())]
                    = file_get_contents($file->getPathname());
            }
        }

        return $sources;
    }

    /**
     * Tidak ada rentang telanjang yang lebih dari satu kolom.
     *
     * `columnSpan(1)` aman -- satu kolom dari satu kolom tetap satu kolom.
     * `columnSpanFull()` juga aman, karena 'full' berarti seluruh baris di
     * ukuran berapa pun.
     */
    public function test_no_column_span_is_wider_than_the_grid_it_sits_in_on_a_phone(): void
    {
        $offenders = [];

        foreach ($this->filamentSources() as $name => $source) {
            preg_match_all("/->columnSpan\((\d+)\)/", $source, $matches);

            foreach ($matches[1] as $span) {
                if ((int) $span >= 2) {
                    $offenders[] = $name.' -> columnSpan('.$span.')';
                }
            }
        }

        $offenders = array_unique($offenders);
        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Rentang berikut berlaku di SEMUA ukuran layar, sementara kolom wadahnya "
            ."hanya dari lg ke atas. Di layar sempit fieldnya akan mengerut jadi kotak "
            ."yang tidak bisa dibaca maupun diisi, tanpa satu pun error.\n"
            ."Pakai ->columnSpan(['default' => 'full', 'lg' => N]).\n"
            .implode("\n", $offenders),
        );
    }

    /**
     * Baris judul repeater tidak boleh disembunyikan dengan `hidden md:grid`.
     *
     * Panel ini tidak memuat CSS hasil build aplikasi, dan `md:grid` termasuk
     * kelas yang TIDAK ada CSS-nya. Yang benar-benar berlaku hanya `hidden`,
     * jadi baris judulnya tidak pernah tampil di ukuran layar mana pun --
     * termasuk di layar lebar tempat ia justru dibutuhkan.
     *
     * Sepuluh berkas memakai pola ini dan tidak ada yang menyadarinya.
     */
    public function test_no_header_row_is_hidden_by_a_class_with_no_css(): void
    {
        $offenders = [];

        foreach ($this->filamentSources() as $name => $source) {
            if (str_contains($source, 'hidden md:grid')) {
                $offenders[] = $name;
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            'Berkas berikut menyembunyikan baris judul dengan kelas yang tidak punya CSS '
            .'di panel ini, sehingga barisnya tidak pernah tampil sama sekali. '
            .'Pakai kelas swm-wide-only.',
        );
    }

    /** Dan kelas itu benar-benar punya CSS. */
    public function test_the_wide_only_class_actually_has_css(): void
    {
        $css = file_get_contents(resource_path(
            'views/filament/admin/missing-color-utilities.blade.php'
        ));

        $this->assertStringContainsString('.swm-wide-only', $css);
        $this->assertStringContainsString('min-width: 1024px', $css);
    }
}
