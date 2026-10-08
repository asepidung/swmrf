<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga issue #512: dropdown ke master yang TERUS BERTAMBAH -- Product,
 * Material, Supplier, Customer -- tidak boleh memuat seluruh pilihan sekali saat
 * halaman dibuka.
 *
 * Polanya yang dilarang untuk field `product_id` / `material_id` /
 * `supplier_id` / `customer_id`:
 *
 *   ->preload()                                   (memuat semuanya sekaligus)
 *   ->options(fn () => Model::...->pluck(...))    (idem; `searchable()` lalu hanya
 *                                                  menyaring di browser)
 *
 * Akibatnya yang dirasakan Owner: item yang dibuat di tab lain tidak muncul di
 * form yang sudah terbuka tanpa me-refresh halaman, dan me-refresh berarti
 * mengisi ulang form dari awal. Gantinya `App\Filament\Support\MasterSelect`
 * (pencarian ke server).
 *
 * Master KECIL yang jarang bertambah (gudang, grade, satuan, kategori, ...)
 * tidak dijaga di sini.
 *
 * DAFTAR PENGECUALIAN di bawah adalah pekerjaan yang MASIH MENUNGGU, kelompok
 * demi kelompok -- bukan izin. Tiap berkas yang selesai dikeluarkan dari daftar;
 * daftar yang menyusut itu sendiri yang menunjukkan sisa pekerjaannya.
 */
class MasterDropdownGuardTest extends TestCase
{
    /** Nama field yang menunjuk ke master yang terus bertambah. */
    private const FIELDS = ['product_id', 'material_id', 'supplier_id', 'customer_id'];

    /**
     * Berkas yang masih memakai pola lama, menunggu kelompoknya (issue #512).
     * Kunci: path relatif dari `app/Filament/`. Nilai: kelompok yang akan
     * mengerjakannya.
     *
     * @var array<string, string>
     */
    private const MENUNGGU = [
        // Kelompok 2: PO dan GR
        'Admin/Resources/PurchaseMaterialResource.php' => 'kelompok 2: PO dan GR',
        'Admin/Resources/PurchaseProductResource.php' => 'kelompok 2: PO dan GR',
        'Admin/Resources/GoodsReceiptProductResource/Pages/LabelingGoodsReceiptProduct.php' => 'kelompok 2: PO dan GR',

        // Kelompok 3: Sales Order, Price List, Sales Return Plan, dan yang sejenis
        'Admin/Resources/SalesOrderResource.php' => 'kelompok 3: penjualan',
        'Admin/Resources/PriceListResource.php' => 'kelompok 3: penjualan',
        'Admin/Resources/SalesReturnPlanResource.php' => 'kelompok 3: penjualan',
        'Admin/Resources/SalesReturnResource.php' => 'kelompok 3: penjualan',
        'Admin/Resources/SalesReturnResource/Pages/InputReturnItems.php' => 'kelompok 3: penjualan',
        'Admin/Resources/InvoiceResource.php' => 'kelompok 3: penjualan',
        'Admin/Resources/DeliveryOrderResource.php' => 'kelompok 3: penjualan',
        'Admin/Resources/DeliveryOrderReceiptResource.php' => 'kelompok 3: penjualan',
        'Admin/Resources/DeliveryPlanResource.php' => 'kelompok 3: penjualan',
        'Admin/Resources/TallyResource.php' => 'kelompok 3: penjualan',

        // Kelompok 4: sisanya
        'Admin/Resources/BoningResource/Pages/LabelingBoning.php' => 'kelompok 4: sisanya',
        'Admin/Resources/RepackResource/Pages/InputHasilRepack.php' => 'kelompok 4: sisanya',
        'Clusters/MaterialsStock/Resources/MaterialFindingResource.php' => 'kelompok 4: sisanya',
        'Concerns/ShowsBomMaterialUsage.php' => 'kelompok 4: sisanya (halaman Pemakaian Material, bahan terbuang)',
    ];

