<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga issue #512: dropdown ke master yang TERUS BERTAMBAH -- Product,
 * Material, Supplier, Customer -- tidak boleh memuat seluruh
 * pilihan sekali saat halaman dibuka.
 *
 * Dropdown dikenali dari MODEL yang disentuhnya atau dari nama fieldnya
 * (`product_id`, `material_id`, `supplier_id`, `customer_id`, ...), bukan dari
 * nama field saja: `->options(Product::pluck(...))` pada field bernama lain tetap
 * dropdown ke Product.
 *
 * Yang dilarang:
 *
 *   ->preload()                                   (memuat semuanya sekaligus)
 *   ->options( ... pluck( ...) )                  (idem; `searchable()` lalu hanya
 *                                                  menyaring di browser)
 *
 * Akibatnya yang dirasakan Owner: item yang dibuat di tab lain tidak muncul di
 * form yang sudah terbuka tanpa me-refresh halaman, dan me-refresh berarti
 * mengisi ulang form dari awal. Gantinya `App\Filament\Support\MasterSelect`
 * (pencarian ke server, dengan 50 pilihan pertama saat dibuka).
 * `->relationship(...)->searchable()` TANPA preload sudah mencari ke server,
 * jadi diizinkan; tetapi `->relationship(...)` TANPA `searchable()` memuat seluruh
 * tabel sekaligus, jadi dilarang.
 *
 * Master KECIL yang jarang bertambah (gudang, grade, satuan, kategori, grup
 * customer, ...) tidak dijaga di sini. Customer Group hanya ada di form master
 * Customer (tidak di form transaksi) dan isinya segelintir, jadi dikecualikan.
 *
 * DAFTAR PENGECUALIAN di bawah adalah pekerjaan yang MASIH MENUNGGU, kelompok
 * demi kelompok -- bukan izin. Tiap berkas yang selesai dikeluarkan dari daftar;
 * daftar yang menyusut itu sendiri yang menunjukkan sisa pekerjaannya.
 */
class MasterDropdownGuardTest extends TestCase
{
    /** Nama field yang menunjuk ke master yang terus bertambah. */
    private const FIELDS = ['product_id', 'product_ids', 'material_id', 'supplier_id', 'customer_id', 'parent_id'];

    /**
     * Berkas yang masih memakai pola lama, menunggu kelompoknya (issue #512).
     * Kunci: path relatif dari `app/Filament/`. Nilai: kelompok yang akan
     * mengerjakannya.
     *
     * @var array<string, string>
     */
    private const MENUNGGU = [];

    public function test_dropdowns_to_growing_masters_search_the_server(): void
    {
        $pelanggaran = [];

        foreach ($this->berkas() as $relatif => $isi) {
            if (array_key_exists($relatif, self::MENUNGGU)) {
                continue;
            }

            foreach ($this->pelanggaranDi($isi) as [$field, $baris, $sebab]) {
                $pelanggaran[] = "{$relatif}:{$baris}  {$field}: {$sebab}";
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

        // Daftar kosong = semua kelompok selesai; tetap dihitung sebagai pemeriksaan.
        $this->addToAssertionCount(1);

        foreach (self::MENUNGGU as $relatif => $kelompok) {
            $this->assertArrayHasKey($relatif, $berkas, "{$relatif} ada di daftar menunggu tetapi berkasnya tidak ada.");

            $this->assertNotEmpty(
                $this->pelanggaranDi($berkas[$relatif]),
                "{$relatif} sudah bersih (kelompok: {$kelompok}); keluarkan dari daftar menunggu.",
            );
        }
    }

    /**
     * Pelanggaran dalam sebuah berkas.
     *
     * @return array<int, array{string, int, string}>  [nama field, nomor baris, sebab]
     */
    private function pelanggaranDi(string $kode): array
    {
        $hasil = [];

        foreach ($this->jendela($kode) as [$field, $baris, $jendela]) {
            if (str_contains($jendela, 'MasterSelect::') || str_contains($jendela, 'getSearchResultsUsing')) {
                continue;
            }

            if (str_contains($jendela, '->preload()')) {
                $hasil[] = [$field, $baris, '->preload() memuat semua pilihan sekali saat halaman dibuka'];
            }

            if (preg_match('/->options\(.*?pluck\(/s', $jendela)) {
                $hasil[] = [$field, $baris, '->options(... pluck()) memuat semua pilihan sekali saat halaman dibuka'];
            }

            // Dropdown relasi TANPA searchable() memuat seluruh tabel sekaligus.
            if (str_contains($jendela, '->relationship(') && ! str_contains($jendela, '->searchable()')) {
                $hasil[] = [$field, $baris, '->relationship() tanpa ->searchable() memuat semua pilihan sekali saat halaman dibuka'];
            }
        }

        return $hasil;
    }

    /**
     * @return array<string, string>  path relatif dari app/Filament (garis miring) => isi tanpa komentar
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
     * Tiap Select/SelectFilter yang menyentuh master yang terus bertambah, beserta
     * "jendela"-nya: teks dari `Select::make('xxx')` sampai pembuatan komponen
     * berikutnya. Dikenali dari nama field ATAU dari model yang disebut di
     * jendelanya (`Product::`, `Material::`, `Supplier::`, `Customer::`,
     * `CustomerGroup::`, atau `relationship('product'...)`).
     *
     * @return array<int, array{string, int, string}>  [nama field, nomor baris, jendela]
     */
    private function jendela(string $kode): array
    {
        $hasil = [];

        preg_match_all("/(Select|SelectFilter)::make\('([^']+)'\)/", $kode, $cocok, PREG_OFFSET_CAPTURE);

        foreach ($cocok[0] as $i => [$teks, $offset]) {
            $akhir = strlen($kode);

            if (preg_match('/::make\(/', $kode, $berikut, PREG_OFFSET_CAPTURE, $offset + strlen($teks))) {
                $akhir = $berikut[0][1];
            }

            $jendela = substr($kode, $offset, min($akhir - $offset, 2500));
            $nama = $cocok[2][$i][0];

            $menyentuh = in_array($nama, self::FIELDS, true)
                || preg_match('/\b(Product|Material|Supplier|Customer)::|relationship\(\s*\'(product|material|supplier|customer)\'/', $jendela);

            if (! $menyentuh) {
                continue;
            }

            $hasil[] = [$nama, substr_count(substr($kode, 0, $offset), "\n") + 1, $jendela];
        }

        return $hasil;
    }
}
