<?php

namespace Tests\Feature;

use App\Filament\Clusters\ProductsCluster\Resources\ProductResource;
use App\Filament\Clusters\ProductsCluster\Resources\ProductResource\RelationManagers\BillOfMaterialsRelationManager;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMaterial;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    private function pengguna(string ...$izin): User
    {
        $user = User::create([
            'name' => 'Penguji',
            'username' => 'uji_'.uniqid(),
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        foreach ($izin as $satu) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => $satu],
                    ['module_name' => 'Bill of Materials', 'description' => $satu],
                )->id
            );
        }

        return $user->fresh();
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

    /**
     * Panel BOM tertutup bagi yang tidak punya izin membacanya.
     *
     * @test
     */
    public function the_panel_stays_shut_without_the_permission_to_read_it()
    {
        $produk = $this->produk();

        $this->actingAs($this->pengguna());

        $this->assertFalse(
            BillOfMaterialsRelationManager::canViewForRecord($produk, ProductResource\Pages\EditProduct::class),
            'Panel BOM terbuka tanpa izin view_product_materials.',
        );

        $this->actingAs($this->pengguna('view_product_materials'));

        $this->assertTrue(
            BillOfMaterialsRelationManager::canViewForRecord($produk, ProductResource\Pages\EditProduct::class),
            'Panel BOM tetap tertutup padahal izinnya sudah diberikan.',
        );
    }

    /**
     * Menyalin BOM dari produk lain melengkapi, bukan menimpa.
     *
     * Jumlah yang sudah disesuaikan tangan tidak boleh hilang karena satu
     * klik, dan salinannya harus PUTUS dari asalnya -- data produksi
     * memperlihatkan daftarnya memang sering berbeda sedikit antar produk
     * yang mirip.
     *
     * @test
     */
    public function copying_from_another_product_fills_the_gaps_without_overwriting()
    {
        $sumber = $this->produk('BACKRIB');
        $tujuan = $this->produk('BACKRIB CUT');

        $karton = $this->bahan('KARTON TOP TULANG');
        $linier = $this->bahan('PLASTIK LINIER');

        ProductMaterial::create(['product_id' => $sumber->id, 'material_id' => $karton->id, 'quantity' => 1, 'basis' => 'box']);
        ProductMaterial::create(['product_id' => $sumber->id, 'material_id' => $linier->id, 'quantity' => 1, 'basis' => 'box']);

        // Yang sudah ada di tujuan, dengan jumlah yang sengaja berbeda.
        ProductMaterial::create(['product_id' => $tujuan->id, 'material_id' => $linier->id, 'quantity' => 3, 'basis' => 'piece']);

        $this->actingAs($this->pengguna('view_product_materials', 'create_product_materials'));

        Livewire::test(BillOfMaterialsRelationManager::class, [
            'ownerRecord' => $tujuan,
            'pageClass' => ProductResource\Pages\EditProduct::class,
        ])
            ->callTableAction('salin_bom', data: ['product_id' => $sumber->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $tujuan->billOfMaterials()->count(), 'Baris yang belum ada seharusnya ikut tersalin.');

        $yangSudahAda = $tujuan->billOfMaterials()->where('material_id', $linier->id)->first();

        $this->assertSame(3, $yangSudahAda->quantity, 'Jumlah yang sudah disesuaikan tangan tidak boleh tertimpa.');
        $this->assertSame('piece', $yangSudahAda->basis, 'Dasar hitung yang sudah ada juga tidak boleh tertimpa.');

        // Salinannya putus: mengubah asalnya tidak menyentuh tujuannya.
        $sumber->billOfMaterials()->where('material_id', $karton->id)->update(['quantity' => 9]);

        $this->assertSame(
            1,
            $tujuan->billOfMaterials()->where('material_id', $karton->id)->first()->quantity,
            'Salinan seharusnya berdiri sendiri, bukan mengikuti produk asalnya.',
        );
    }

    /**
     * Tombol salin tidak muncul bagi yang tidak boleh menambah baris.
     *
     * Menyalin MEMBUAT baris, jadi izinnya harus izin membuat -- bukan izin
     * membaca yang kebetulan sudah dipegang karena panelnya terbuka.
     *
     * @test
     */
    public function the_copy_button_needs_the_permission_to_add_rows()
    {
        $produk = $this->produk();

        $this->actingAs($this->pengguna('view_product_materials'));

        Livewire::test(BillOfMaterialsRelationManager::class, [
            'ownerRecord' => $produk,
            'pageClass' => ProductResource\Pages\EditProduct::class,
        ])
            ->assertTableActionHidden('salin_bom')
            ->assertTableActionHidden('create');

        $this->actingAs($this->pengguna('view_product_materials', 'create_product_materials'));

        Livewire::test(BillOfMaterialsRelationManager::class, [
            'ownerRecord' => $produk,
            'pageClass' => ProductResource\Pages\EditProduct::class,
        ])
            ->assertTableActionVisible('salin_bom')
            ->assertTableActionVisible('create');
    }

    /**
     * BOM terpasang di halaman produknya.
     *
     * Penjaga yang menahan panel ini lepas diam-diam: menghapus satu baris di
     * `getRelations()` tidak membuat apa pun gagal, panelnya cuma menghilang.
     *
     * @test
     */
    public function the_panel_is_wired_to_the_product_screen()
    {
        $this->assertContains(
            BillOfMaterialsRelationManager::class,
            ProductResource::getRelations(),
            'Panel BOM tidak terdaftar di ProductResource -- ia hanya akan hilang tanpa gejala.',
        );
    }
}
