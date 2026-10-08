<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga issue #512, poin 2: tombol "+" (`createOptionForm`) hanya lewat
 * `QuickCreate`, dan tidak pernah di dropdown produk, customer, supplier, atau
 * material.
 *
 * Kenapa lewat `QuickCreate`: formnya sama dengan form master aslinya, tombolnya
 * HANYA tampil bagi pemegang izin membuat master itu (dan permintaan langsung
 * tanpa izin ditolak server), nama disimpan huruf besar, dan keunikannya tidak
 * peka huruf besar/kecil. Sebelumnya sepuluh "+" ditulis sendiri-sendiri: ada
 * yang tanpa izin sama sekali, ada yang namanya hanya tampak huruf besar tetapi
 * tersimpan apa adanya.
 *
 * Kenapa tidak di produk/customer/supplier/material: fieldnya banyak (keputusan
 * Owner); alurnya membuka tab baru lalu mengetik ulang di dropdown yang mencari
 * ke server (`MasterSelect`).
 */
class QuickCreateGuardTest extends TestCase
{
    /** Field yang tidak boleh punya tombol "+". */
    private const NO_PLUS = ['product_id', 'product_ids', 'customer_id', 'supplier_id', 'material_id', 'parent_id'];

    public function test_every_plus_button_goes_through_quick_create(): void
    {
        $pelanggaran = [];

        foreach ($this->berkas() as $relatif => $isi) {
            $form = substr_count($isi, '->createOptionForm(');
            $lewatHelper = substr_count($isi, '->createOptionForm(\App\Filament\Support\QuickCreate::schema(');
            $aksi = substr_count($isi, '->createOptionAction(');
            $aksiHelper = substr_count($isi, 'QuickCreate::action(');

            if ($form !== $lewatHelper) {
                $pelanggaran[] = "{$relatif}: ada ->createOptionForm() yang tidak memakai QuickCreate::schema() -- bentuk formnya bisa menyimpang dari form master aslinya";
            }

            if ($form !== $aksi || $form !== $aksiHelper) {
                $pelanggaran[] = "{$relatif}: setiap createOptionForm() harus dipasangkan dengan ->createOptionAction(QuickCreate::action(...)); tanpa itu tombol tampil untuk siapa pun, tanpa memeriksa izin";
            }
        }

        $this->assertSame([], $pelanggaran, "Tombol + harus lewat App\\Filament\\Support\\QuickCreate:\n".implode("\n", $pelanggaran));
    }

    public function test_product_customer_supplier_and_material_dropdowns_have_no_plus_button(): void
    {
        $pelanggaran = [];

        foreach ($this->berkas() as $relatif => $isi) {
            preg_match_all("/(Select|SelectFilter)::make\('([^']+)'\)/", $isi, $cocok, PREG_OFFSET_CAPTURE);

            foreach ($cocok[0] as $i => [$teks, $offset]) {
                if (! in_array($cocok[2][$i][0], self::NO_PLUS, true)) {
                    continue;
                }

                $akhir = strlen($isi);

                if (preg_match('/::make\(/', $isi, $berikut, PREG_OFFSET_CAPTURE, $offset + strlen($teks))) {
                    $akhir = $berikut[0][1];
                }

                if (str_contains(substr($isi, $offset, $akhir - $offset), 'createOption')) {
                    $baris = substr_count(substr($isi, 0, $offset), "\n") + 1;
                    $pelanggaran[] = "{$relatif}:{$baris}  {$cocok[2][$i][0]}: tombol + dilarang di dropdown produk/customer/supplier/material (fieldnya banyak; buka tab baru, lalu cari di dropdown)";
                }
            }
        }

        $this->assertSame([], $pelanggaran, "Keputusan Owner: produk dan customer tidak memakai tombol +.\n".implode("\n", $pelanggaran));
    }

    /**
     * @return array<string, string>  path relatif dari app/Filament => isi tanpa komentar
     */
    private function berkas(): array
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Filament'), \FilesystemIterator::SKIP_DOTS));
        $hasil = [];

        foreach ($iterator as $berkas) {
            if ($berkas->getExtension() !== 'php' || $berkas->getFilename() === 'QuickCreate.php') {
                continue;
            }

            $relatif = str_replace('\\', '/', substr($berkas->getPathname(), strlen(app_path('Filament')) + 1));
            $hasil[$relatif] = $this->tanpaKomentar(file_get_contents($berkas->getPathname()));
        }

        ksort($hasil);

        return $hasil;
    }

    /** Buang komentar, jaga jumlah barisnya (nomor baris pada pesan tetap benar). */
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
}
