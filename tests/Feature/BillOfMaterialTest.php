<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMaterial;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bill of Material: bahan penolong yang dipakai sebuah produk.
 *
 * Yang dijaga di sini adalah tiga keputusan yang gampang hilang diam-diam
 * kalau tidak ada yang menahannya, dan ketiganya sudah pernah salah di
 * legacy:
 *
 *   1. jumlah yang KOSONG bukan nol -- Drylog dipakai, jumlahnya tidak tetap;
 *   2. dasar hitung DISIMPAN, bukan ditulis sebagai teks di sebelah kolom;
 *   3. satu bahan hanya boleh muncul sekali per produk.
 */
class BillOfMaterialTest extends TestCase
{
    use RefreshDatabase;

    private function produk(string $nama = 'BLADE'): Product
    {
        $kategori = ProductCategory::firstOrCreate(
            ['name' => 'DAGING'],
            ['prefix' => 1],
        );

        return Product::create([
            'code' => (string) random_int(100000, 999999),
            'name' => $nama,
            'category_id' => $kategori->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    private function bahan(string $nama): Material
    {
        return Material::create([
            'name' => $nama,
            'material_category_id' => MaterialCategory::firstOrCreate(['name' => 'PACKAGING'])->id,
            'material_unit_id' => MaterialUnit::firstOrCreate(['name' => 'BOX'])->id,
            'min_stock' => 0,
            'is_active' => true,
            'show_in_stock' => true,
        ]);
    }

    /**
     * Jumlah yang kosong berarti "dipakai, jumlahnya tidak tetap".
     *
     * Ini keadaan Drylog: dipakai di hampir semua produk, tetapi jumlahnya
     * berbeda-beda walau produknya sama. Nol berarti tidak dipakai, dan itu
     * keterangan yang salah -- baris yang tidak dipakai memang dihapus.
     *
     * @test
     */
    public function an_amount_left_empty_is_not_the_same_as_zero()
    {
        $baris = ProductMaterial::create([
            'product_id' => $this->produk()->id,
            'material_id' => $this->bahan('DRY LOG')->id,
            'quantity' => null,
            'basis' => 'box',
        ]);

        $this->assertNull($baris->fresh()->quantity, 'Jumlah kosong tidak boleh berubah menjadi nol saat disimpan.');
        $this->assertTrue($baris->jumlahnyaTidakTetap());

        $terhitung = ProductMaterial::create([
            'product_id' => $this->produk('CHUCK')->id,
            'material_id' => $this->bahan('KARTON TOP')->id,
            'quantity' => 1,
            'basis' => 'box',
        ]);

        $this->assertFalse($terhitung->jumlahnyaTidakTetap());
    }

    /**
     * Dasar hitungnya tersimpan bersama barisnya.
     *
     * Data produksi legacy memperlihatkan kenapa ini perlu: plastik cryovac
     * dan karton sama-sama tertulis `qty 1`, padahal yang satu per potong
     * daging dan yang lain per box. Angkanya sama, artinya berbeda -- dan
     * legacy hanya menuliskan bedanya sebagai teks di sebelah kolom.
     *
     * @test
     */
    public function each_row_carries_the_basis_it_is_counted_on()
    {
        $produk = $this->produk();

        $karton = ProductMaterial::create([
            'product_id' => $produk->id,
            'material_id' => $this->bahan('KARTON TOP DAGING')->id,
            'quantity' => 1,
            'basis' => 'box',
        ]);

        $plastik = ProductMaterial::create([
            'product_id' => $produk->id,
            'material_id' => $this->bahan('PLASTIK CRYOVAC 300X500')->id,
            'quantity' => 1,
            'basis' => 'piece',
        ]);

        $this->assertSame($karton->quantity, $plastik->quantity, 'Prasyarat ujinya: jumlahnya memang sama.');
        $this->assertNotSame($karton->basis, $plastik->basis, 'Dua baris berjumlah sama harus tetap bisa dibedakan dasar hitungnya.');

        $this->assertSame('Per Box', $karton->labelBasis());
        $this->assertSame('Per Pcs', $plastik->labelBasis());
    }

    /**
     * Setiap dasar hitung yang dipakai kode punya labelnya sendiri.
     *
     * Penjaga arah kedua: menambah nilai baru ke basis tanpa menambah
     * labelnya membuat tabelnya menampilkan nilai mentah dari basis data.
     *
     * @test
     */
    public function every_basis_has_a_label_registered_in_both_languages()
    {
        $en = json_decode(file_get_contents(lang_path('en.json')), true);
        $id = json_decode(file_get_contents(lang_path('id.json')), true);

        foreach (ProductMaterial::BASIS as $nilai => $label) {
            $this->assertArrayHasKey($label, $en, "Label basis '{$nilai}' belum terdaftar di en.json.");
            $this->assertArrayHasKey($label, $id, "Label basis '{$nilai}' belum terdaftar di id.json.");
        }
    }

    /**
     * Satu bahan hanya boleh muncul sekali per produk.
     *
     * Dua baris bahan yang sama dengan jumlah berbeda tidak punya arti yang
     * bisa dipertahankan: yang membacanya harus menebak dijumlahkan atau yang
     * belakangan menang. Legacy menahannya lewat pemeriksaan di PHP -- yang
     * berarti apa pun yang menulis tanpa melewati halaman itu bisa
     * menggandakannya.
     *
     * @test
     */
    public function the_same_material_cannot_be_listed_twice_on_one_product()
    {
        $produk = $this->produk();
        $bahan = $this->bahan('PLASTIK LINIER');

        ProductMaterial::create([
            'product_id' => $produk->id,
            'material_id' => $bahan->id,
            'quantity' => 1,
            'basis' => 'box',
        ]);

        $this->expectException(QueryException::class);

        ProductMaterial::create([
            'product_id' => $produk->id,
            'material_id' => $bahan->id,
            'quantity' => 2,
            'basis' => 'piece',
        ]);
    }

    /**
     * Bahan yang sama boleh dipakai produk yang berbeda.
     *
     * Penjaga arah kedua bagi yang di atas: kunci uniknya harus menyebut
     * pasangan produk-dan-bahan, bukan bahannya saja.
     *
     * @test
     */
    public function the_same_material_may_serve_more_than_one_product()
    {
        $bahan = $this->bahan('PLASTIK LINIER');

        foreach (['BLADE', 'CHUCK'] as $nama) {
            ProductMaterial::create([
                'product_id' => $this->produk($nama)->id,
                'material_id' => $bahan->id,
                'quantity' => 1,
                'basis' => 'box',
            ]);
        }

        $this->assertSame(2, ProductMaterial::where('material_id', $bahan->id)->count());
    }

    /**
     * Menghapus produk ikut membawa BOM-nya; menghapus bahan ditolak.
     *
     * Dua arah yang sengaja dibedakan. BOM adalah bagian dari produknya, jadi
     * ia ikut pergi. Bahan berdiri sendiri, dan resep yang kehilangan
     * bahannya diam-diam lebih buruk daripada penghapusan yang ditolak.
     *
     * @test
     */
    public function a_deleted_product_takes_its_rows_along_while_a_used_material_refuses_to_go()
    {
        $produk = $this->produk();
        $bahan = $this->bahan('KARUNG');

        ProductMaterial::create([
            'product_id' => $produk->id,
            'material_id' => $bahan->id,
            'quantity' => 1,
            'basis' => 'box',
        ]);

        try {
            $bahan->delete();
            $this->fail('Bahan yang masih dipakai sebuah BOM seharusnya tidak bisa dihapus.');
        } catch (QueryException) {
            // Inilah yang diharapkan.
        }

        $this->assertSame(1, ProductMaterial::count());

        $produk->delete();

        $this->assertSame(0, ProductMaterial::count(), 'BOM harus ikut terhapus bersama produknya.');
    }
}