    public function test_dropdowns_to_growing_masters_search_the_server(): void
    {
        $pelanggaran = [];

        foreach ($this->berkas() as $relatif => $isi) {
            if (array_key_exists($relatif, self::MENUNGGU)) {
                continue;
            }

            foreach ($this->jendela($isi) as [$field, $baris, $jendela]) {
                if (str_contains($jendela, 'MasterSelect::') || str_contains($jendela, 'getSearchResultsUsing')) {
                    continue;
                }

                if (str_contains($jendela, '->preload()')) {
                    $pelanggaran[] = "{$relatif}:{$baris}  {$field}: ->preload() memuat semua pilihan sekali saat halaman dibuka";
                }

                if (preg_match('/->options\(\s*(fn|function)[^;]*pluck\(/s', $jendela)) {
                    $pelanggaran[] = "{$relatif}:{$baris}  {$field}: ->options(... pluck()) memuat semua pilihan sekali saat halaman dibuka";
                }
            }
        }

        $this->assertSame(
            [],
            $pelanggaran,
            "Dropdown berikut memuat seluruh master sekali saat halaman dibuka. Item yang dibuat di tab lain tidak akan muncul tanpa me-refresh halaman -- dan me-refresh berarti mengisi ulang form dari awal. Pakai App\\Filament\\Support\\MasterSelect (pencarian ke server):\n".implode("\n", $pelanggaran),
        );
    }

    /**
     * Daftar menunggu tidak boleh berisi berkas yang sudah bersih -- kalau
     * sudah selesai, keluarkan dari daftar supaya penjaga ikut menjaganya.
     */
    public function test_the_waiting_list_only_holds_files_that_still_use_the_old_pattern(): void
    {
        $berkas = $this->berkas();

        foreach (self::MENUNGGU as $relatif => $kelompok) {
            $this->assertArrayHasKey($relatif, $berkas, "{$relatif} ada di daftar menunggu tetapi berkasnya tidak ada.");

            $masihLama = false;

            foreach ($this->jendela($berkas[$relatif]) as [, , $jendela]) {
                if (str_contains($jendela, 'MasterSelect::') || str_contains($jendela, 'getSearchResultsUsing')) {
                    continue;
                }

                if (str_contains($jendela, '->preload()') || preg_match('/->options\(\s*(fn|function)[^;]*pluck\(/s', $jendela)) {
                    $masihLama = true;
                }
            }

            $this->assertTrue($masihLama, "{$relatif} sudah bersih (kelompok: {$kelompok}); keluarkan dari daftar menunggu.");
        }
    }

    /**
     * @return array<string, string>  path => isi tanpa komentar
     */
    private function berkas(): array
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Filament'), \FilesystemIterator::SKIP_DOTS));
        $hasil = [];

        foreach ($iterator as $berkas) {
            if ($berkas->getExtension() === 'php') {
                $relatif = str_replace('\\', '/', substr($berkas->getPathname(), strlen(app_path('Filament')) + 1));
                $hasil[$relatif] = $this->tanpaKomentar(file_get_contents($berkas->getPathname()));
            }
        }

        ksort($hasil);

        return $hasil;
    }

    /**
     * Buang komentar tetapi pertahankan jumlah barisnya, supaya nomor baris pada
     * pesan gagal tetap benar. Komentar yang MENJELASKAN pola lama tidak boleh
     * dituduh memakainya.
     */
    private function tanpaKomentar(string $kode): string
    {
        $hasil = '';

        foreach (token_get_all($kode) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    $hasil .= str_repeat("\n", substr_count($token[1], "\n"));

                    continue;
                }

                $hasil .= $token[1];
            } else {
                $hasil .= $token;
            }
        }

        return $hasil;
    }

    /**
     * Tiap field yang menunjuk ke master yang terus bertambah, beserta
     * "jendela"-nya: teks dari `Select::make('xxx_id')` sampai pembuatan
     * komponen berikutnya.
     *
     * @return array<int, array{string, int, string}>  [nama field, nomor baris, jendela]
     */
    private function jendela(string $kode): array
    {
        $daftar = implode('|', self::FIELDS);
        $hasil = [];

        preg_match_all("/(Select|SelectFilter)::make\('({$daftar})'\)/", $kode, $cocok, PREG_OFFSET_CAPTURE);

        foreach ($cocok[0] as $i => [$teks, $offset]) {
            $akhir = strlen($kode);

            if (preg_match('/::make\(/', $kode, $berikut, PREG_OFFSET_CAPTURE, $offset + strlen($teks))) {
                $akhir = $berikut[0][1];
            }

            $hasil[] = [
                $cocok[2][$i][0],
                substr_count(substr($kode, 0, $offset), "\n") + 1,
                substr($kode, $offset, min($akhir - $offset, 2500)),
            ];
        }

        return $hasil;
    }
}
