<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PPN 11% hanya boleh punya SATU aturan.
 *
 * Kolom yang menentukannya bernama `is_tax_11`, dan hanya itu yang ada di
 * tabel `suppliers`. Sisi pembelian DAGING menanyakan `has_tax` -- kolom
 * yang tidak pernah ada, tanpa accessor, tanpa migrasi.
 *
 * Eloquent menjawab `null` untuk kolom yang tidak ada. Tidak ada galat, tidak
 * ada peringatan: jawabannya cuma selalu "tidak memungut". Akibatnya setiap
 * permintaan pembelian daging ke pemasok PKP tersimpan dengan PPN nol --
 * sementara hutangnya di `Payable` menghitung PPN dengan benar dari kolom
 * yang sungguhan.
 *
 * Dua angka untuk transaksi yang sama, selisihnya persis sebelas persen, dan
 * tidak ada satu pun gejala yang memberitahu. Empat tempat menanyakannya
 * dengan nama yang salah, dan keempatnya adalah salinan dari sisi material
 * yang menanyakannya dengan benar.
 *
 * Basis data lokal maupun server diperiksa saat perbaikannya dibuat: belum
 * ada satu pun permintaan atau PO daging ke pemasok PKP, jadi tidak ada
 * angka lama yang perlu dibetulkan.
 */
class ValueAddedTaxTest extends TestCase
{
    use RefreshDatabase;

    private function pemasok(bool $pkp): Supplier
    {
        return Supplier::create([
            'name' => $pkp ? 'PT PKP' : 'CV Non PKP',
            'address' => 'Bogor',
            'pic' => 'Doni',
            'top_days' => 30,
            'is_tax_11' => $pkp,
            'is_active' => true,
        ]);
    }

    /** Pemasok PKP memungut sebelas persen. */
    public function test_a_taxable_supplier_charges_eleven_percent(): void
    {
        $this->assertEqualsWithDelta(
            1_100_000.0,
            $this->pemasok(true)->ppnAtas(10_000_000),
            0.01,
        );
    }

    /** Yang bukan PKP tidak memungut apa pun. */
    public function test_a_non_taxable_supplier_charges_nothing(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->pemasok(false)->ppnAtas(10_000_000), 0.01);
    }

    /**
     * Angka yang datang sebagai TEKS tetap dihitung.
     *
     * MySQL mengembalikan `decimal` sebagai string, jadi jumlah subtotal
     * sampai ke sini berbentuk `"10000000.00"`, bukan angka.
     */
    public function test_a_decimal_that_arrives_as_text_is_still_counted(): void
    {
        $this->assertEqualsWithDelta(
            1_100_000.0,
            $this->pemasok(true)->ppnAtas('10000000.00'),
            0.01,
        );
    }

    /**
     * Tidak ada lagi yang menanyakan `has_tax`.
     *
     * Kolomnya tidak pernah ada. Yang menanyakannya tidak akan pernah
     * mendapat jawaban selain "tidak".
     */
    public function test_nobody_asks_for_a_column_that_does_not_exist(): void
    {
        $this->assertNotContains(
            'has_tax',
            Supplier::query()->getConnection()->getSchemaBuilder()->getColumnListing('suppliers'),
            'Kolom `has_tax` ternyata ADA sekarang -- penjelasan di berkas ini perlu ditinjau ulang.',
        );

        $pelanggar = [];

        foreach ($this->berkasSumber() as $berkas => $isi) {
            if (str_contains($isi, 'has_tax')) {
                $pelanggar[] = $berkas;
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Berkas berikut menanyakan `has_tax`, kolom yang tidak pernah ada. Jawabannya "
            ."selalu null, jadi PPN-nya selalu nol tanpa galat apa pun:\n".implode("\n", $pelanggar),
        );
    }

    /**
     * Tarifnya hanya ditulis di satu tempat.
     *
     * Sepuluh tempat dulu menuliskan `0.11` sendiri-sendiri. Selama tarifnya
     * tidak berubah semuanya kebetulan sama; begitu berubah, yang terlewat
     * tidak akan mengeluh sedikit pun -- ia cuma menghasilkan angka lain.
     */
    public function test_the_tax_rate_is_written_in_one_place_only(): void
    {
        $pelanggar = [];

        foreach ($this->berkasSumber() as $berkas => $isi) {
            if ($berkas === 'app/Models/Supplier.php') {
                continue;
            }

            if (preg_match('/(?<![\d.])[01]\.11(?![\d])/', $isi)) {
                $pelanggar[] = $berkas;
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Berkas berikut menuliskan tarif PPN sendiri. Pakai `Supplier::ppnAtas()`:\n"
            .implode("\n", $pelanggar),
        );
    }

    /**
     * Berkas PHP dan Blade yang ikut diperiksa, komentarnya sudah dibuang.
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

                $isi = file_get_contents($satu->getPathname());

                // Lebar kolom tabel cetak kebetulan berbunyi sama: `11%`.
                // Yang dicari tarif, bukan tata letak.
                $isi = preg_replace('/width\s*[:=]\s*["\']?\s*\d+%/', ' ', $isi);

                $hasil[$nama] = $this->tanpaKomentar($isi, str_ends_with($nama, '.blade.php'));
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
