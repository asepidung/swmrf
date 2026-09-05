<?php

namespace Tests\Feature;

use App\Models\BeefStockMovement;
use App\Models\MaterialStockMovement;
use Tests\TestCase;

/**
 * Jenis pergerakan stok: daftar di kode harus cocok dengan yang benar-benar
 * ditulis aplikasi.
 *
 * Bentuk kegagalannya sudah ditambal tiga kali di modul lain -- Invoice,
 * Sales Order, dan Tally -- dan muncul lagi di sini, di dua sisi sekaligus:
 * sebuah daftar yang ditulis tangan, lalu ditinggalkan diam-diam oleh kode
 * yang menulis jenis baru.
 *
 * Yang hilang tidak pernah terlihat sebagai error. Penyaringnya hanya
 * kekurangan pilihan, dan badge-nya hanya berwarna abu-abu. Yang berlebih
 * lebih halus lagi: sebuah pilihan hantu yang selalu mengembalikan daftar
 * kosong, sehingga terbaca sebagai "memang tidak ada datanya".
 */
class StockMovementTypesTest extends TestCase
{
    /**
     * Setiap jenis yang ditulis kode harus terdaftar, dan setiap yang
     * terdaftar harus benar-benar ditulis.
     *
     * Dua arah, karena keduanya pernah salah: daging menulis 19 jenis tetapi
     * mendaftarkan 10, dan `TALLY_REVERT` terdaftar tanpa pernah ditulis.
     */
    public function test_the_beef_movement_types_match_what_the_code_writes(): void
    {
        $ditulis = [];

        foreach ($this->berkasPhp() as $berkas) {
            preg_match_all(
                "/'transaction_type'\s*=>\s*'([A-Z_]+)'/",
                $this->tanpaKomentar(file_get_contents($berkas)),
                $cocok,
            );

            foreach ($cocok[1] as $jenis) {
                $ditulis[$jenis] = true;
            }
        }

        // Jenis milik material ditulis lewat argumen, bukan lewat kunci ini,
        // kecuali satu: penyesuaian opname material memakai nama yang sama
        // dengan salah satu jenis daging.
        unset($ditulis['STOCK_TAKE_ADJUSTMENT']);

        $terdaftar = array_keys(BeefStockMovement::TYPES);
        $ditulis = array_keys($ditulis);

        sort($terdaftar);
        sort($ditulis);

        $hilang = array_values(array_diff($ditulis, $terdaftar));
        $hantu = array_values(array_diff($terdaftar, $ditulis));

        $this->assertSame(
            [],
            $hilang,
            "Jenis pergerakan berikut ditulis kode tetapi tidak terdaftar, jadi "
            ."tidak bisa disaring dan badge-nya abu-abu:\n".implode("\n", $hilang),
        );

        $this->assertSame(
            [],
            $hantu,
            "Jenis pergerakan berikut terdaftar tetapi TIDAK PERNAH ditulis kode "
            ."mana pun -- memilihnya selalu menghasilkan daftar kosong:\n".implode("\n", $hantu),
        );
    }

    /** Sisi material: jenisnya dikirim sebagai argumen ke `adjustStock()`. */
    public function test_the_material_movement_types_match_what_the_code_writes(): void
    {
        $ditulis = [];

        foreach ($this->berkasPhp() as $berkas) {
            preg_match_all(
                '/adjustStock\(\s*[^;]*?,\s*[^;]*?,\s*\'([A-Z_ ]+)\'/s',
                $this->tanpaKomentar(file_get_contents($berkas)),
                $cocok,
            );

            foreach ($cocok[1] as $jenis) {
                $ditulis[$jenis] = true;
            }
        }

        $terdaftar = array_keys(MaterialStockMovement::TYPES);
        $ditulis = array_keys($ditulis);

        sort($terdaftar);
        sort($ditulis);

        $this->assertSame(
            [],
            array_values(array_diff($ditulis, $terdaftar)),
            "Jenis pergerakan material berikut ditulis kode tetapi tidak terdaftar:\n"
            .implode("\n", array_diff($ditulis, $terdaftar)),
        );

        $this->assertSame(
            [],
            array_values(array_diff($terdaftar, $ditulis)),
            "Jenis pergerakan material berikut terdaftar tetapi tidak pernah ditulis:\n"
            .implode("\n", array_diff($terdaftar, $ditulis)),
        );
    }

    /**
     * Penghapusan stok berwarna MERAH, bukan hijau.
     *
     * `VOID_STOCK` diberi warna hijau sebelum ini -- penghapusan stok manual,
     * barang KELUAR, ditampilkan dengan warna yang berarti masuk. Itu satu
     * satunya aksi manual di aplikasi ini yang menghancurkan baris stok.
     */
    public function test_a_deletion_is_never_shown_as_an_inflow(): void
    {
        $this->assertSame('danger', BeefStockMovement::typeColor('VOID_STOCK'));
        $this->assertSame('danger', BeefStockMovement::typeColor('STOCK_TAKE_LOSS'));
        $this->assertSame('danger', BeefStockMovement::typeColor('TALLY'));

        $this->assertSame('success', BeefStockMovement::typeColor('FOUND_ITEM'));
        $this->assertSame('success', BeefStockMovement::typeColor('MUTATION_IN'));

        $this->assertSame('gray', BeefStockMovement::typeColor('TIDAK_DIKENAL'));
        $this->assertSame('gray', BeefStockMovement::typeColor(null));
    }

    /** Penyaringnya menawarkan SELURUHNYA, bukan sebagian. */
    public function test_the_filters_offer_every_registered_type(): void
    {
        $this->assertSame(
            array_keys(BeefStockMovement::TYPES),
            array_keys(BeefStockMovement::typeOptions()),
        );

        $this->assertSame(
            array_keys(MaterialStockMovement::TYPES),
            array_keys(MaterialStockMovement::typeOptions()),
        );

        $berkas = file_get_contents(base_path(
            'app/Filament/Admin/Resources/BeefStockMovementResource.php'
        ));

        $this->assertStringContainsString('BeefStockMovement::typeOptions()', $berkas);
        $this->assertStringNotContainsString("'TALLY_REVERT'", $berkas);
    }

    /**
     * Kolom `condition` menyimpan grade_id, bukan teks.
     *
     * `BeefStockMovement::grade()` menyatakannya, dan delapan belas dari
     * sembilan belas penulis memang begitu. Yang satu menulis `'GOOD'`,
     * sehingga kolom Grade di daftar pergerakan kosong untuk setiap barang
     * temuan.
     */
    public function test_every_movement_stores_a_grade_id_in_condition(): void
    {
        $pelanggar = [];

        foreach ($this->berkasPhp() as $berkas) {
            preg_match_all(
                "/'condition'\s*=>\s*'([^']+)'/",
                $this->tanpaKomentar(file_get_contents($berkas)),
                $cocok,
                PREG_SET_ORDER,
            );

            foreach ($cocok as $satu) {
                $pelanggar[] = $this->relatif($berkas)." -> 'condition' => '{$satu[1]}'";
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Kolom `condition` menyimpan grade_id, tetapi berikut menulis teks:\n"
            .implode("\n", $pelanggar),
        );
    }

    /** @return \Generator<string> */
    private function berkasPhp(): \Generator
    {
        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'))
        );

        foreach ($berkas as $satu) {
            if ($satu->isFile() && $satu->getExtension() === 'php') {
                yield $satu->getPathname();
            }
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

    private function relatif(string $jalur): string
    {
        return str_replace(['\\', base_path().'/'], ['/', ''], $jalur);
    }
}
