<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga HPP tahap 2 (issue #522): setiap tempat yang memasukkan barcode ke
 * `beef_stocks` harus jelas dari mana nilainya datang. Berkas yang memanggil
 * `BeefStock::create(` harus memanggil `BarcodeCosts::`, atau terdaftar di bawah
 * beserta ALASAN barcodenya tidak butuh nilai baru.
 *
 * Tanpa penjaga ini, tempat baru yang melahirkan stok diam-diam melahirkan
 * barcode tanpa nilai -- dan baru ketahuan di laporan.
 */
class BarcodeCostGuardTest extends TestCase
{
    /** Berkas yang tidak perlu memanggil `BarcodeCosts::`, dan kenapa. */
    private const TIDAK_PERLU = [
        'Filament/Admin/Resources/BoningResource/Pages/LabelingBoning.php' => 'baris dibuat otomatis oleh BoningItem::created; nilainya datang saat costing dikunci',
        'Filament/Admin/Resources/RepackResource/Pages/InputBahanRepack.php' => 'bahan dikembalikan ke stok dengan barcodenya sendiri; barisnya tetap ada',
        'Models/MutationItem.php' => 'mutasi tidak menyentuh nilai (barcode sama)',
        'Filament/Admin/Resources/MutationResource/Pages/ReceiveMutation.php' => 'mutasi tidak menyentuh nilai (barcode sama)',
        'Models/TallyItem.php' => 'barang dikembalikan ke stok dengan barcodenya sendiri; barisnya tetap ada',
    ];

    /** Menunggu PR berikutnya (valuasi repack); bukan izin. */
    private const MENUNGGU = [
        'Filament/Admin/Resources/RepackResource/Pages/InputHasilRepack.php' => 'PR 3: valuasi repack',
    ];

    public function test_every_place_that_creates_stock_says_where_the_value_comes_from(): void
    {
        $pelanggaran = [];

        foreach ($this->berkas() as $relatif => $isi) {
            if (! preg_match('/BeefStock::create\(/', $isi)) {
                continue;
            }

            if (str_contains($isi, 'BarcodeCosts::')
                || array_key_exists($relatif, self::TIDAK_PERLU)
                || array_key_exists($relatif, self::MENUNGGU)) {
                continue;
            }

            $pelanggaran[] = $relatif;
        }

        $this->assertSame([], $pelanggaran, "Berkas ini membuat BeefStock tanpa menentukan nilai barcodenya (App\Support\BarcodeCosts), dan tidak terdaftar sebagai pengecualian:\n".implode("\n", $pelanggaran));
    }

    public function test_the_exception_lists_only_hold_files_that_still_create_stock(): void
    {
        $berkas = $this->berkas();

        foreach ([...array_keys(self::TIDAK_PERLU), ...array_keys(self::MENUNGGU)] as $relatif) {
            $this->assertArrayHasKey($relatif, $berkas, "{$relatif} terdaftar tetapi tidak ada.");
            $this->assertMatchesRegularExpression('/BeefStock::create\(/', $berkas[$relatif], "{$relatif} tidak lagi membuat BeefStock; keluarkan dari daftar.");
        }
    }

    /** @return array<string, string> path relatif dari app/ => isi tanpa komentar */
    private function berkas(): array
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS));
        $hasil = [];

        foreach ($iterator as $berkas) {
            if ($berkas->getExtension() !== 'php') {
                continue;
            }

            $relatif = str_replace('\\', '/', substr($berkas->getPathname(), strlen(app_path()) + 1));
            $hasil[$relatif] = $this->tanpaKomentar(file_get_contents($berkas->getPathname()));
        }

        return $hasil;
    }

    private function tanpaKomentar(string $kode): string
    {
        $hasil = '';

        foreach (token_get_all($kode) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $hasil .= $token[1];
            } else {
                $hasil .= $token;
            }
        }

        return $hasil;
    }
}
