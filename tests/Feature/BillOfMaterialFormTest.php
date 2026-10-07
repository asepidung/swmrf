<?php

namespace Tests\Feature;

use App\Filament\Clusters\ProductsCluster\Resources\ProductResource;
use App\Filament\Clusters\ProductsCluster\Resources\ProductResource\Forms\BillOfMaterialSection;
use App\Filament\Clusters\ProductsCluster\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Clusters\ProductsCluster\Resources\ProductResource\Pages\ListProducts;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMaterial;
use App\Models\User;
use App\Support\ProductBomSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BOM sebagai baris-baris di form Edit produk (issue #507).
 *
 * Yang dijaga: semua baris disimpan SEKALI lewat Save changes; jumlah kosong
 * tetap kosong (bukan nol); satu bahan sekali per produk; dan izin tambah,
 * ubah, hapus ditegakkan DI SERVER -- menyembunyikan tombol di layar tidak
 * menutup permintaan yang dikirim langsung.
 *
 * Memakai pengguna `employee`: `hasPermission()` selalu `true` untuk
 * `programmer`, jadi test dengan peran itu tidak menguji izin apa pun.
 */
class BillOfMaterialFormTest extends TestCase
{
    use RefreshDatabase;

    private function produk(string $nama = 'BACKRIB'): Product
    {
        $kategori = ProductCategory::firstOrCreate(['name' => 'DAGING'], ['prefix' => 1]);

        return Product::create([
            'code' => (string) random_int(100000, 999999),
            'name' => $nama,
            'category_id' => $kategori->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    private function bahan(string $nama, string $satuan = 'DUS'): Material
    {
        return Material::create([
            'name' => $nama,
            'material_category_id' => MaterialCategory::firstOrCreate(['name' => 'PACKAGING'])->id,
            'material_unit_id' => MaterialUnit::firstOrCreate(['name' => $satuan])->id,
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

        foreach (array_merge(['view_products', 'edit_products'], $izin) as $satu) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $satu], ['module_name' => 'Test', 'description' => $satu])->id
            );
        }

        return $user->fresh();
    }

    private function baris(Material $bahan, string $basis = 'box', ?int $jumlah = 1, ?string $catatan = null): array
    {
        return ['material_id' => $bahan->id, 'basis' => $basis, 'quantity' => $jumlah, 'note' => $catatan];
    }

    private function semuaIzin(): User
    {
        return $this->pengguna(
            'view_product_materials', 'create_product_materials', 'edit_product_materials', 'delete_product_materials',
        );
    }

    // =====================================================================
    // Satu form, satu kali simpan
    // =====================================================================

    public function test_several_rows_are_saved_at_once_with_one_save(): void
    {
        $produk = $this->produk();
        $karton = $this->bahan('KARTON TOP');
        $plastik = $this->bahan('PLASTIK VAKUM', 'LEMBAR');
        $drylog = $this->bahan('DRYLOG', 'IKAT');

        $this->actingAs($this->semuaIzin());

        Livewire::test(EditProduct::class, ['record' => $produk->getKey()])
            ->fillForm(['billOfMaterials' => [
                $this->baris($karton),
                $this->baris($plastik, 'piece', 2, 'cryovac'),
                $this->baris($drylog, 'box', null),
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3, $produk->billOfMaterials()->count(), 'Ketiga baris harus tersimpan dalam satu Save.');

        $plastikBaris = $produk->billOfMaterials()->where('material_id', $plastik->id)->first();
        $this->assertSame(2, $plastikBaris->quantity);
        $this->assertSame('piece', $plastikBaris->basis);
        $this->assertSame('cryovac', $plastikBaris->note);

        $this->assertNull(
            $produk->billOfMaterials()->where('material_id', $drylog->id)->first()->quantity,
            'Jumlah yang dikosongkan harus tetap kosong, bukan nol.',
        );
    }

    public function test_adding_changing_and_deleting_rows_work_in_the_same_save(): void
    {
        $produk = $this->produk();
        $a = $this->bahan('KARTON TOP');
        $b = $this->bahan('KARTON BOTTOM');
        $c = $this->bahan('PLASTIK VAKUM', 'LEMBAR');

        $barisA = ProductMaterial::create(['product_id' => $produk->id, 'material_id' => $a->id, 'quantity' => 1, 'basis' => 'box']);
        $barisB = ProductMaterial::create(['product_id' => $produk->id, 'material_id' => $b->id, 'quantity' => 1, 'basis' => 'box']);

        $this->actingAs($this->semuaIzin());

        $form = Livewire::test(EditProduct::class, ['record' => $produk->getKey()]);

        $state = $form->get('data.billOfMaterials');

        // A diubah jumlahnya, B dihapus, C ditambah.
        $state["record-{$barisA->id}"]['quantity'] = 4;
        unset($state["record-{$barisB->id}"]);
        $state['baru-1'] = $this->baris($c, 'piece', 3);

        $form->set('data.billOfMaterials', $state)->call('save')->assertHasNoFormErrors();

        $this->assertSame(4, $barisA->fresh()->quantity);
        $this->assertNull(ProductMaterial::find($barisB->id), 'Baris yang dihapus dari form harus hilang dari BOM.');
        $this->assertSame(3, $produk->billOfMaterials()->where('material_id', $c->id)->first()->quantity);
        $this->assertSame(2, $produk->billOfMaterials()->count());
    }

    public function test_the_form_lists_the_existing_rows_when_it_opens(): void
    {
        $produk = $this->produk();
        $a = $this->bahan('KARTON TOP');
        ProductMaterial::create(['product_id' => $produk->id, 'material_id' => $a->id, 'quantity' => 2, 'basis' => 'box']);

        $this->actingAs($this->semuaIzin());

        $rows = array_values(Livewire::test(EditProduct::class, ['record' => $produk->getKey()])->get('data.billOfMaterials'));

        $this->assertCount(1, $rows);
        $this->assertSame($a->id, $rows[0]['material_id']);
        $this->assertSame(2, $rows[0]['quantity']);
    }

    // =====================================================================
    // Aturan data
    // =====================================================================

    public function test_the_same_material_cannot_be_listed_twice(): void
    {
        $produk = $this->produk();
        $a = $this->bahan('KARTON TOP');

        $this->actingAs($this->semuaIzin());

        Livewire::test(EditProduct::class, ['record' => $produk->getKey()])
            ->fillForm(['billOfMaterials' => [$this->baris($a), $this->baris($a, 'piece', 2)]])
            ->call('save');

        $this->assertSame(0, $produk->billOfMaterials()->count(), 'Bahan kembar tidak boleh tersimpan separuh.');
    }

    public function test_swapping_the_materials_of_two_rows_does_not_trip_the_unique_index(): void
    {
        $produk = $this->produk();
        $a = $this->bahan('KARTON TOP');
        $b = $this->bahan('KARTON BOTTOM');

        $barisA = ProductMaterial::create(['product_id' => $produk->id, 'material_id' => $a->id, 'quantity' => 1, 'basis' => 'box']);
        $barisB = ProductMaterial::create(['product_id' => $produk->id, 'material_id' => $b->id, 'quantity' => 2, 'basis' => 'box']);

        ProductBomSync::sync($produk, [
            "record-{$barisA->id}" => $this->baris($b, 'box', 1),
            "record-{$barisB->id}" => $this->baris($a, 'box', 2),
        ], $this->semuaIzin());

        $this->assertSame(1, $produk->billOfMaterials()->where('material_id', $b->id)->first()->quantity);
        $this->assertSame(2, $produk->billOfMaterials()->where('material_id', $a->id)->first()->quantity);
    }

    public function test_a_quantity_of_zero_is_refused_because_empty_is_how_unfixed_is_written(): void
    {
        $produk = $this->produk();

        $this->expectException(ValidationException::class);

        ProductBomSync::sync($produk, ['baru-1' => $this->baris($this->bahan('KARTON'), 'box', 0)], $this->semuaIzin());
    }

    // =====================================================================
    // Izin: ditegakkan di server
    // =====================================================================

    public function test_adding_a_row_needs_the_create_permission(): void
    {
        $produk = $this->produk();
        $a = $this->bahan('KARTON TOP');

        $this->expectException(ValidationException::class);

        ProductBomSync::sync($produk, ['baru-1' => $this->baris($a)], $this->pengguna('view_product_materials', 'edit_product_materials', 'delete_product_materials'));
    }

    public function test_changing_a_row_needs_the_edit_permission(): void
    {
        $produk = $this->produk();
        $a = $this->bahan('KARTON TOP');
        $baris = ProductMaterial::create(['product_id' => $produk->id, 'material_id' => $a->id, 'quantity' => 1, 'basis' => 'box']);

        try {
            ProductBomSync::sync($produk, ["record-{$baris->id}" => $this->baris($a, 'box', 9)], $this->pengguna('view_product_materials', 'create_product_materials', 'delete_product_materials'));
            $this->fail('Mengubah baris tanpa izin edit seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertSame(1, $baris->fresh()->quantity, 'Baris tidak boleh berubah walau permintaannya ditolak.');
        }
    }

    public function test_deleting_a_row_needs_the_delete_permission(): void
    {
        $produk = $this->produk();
        $a = $this->bahan('KARTON TOP');
        $baris = ProductMaterial::create(['product_id' => $produk->id, 'material_id' => $a->id, 'quantity' => 1, 'basis' => 'box']);

        try {
            ProductBomSync::sync($produk, [], $this->pengguna('view_product_materials', 'create_product_materials', 'edit_product_materials'));
            $this->fail('Menghapus baris tanpa izin delete seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertNotNull(ProductMaterial::find($baris->id), 'Baris tidak boleh hilang walau permintaannya ditolak.');
        }
    }

    public function test_a_save_with_nothing_changed_needs_no_bom_permission_at_all(): void
    {
        $produk = $this->produk();
        $a = $this->bahan('KARTON TOP');
        $baris = ProductMaterial::create(['product_id' => $produk->id, 'material_id' => $a->id, 'quantity' => 1, 'basis' => 'box']);

        // Hanya melihat: menyimpan produk (mis. mengubah nama) tidak boleh gagal
        // hanya karena BOM ikut terkirim apa adanya.
        ProductBomSync::sync($produk, ["record-{$baris->id}" => $this->baris($a)], $this->pengguna('view_product_materials'));

        $this->assertSame(1, $produk->billOfMaterials()->count());
        $this->assertTrue(true);
    }

    public function test_the_section_is_hidden_without_the_view_permission_and_on_create(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->pengguna());

        Livewire::test(EditProduct::class, ['record' => $produk->getKey()])
            ->assertFormFieldIsHidden('billOfMaterials');

        $this->actingAs($this->pengguna('view_product_materials'));

        Livewire::test(EditProduct::class, ['record' => $produk->getKey()])
            ->assertFormFieldIsVisible('billOfMaterials');
    }

    // =====================================================================
    // Salin dari produk lain: mengisi FORM, belum database
    // =====================================================================

    public function test_copying_builds_form_rows_without_touching_the_database(): void
    {
        $sumber = $this->produk('BACKRIB');
        $tujuan = $this->produk('BACKRIB CUT');
        $karton = $this->bahan('KARTON TOP TULANG');
        $linier = $this->bahan('PLASTIK LINIER', 'LEMBAR');

        ProductMaterial::create(['product_id' => $sumber->id, 'material_id' => $karton->id, 'quantity' => 1, 'basis' => 'box']);
        ProductMaterial::create(['product_id' => $sumber->id, 'material_id' => $linier->id, 'quantity' => 1, 'basis' => 'box']);
        // Sudah ada di tujuan dengan jumlah yang sengaja berbeda: tidak boleh tertimpa.
        $sudahAda = ProductMaterial::create(['product_id' => $tujuan->id, 'material_id' => $linier->id, 'quantity' => 3, 'basis' => 'piece']);

        $current = ["record-{$sudahAda->id}" => $this->baris($linier, 'piece', 3)];

        [$merged, $added] = BillOfMaterialSection::copyRows($current, $sumber->id);

        $this->assertSame(1, $added, 'Hanya bahan yang belum ada yang ikut tersalin.');
        $this->assertCount(2, $merged);
        $this->assertSame(3, $merged["record-{$sudahAda->id}"]['quantity'], 'Jumlah yang sudah ada tidak boleh tertimpa.');
        $this->assertSame(1, $tujuan->billOfMaterials()->count(), 'Menyalin tidak boleh menulis ke database sebelum Save.');

        // Baru disimpan lewat Save: baris tersalin berkunci acak, jadi menjadi baris BARU.
        $this->actingAs($this->semuaIzin());
        ProductBomSync::sync($tujuan, $merged);

        $this->assertSame(2, $tujuan->billOfMaterials()->count());

        // Salinan putus dari asalnya.
        $sumber->billOfMaterials()->where('material_id', $karton->id)->update(['quantity' => 9]);
        $this->assertSame(1, $tujuan->billOfMaterials()->where('material_id', $karton->id)->first()->quantity);
    }

    public function test_the_copy_button_needs_the_permission_to_add_rows(): void
    {
        $section = BillOfMaterialSection::make();
        $copy = collect($section->getHeaderActions())->first(fn ($a) => $a->getName() === 'salin_bom');

        $this->assertNotNull($copy, 'Tombol salin tidak ada di bagian BOM.');

        $this->actingAs($this->pengguna('view_product_materials'));
        $this->assertTrue($copy->isHidden(), 'Tombol salin terbuka tanpa izin create_product_materials.');

        $this->actingAs($this->semuaIzin());
        $this->assertFalse($copy->isHidden());
    }

    public function test_the_section_is_part_of_the_product_form(): void
    {
        $this->assertSame([], ProductResource::getRelations(), 'BOM tidak lagi panel relasi; ia bagian dari form produk.');
        $this->assertStringContainsString(
            'BillOfMaterialSection',
            file_get_contents((new \ReflectionClass(ProductResource::class))->getFileName()),
            'Bagian BOM tidak terpasang di form ProductResource -- ia akan hilang tanpa gejala.',
        );
    }

    // =====================================================================
    // Tombol BOM di daftar produk (Owner, 7 Oktober 2026)
    // =====================================================================

    public function test_the_list_button_opens_the_existing_rows_and_saves_all_rows_at_once(): void
    {
        $produk = $this->produk();
        $karton = $this->bahan('KARTON TOP');
        $plastik = $this->bahan('PLASTIK VAKUM', 'LEMBAR');
        $lama = ProductMaterial::create(['product_id' => $produk->id, 'material_id' => $karton->id, 'quantity' => 1, 'basis' => 'box']);

        $this->actingAs($this->semuaIzin());

        $aksi = Livewire::test(ListProducts::class)->mountTableAction('bill_of_material', $produk);

        // Baris lama sudah terisi dari BOM-nya, lengkap dengan id.
        $kunci = array_key_first($aksi->get('mountedTableActionsData.0.billOfMaterials'));
        $this->assertSame($lama->id, $aksi->get("mountedTableActionsData.0.billOfMaterials.$kunci.id"));

        $aksi
            ->set("mountedTableActionsData.0.billOfMaterials.$kunci.quantity", 2)
            ->set('mountedTableActionsData.0.billOfMaterials.baru-1', $this->baris($plastik, 'piece', 1, 'cryovac'))
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $produk->billOfMaterials()->count());
        $this->assertSame(2, (int) $lama->fresh()->quantity);
        $this->assertSame('piece', $produk->billOfMaterials()->where('material_id', $plastik->id)->value('basis'));
    }

    public function test_the_list_button_is_hidden_without_permission_to_view_boms(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->pengguna());

        Livewire::test(ListProducts::class)->assertTableActionHidden('bill_of_material', $produk);
    }

    public function test_the_list_button_cannot_add_rows_without_the_create_permission(): void
    {
        $produk = $this->produk();
        $karton = $this->bahan('KARTON TOP');

        $this->actingAs($this->pengguna('view_product_materials', 'edit_product_materials'));

        Livewire::test(ListProducts::class)
            ->callTableAction('bill_of_material', $produk, ['billOfMaterials' => ['baru-1' => $this->baris($karton)]]);

        $this->assertSame(0, $produk->billOfMaterials()->count());
    }

    /**
     * Repeater tanpa relasi mengganti kunci baris dengan UUID. Kalau id baris
     * lama hilang, mengubah satu jumlah akan dibaca sebagai hapus + buat, dan
     * pengguna tanpa izin hapus ditolak tanpa sebab yang jelas.
     */
    public function test_editing_from_the_list_keeps_the_row_ids_and_needs_no_delete_permission(): void
    {
        $produk = $this->produk();
        $karton = $this->bahan('KARTON TOP');
        $lama = ProductMaterial::create(['product_id' => $produk->id, 'material_id' => $karton->id, 'quantity' => 1, 'basis' => 'box']);

        $this->actingAs($this->pengguna('view_product_materials', 'create_product_materials', 'edit_product_materials'));

        Livewire::test(ListProducts::class)
            ->mountTableAction('bill_of_material', $produk)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame([$lama->id], $produk->billOfMaterials()->pluck('id')->all());
    }

    public function test_the_list_button_cannot_touch_a_row_of_another_product(): void
    {
        $produk = $this->produk('BACKRIB');
        $lain = $this->produk('TOPSIDE');
        $karton = $this->bahan('KARTON TOP');
        $milikLain = ProductMaterial::create(['product_id' => $lain->id, 'material_id' => $karton->id, 'quantity' => 5, 'basis' => 'box']);

        $this->actingAs($this->semuaIzin());

        Livewire::test(ListProducts::class)
            ->callTableAction('bill_of_material', $produk, ['billOfMaterials' => [
                'x' => ['id' => $milikLain->id] + $this->baris($karton, 'box', 99),
            ]]);

        $this->assertSame(5, (int) $milikLain->fresh()->quantity);
        $this->assertSame(1, $produk->billOfMaterials()->count());
    }
}
