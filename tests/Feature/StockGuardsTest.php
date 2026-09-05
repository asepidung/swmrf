<?php

namespace Tests\Feature;

use App\Filament\Clusters\BeefStocks\Pages\FoundItemScanner;
use App\Models\BeefStock;
use App\Models\Grade;
use App\Models\Mutation;
use App\Models\MutationItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjagaan di rumpun Gudang & Stok.
 *
 * Tiga hal yang dijaga di sini semuanya berpusat pada satu pertanyaan: apakah
 * sebuah angka atau dokumen bisa lahir tanpa ada yang menyadarinya.
 */
class StockGuardsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);
    }

    // =====================================================================
    // Penomoran dokumen
    // =====================================================================

    /**
     * Dokumen yang DIHAPUS tetap memegang nomornya.
     *
     * `Mutation` memakai hapus lunak tetapi penomorannya memakai
     * `static::query()`, sehingga dokumen yang dihapus menjadi tak terlihat
     * dan nomornya dipakai ulang. Dua dokumen bernomor sama, tanpa satu pun
     * gejala sampai seseorang mencari MT#26001 dan menemukan dua.
     */
    public function test_a_deleted_mutation_keeps_its_number_reserved(): void
    {
        $gudang = Warehouse::create(['code' => 'A', 'name' => 'A', 'is_active' => true]);
        $tujuan = Warehouse::create(['code' => 'B', 'name' => 'B', 'is_active' => true]);

        $pertama = Mutation::create([
            'mutation_date' => now()->toDateString(),
            'from_warehouse_id' => $gudang->id,
            'to_warehouse_id' => $tujuan->id,
            'status' => 'DRAFT',
        ]);

        $nomorPertama = $pertama->mutation_number;

        $pertama->delete();

        $kedua = Mutation::create([
            'mutation_date' => now()->toDateString(),
            'from_warehouse_id' => $gudang->id,
            'to_warehouse_id' => $tujuan->id,
            'status' => 'DRAFT',
        ]);

        $this->assertNotSame(
            $nomorPertama,
            $kedua->mutation_number,
            'Nomor mutasi yang sudah dihapus dipakai ulang.',
        );
    }

    /**
     * Penjaga pola: tiap model yang memakai hapus lunak DAN penomoran dokumen
     * wajib menghitung yang terhapus.
     *
     * Dari enam belas model yang memakai `DocumentNumber`, lima belas sudah
     * benar dan satu tertinggal. Memperbaiki satu berkas tidak menahan yang
     * keenam belas berikutnya; pemindai inilah yang menahannya.
     */
    public function test_every_soft_deleting_document_counts_its_deleted_numbers(): void
    {
        $pelanggar = [];

        foreach (glob(app_path('Models/*.php')) as $berkas) {
            $isi = file_get_contents($berkas);

            if (! str_contains($isi, 'DocumentNumber::next')) {
                continue;
            }

            if (! str_contains($isi, 'SoftDeletes')) {
                continue;
            }

            // `self::` sama benarnya di sini; yang dijaga adalah ADANYA
            // withTrashed, bukan kata mana yang dipakai menyebut kelasnya.
            if (! preg_match('/(?:static|self)::withTrashed\(\)/', $isi)) {
                $pelanggar[] = basename($berkas);
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Model ini memakai hapus lunak tetapi penomorannya tidak menghitung yang terhapus, "
            ."sehingga nomornya bisa terpakai dua kali:\n".implode("\n", $pelanggar)
        );
    }

    // =====================================================================
    // Mutasi kosong
    // =====================================================================

    /**
     * Mutasi tanpa satu pun barang tidak bisa dikirim.
     *
     * Tanpa penjagaan ini, dokumen kosong bisa berstatus SENT, lalu diterima
     * di gudang tujuan, lalu selesai -- dokumen lengkap yang tidak memindahkan
     * apa pun.
     */
    public function test_the_send_button_is_disabled_while_nothing_has_been_scanned(): void
    {
        $berkas = file_get_contents(base_path(
            'app/Filament/Admin/Resources/MutationResource/Pages/ScanMutation.php'
        ));

        $this->assertStringContainsString(
            "->disabled(fn (): bool => \$this->record->items()->doesntExist())",
            $berkas,
            'Tombol kirim mutasi tidak menahan dokumen kosong.',
        );
    }

    // =====================================================================
    // Halaman yang MENCETAK stok
    // =====================================================================

    /**
     * Melihat stok tidak berarti boleh mencetak stok.
     *
     * `FoundItemScanner` membuat baris `BeefStock` baru dari isian orang --
     * satu-satunya tempat di aplikasi ini yang menambah persediaan tanpa
     * dokumen asal. Sebelumnya ia hanya dijaga gerbang clusternya, yang berisi
     * izin MELIHAT.
     */
    public function test_recording_a_found_item_needs_its_own_permission(): void
    {
        $pegawai = User::create([
            'name' => 'Gudang', 'username' => 'gudang_temuan',
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        foreach (['view_beef_stocks', 'view_beef_stock_movements', 'view_beef_stock_aging'] as $izin) {
            $pegawai->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => $izin],
                    ['module_name' => 'Beef Stocks', 'description' => $izin],
                )->id
            );
        }

        // Boleh melihat seluruhnya, tetapi TIDAK boleh mencetak.
        $this->actingAs($pegawai->fresh());
        $this->assertFalse(FoundItemScanner::canAccess());

        $pegawai->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'record_found_items'],
                ['module_name' => 'Beef Stocks', 'description' => 'record_found_items'],
            )->id
        );

        $this->actingAs($pegawai->fresh());
        $this->assertTrue(FoundItemScanner::canAccess());
    }

    /**
     * Penomoran barcode tidak memakai panjang sebagai penanda sah, dan tidak
     * mengurutkan barcode sebagai teks -- di SELURUH aplikasi.
     *
     * Ini penjaga yang datang terlambat. Bentuk yang sama sudah ditambal tiga
     * kali di tiga berkas yang berbeda:
     *
     *   #230  InputReturnItems   (Retur)
     *   #269  FoundItemScanner   (Temuan)
     *   #283  ScanStockTake      (Opname)
     *
     * Dua kali pertama yang ditambal hanya berkasnya, dan penjaganya pun
     * hanya menyebut nama berkas itu. Penjaga yang menyebut satu nama tidak
     * pernah menahan berkas keempat.
     *
     * Ketiganya salah dengan cara yang sama: `strlen >= 26` sebagai syarat sah
     * padahal Project Owner sudah menegaskan tidak semua barcode 26 karakter,
     * `substr(-4)` yang membaca `0000` pada urutan ke-10.000, dan
     * `orderBy('barcode', 'desc')` yang mengurutkan sebagai TEKS sehingga "9"
     * dianggap lebih besar daripada "10".
     */
    public function test_no_barcode_numbering_judges_by_length_or_sorts_as_text(): void
    {
        $pelanggar = [];

        $bentukTerlarang = [
            '/strlen\(\$\w+->barcode\)\s*>=\s*26/' => 'panjang barcode dipakai sebagai penanda sah',
            "/->orderBy\(\s*'barcode'\s*,\s*'desc'\s*\)/" => 'barcode diurutkan sebagai teks',
        ];

        foreach ($this->berkasPhp() as $berkas) {
            $isi = file_get_contents($berkas);

            foreach ($bentukTerlarang as $pola => $keterangan) {
                if (preg_match($pola, $isi)) {
                    $pelanggar[] = $this->relatif($berkas).'  -- '.$keterangan;
                }
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Penomoran barcode berikut memakai bentuk yang sudah diberantas tiga kali:\n"
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

    private function relatif(string $jalur): string
    {
        return str_replace(['\\', base_path().'/'], ['/', ''], $jalur);
    }

    // =====================================================================
    // Laporan tetap laporan
    // =====================================================================

    /**
     * Halaman umur simpan TIDAK boleh menjadi pintu kedua ke tabel stok.
     *
     * Ia membaca `BeefStock` yang sungguhan. Membuka jalan menyuntingnya dari
     * sebuah laporan berarti satu tabel stok punya dua pintu masuk, dan yang
     * kedua tidak mencatat pergerakan apa pun di `BeefStockMovement`.
     */
    public function test_the_aging_report_can_never_change_stock(): void
    {
        $resource = \App\Filament\Admin\Resources\BeefStockAgingResource::class;

        $stok = BeefStock::create([
            'barcode' => 'AGING-001',
            'product_id' => Product::create([
                'name' => 'SIRLOIN', 'code' => 'B001',
                'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
                'structure_type' => 'main', 'is_active' => true,
            ])->id,
            'warehouse_id' => Warehouse::create(['code' => 'A', 'name' => 'A', 'is_active' => true])->id,
            'grade_id' => Grade::create(['name' => 'CHILL', 'is_active' => true])->id,
            'weight' => 20,
            'qty_pcs' => 1,
            'pack_date' => now()->subDays(90)->toDateString(),
            'status' => 'IN_STOCK',
        ]);

        // Bahkan sebagai programmer, yang biasanya diberi jalan di mana-mana.
        $this->assertFalse($resource::canCreate());
        $this->assertFalse($resource::canEdit($stok));
        $this->assertFalse($resource::canDelete($stok));
    }
}
