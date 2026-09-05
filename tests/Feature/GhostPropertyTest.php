<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Properti yang dibaca tetapi tidak pernah ada.
 *
 * Eloquent menjawab `null` untuk properti yang bukan kolom, bukan cast, bukan
 * accessor, dan bukan relasi. Tidak ada galat. Tidak ada peringatan. Yang
 * terjadi cuma: angkanya nol, atau kotaknya kosong.
 *
 * Tiga sudah ketahuan, dan ketiganya berbiaya uang:
 *
 *  - `has_tax` -- kolomnya `is_tax_11`. PPN pembelian daging selalu nol,
 *    sementara hutangnya menghitung PPN dengan benar. Dua angka untuk
 *    transaksi yang sama, selisihnya sebelas persen.
 *  - `received_box` / `received_weight` -- kolomnya `box` dan `weight`.
 *    Ekspor Excel Surat Jalan mengosongkan kedua kolomnya dan PDF-nya
 *    mencetak 0,00, sementara LAYARNYA benar karena memakai jalur berbeda.
 *  - `item_name` -- bukan kolom sama sekali. Kolom "Product / Charge" di
 *    berkas Excel Invoice selalu kosong.
 *
 * Ketiganya punya bentuk yang sama: layarnya benar, berkas yang dikirim ke
 * luar salah. Tidak ada yang mengeluh karena tidak ada yang memeriksa berkas
 * ekspor sebaris demi sebaris.
 *
 * Pemindai ini sengaja KASAR: ia tidak tahu variabelnya model yang mana, jadi
 * ia menerima properti apa pun yang merupakan kolom di tabel MANA PUN. Ia
 * akan melewatkan salah-tabel, tetapi ia menangkap yang tidak ada di mana
 * pun -- dan justru itu bentuk yang selama ini lolos.
 */
class GhostPropertyTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_property_is_read_that_exists_nowhere(): void
    {
        $dikenal = $this->namaYangDikenal();

        $hantu = [];

        foreach ($this->berkasSumber() as $nama => $isi) {
            // Hanya yang berbentuk snake_case: ciri nama kolom, bukan
            // pemanggilan metode. `(?!\s*\()` membuang pemanggilan.
            preg_match_all('/->([a-z][a-z0-9]*(?:_[a-z0-9]+)+)\b(?!\s*\()/', $isi, $cocok);

            foreach (array_unique($cocok[1]) as $properti) {
                if (isset($dikenal[$properti])) {
                    continue;
                }

                if (in_array($properti, $this->memangBukanKolom(), true)) {
                    continue;
                }

                $hantu[$properti][$nama] = true;
            }
        }

        ksort($hantu);

        $laporan = [];

        foreach ($hantu as $properti => $berkas) {
            $laporan[] = $properti.'   <- '.implode(', ', array_map('basename', array_keys($berkas)));
        }

        $this->assertSame(
            [],
            $laporan,
            "Properti berikut dibaca tetapi bukan kolom di tabel mana pun, bukan cast, bukan "
            ."accessor, dan bukan relasi. Eloquent menjawab null tanpa galat -- angkanya nol "
            ."atau kotaknya kosong:\n".implode("\n", $laporan),
        );
    }

    /**
     * Nama yang sah: kolom tabel mana pun, cast, append, dan metode model.
     *
     * @return array<string, true>
     */
    private function namaYangDikenal(): array
    {
        $dikenal = [];

        // Tampilan basis data ikut dihitung: `invoice_reconciliation_view`
        // adalah VIEW, dan kolom-kolomnya sama sahnya dengan kolom tabel.
        foreach ([...Schema::getTables(), ...Schema::getViews()] as $tabel) {
            foreach (Schema::getColumnListing($tabel['name']) as $kolom) {
                $dikenal[$kolom] = true;
            }
        }

        foreach (glob(app_path('Models/*.php')) as $berkas) {
            $kelas = 'App\\Models\\'.basename($berkas, '.php');

            if (! class_exists($kelas)) {
                continue;
            }

            foreach (get_class_methods($kelas) as $metode) {
                $dikenal[$metode] = true;
                $dikenal[Str::snake($metode)] = true;

                // `getItemNameAttribute` menyediakan `item_name`.
                if (preg_match('/^get(\w+)Attribute$/', $metode, $c)) {
                    $dikenal[Str::snake($c[1])] = true;
                }
            }

            $model = new $kelas;

            foreach (array_keys($model->getCasts()) as $cast) {
                $dikenal[$cast] = true;
            }

            foreach ($model->getAppends() as $append) {
                $dikenal[$append] = true;
            }
        }

        return $dikenal;
    }

    /**
     * Nama yang memang bukan kolom, dengan alasannya.
     *
     * Semuanya alias yang lahir di dalam query itu sendiri -- `withCount`,
     * `withSum`, `selectRaw`, atau kolom hasil `join`. Bukan daftar toleransi:
     * masing-masing benar-benar ADA pada baris yang dikembalikan query-nya,
     * hanya tidak ada di definisi tabel mana pun.
     *
     * @return list<string>
     */
    private function memangBukanKolom(): array
    {
        return [
            // Bawaan Laravel: `withCount('items')`.
            'items_count',

            // Alias `withSum` di BankAccountResource.
            'transactions_in_sum',
            'transactions_out_sum',

            // Alias `selectRaw` di ringkasan dan berkas cetak.
            'total_in',
            'total_out',
            'total_pcs',
            'total_carton',
            'total_subtotal',
            'item_price',

            // Kolom hasil join di Stock Overview dan mutasi.
            'category_name',
            'grade_name',
            'warehouse_name',
            'product_name',

            // Alias hitungan di daftar piutang.
            'deposit_received',
            'deposit_used',
            'due_soon_count',
            'overdue_count',
            'total_receivable_count',
        ];
    }

    /**
     * Berkas PHP dan Blade, komentarnya sudah dibuang.
     *
     * @return array<string, string>
     */
    private function berkasSumber(): array
    {
        $hasil = [];

        foreach (['app', 'resources/views'] as $akar) {
            $berkas = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($akar))
            );

            foreach ($berkas as $satu) {
                if (! $satu->isFile() || $satu->getExtension() !== 'php') {
                    continue;
                }

                $jalur = str_replace('\\', '/', $satu->getPathname());
                $nama = str_replace(str_replace('\\', '/', base_path()).'/', '', $jalur);

                $hasil[$nama] = $this->tanpaKomentar(
                    file_get_contents($satu->getPathname()),
                    str_ends_with($nama, '.blade.php'),
                );
            }
        }

        return $hasil;
    }

    /** Komentar dibuang supaya keterangannya tidak ikut tertuduh. */
    private function tanpaKomentar(string $isi, bool $blade): string
    {
        if ($blade) {
            $isi = preg_replace(['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s'], ' ', $isi);
        }

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
