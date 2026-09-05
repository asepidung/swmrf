<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\StockTakeResource;
use App\Models\BeefStock;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\WarehouseFreezeService;
use App\Support\BarcodeSequence;
use App\Support\ShelfLife;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opname daging.
 *
 * Dokumen ini satu-satunya yang boleh menghapus stok tanpa dokumen penjualan
 * apa pun -- karena itu penjagaannya diperiksa lebih keras daripada modul
 * lain.
 */
class StockTakeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $gudang;

    private Grade $chill;

    private Grade $gradeA;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->gudang = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->chill = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        Grade::create(['name' => 'FROZEN', 'is_active' => true]);
        $this->gradeA = Grade::create(['name' => 'A', 'is_active' => true]);

        $this->produk = Product::create([
            'name' => 'SIRLOIN',
            'code' => 'B001',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    private function opname(string $status = StockTake::STATUS_IN_PROGRESS): StockTake
    {
        return StockTake::create([
            'document_number' => 'ST#'.uniqid(),
            'period' => now()->format('Y-m'),
            'date' => now()->toDateString(),
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    // =====================================================================
    // Umur simpan
    // =====================================================================

    /**
     * Hanya CHILL yang tiga bulan.
     *
     * Keputusan Owner, 5 September 2026: "chill 3 bulan, frozen, a, b dan r
     * setahun". Sebelumnya aturan ini ditulis empat kali dan salinannya tidak
     * sama -- dua di antaranya memberi grade A tiga bulan, dua lagi setahun.
     * Satu karton yang sama mendapat tanggal kedaluwarsa berbeda semata karena
     * pintu mana yang dilewatinya.
     */
    public function test_only_chill_gets_the_short_shelf_life(): void
    {
        $kemas = '2026-09-01';

        $this->assertSame('2026-12-01', ShelfLife::expiryDateFor($kemas, 1));   // CHILL
        $this->assertSame('2027-09-01', ShelfLife::expiryDateFor($kemas, 2));   // FROZEN
        $this->assertSame('2027-09-01', ShelfLife::expiryDateFor($kemas, 3));   // A
        $this->assertSame('2027-09-01', ShelfLife::expiryDateFor($kemas, 4));   // B
        $this->assertSame('2027-09-01', ShelfLife::expiryDateFor($kemas, 5));   // R
    }

    /**
     * Aturan umur simpan hanya boleh ada di SATU tempat.
     *
     * Empat salinan yang berbeda isinya adalah cara aturan ini rusak sebelum
     * ini. Memperbaikinya sekali tidak menahan salinan kelima.
     */
    public function test_the_shelf_life_rule_lives_in_exactly_one_place(): void
    {
        $pelanggar = [];

        foreach ($this->berkasPhp(['app']) as $berkas) {
            if (str_ends_with(str_replace('\\', '/', $berkas), 'app/Support/ShelfLife.php')) {
                continue;
            }

            $isi = file_get_contents($berkas);

            if (preg_match('/addMonths\(3\)/', $isi) && preg_match('/addYear\(\)/', $isi)) {
                $pelanggar[] = $this->relatif($berkas);
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Aturan umur simpan ditulis ulang di luar `ShelfLife`:\n".implode("\n", $pelanggar),
        );
    }

    /**
     * Laporan "lebih dari 60 hari" hanya untuk barang berpendingin.
     *
     * Keputusan Owner, 5 September 2026: umur simpan hanya jadi soal untuk
     * produk chill. Kodenya memang sudah begitu, TETAPI caranya rapuh -- ia
     * mencocokkan NAMA grade, bukan idnya. Sekali saja nama grade
     * itu diubah dari layar Master Data, laporannya kosong tanpa satu pun
     * error, dan tidak ada yang bisa membedakan "tidak ada barang tua" dari
     * "penyaringnya sudah tidak cocok".
     */
    public function test_the_sixty_day_report_follows_the_grade_id_not_its_name(): void
    {
        $berkas = file_get_contents(base_path(
            'app/Filament/Admin/Resources/BeefStockAgingResource.php'
        ));

        $this->assertStringNotContainsString(
            "->where('name', 'like'",
            $berkas,
            'Laporan umur simpan masih mencocokkan nama grade.',
        );

        $this->assertStringContainsString(
            'ShelfLife::shortLivedGradeIds()',
            $berkas,
            'Laporan umur simpan tidak memakai daftar grade yang sama dengan aturan umur simpan.',
        );

        $this->assertSame([1], ShelfLife::shortLivedGradeIds());
    }

    // =====================================================================
    // Barcode temuan
    // =====================================================================

    /**
     * Dua temuan yang seluruh isinya sama tetap mendapat barcode berbeda.
     *
     * Penomoran lamanya hanya melihat `beef_stocks`, padahal barcode temuan
     * ditulis ke `stock_take_items` dan baru pindah ke stok saat opnamenya
     * diselesaikan. Dua karton dengan produk, tanggal, berat, dan pcs yang
     * sama karena itu mendapat barcode yang SAMA PERSIS.
     */
    public function test_two_findings_in_one_count_never_share_a_barcode(): void
    {
        $opname = $this->opname();
        $prefix = '0'.now()->format('dmy');

        $pertama = BarcodeSequence::nextPadded($prefix, [
            BeefStock::query(), StockTakeItem::query(),
        ]);

        StockTakeItem::create([
            'stock_take_id' => $opname->id,
            'barcode' => $prefix.'B00010'.$pertama,
            'product_id' => $this->produk->id,
            'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id,
            'weight' => 20,
            'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'status' => 'UNEXPECTED',
            'is_manual' => true,
        ]);

        $kedua = BarcodeSequence::nextPadded($prefix, [
            BeefStock::query(), StockTakeItem::query(),
        ]);

        $this->assertNotSame($pertama, $kedua, 'Dua temuan mendapat urutan yang sama.');
        $this->assertSame('0001', $pertama);
        $this->assertSame('0002', $kedua);
    }

    /** Barcode yang lebih pendek tidak boleh mengembalikan urutan ke satu. */
    public function test_a_shorter_barcode_does_not_reset_the_sequence(): void
    {
        $opname = $this->opname();
        $prefix = '0'.now()->format('dmy');

        foreach (['0007', '0008'] as $urutan) {
            StockTakeItem::create([
                'stock_take_id' => $opname->id,
                'barcode' => $prefix.'B00010'.$urutan,
                'product_id' => $this->produk->id,
                'warehouse_id' => $this->gudang->id,
                'grade_id' => $this->chill->id,
                'weight' => 20, 'qty_pcs' => 1,
                'pack_date' => now()->toDateString(),
                'status' => 'UNEXPECTED', 'is_manual' => true,
            ]);
        }

        // Baris terakhir justru barcode lama yang jauh lebih pendek.
        //
        // Ujungnya sengaja bukan angka. Barcode pendek yang ujungnya
        // kebetulan angka hanya bisa MENAIKKAN urutan, tidak pernah
        // menabrakkannya -- yang berbahaya justru kebalikannya, dan
        // itulah yang dulu terjadi lewat `strlen >= 26`.
        StockTakeItem::create([
            'stock_take_id' => $opname->id,
            'barcode' => $prefix.'ZZZZZ',
            'product_id' => $this->produk->id,
            'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id,
            'weight' => 20, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'status' => 'UNEXPECTED', 'is_manual' => true,
        ]);

        $this->assertSame(
            '0009',
            BarcodeSequence::nextPadded($prefix, [BeefStock::query(), StockTakeItem::query()]),
            'Barcode pendek di baris terakhir mengembalikan urutan ke awal.',
        );
    }

    // =====================================================================
    // Penjagaan dokumen
    // =====================================================================

    /** Opname yang sudah selesai tidak boleh diubah hitungannya lagi. */
    public function test_a_finished_count_can_no_longer_be_counted(): void
    {
        $this->assertTrue($this->opname()->isCountable());
        $this->assertFalse($this->opname(StockTake::STATUS_COMPLETED)->isCountable());
        $this->assertFalse($this->opname(StockTake::STATUS_CANCELED)->isCountable());
    }

    /**
     * Opname yang sudah ada hitungannya tidak boleh dihapus.
     *
     * Penjagaan ini dulu hanya ada di halaman View; halaman Edit dan aksi
     * hapus massal tidak menjaga apa pun. Dan menghapus opname yang sedang
     * berjalan juga MENCAIRKAN pembekuan gudang tanpa memberi tahu siapa pun.
     */
    public function test_a_count_with_results_cannot_be_deleted(): void
    {
        $opname = $this->opname();

        $this->assertTrue($opname->isDeletable());

        StockTakeItem::create([
            'stock_take_id' => $opname->id,
            'barcode' => 'BC-1',
            'product_id' => $this->produk->id,
            'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id,
            'weight' => 20, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'status' => 'MATCHED', 'is_manual' => false,
        ]);

        $this->assertFalse($opname->fresh()->isDeletable());
    }

    /** Yang belum dihitung sama sekali masih boleh dibatalkan. */
    public function test_a_count_that_only_holds_its_snapshot_can_still_be_deleted(): void
    {
        $opname = $this->opname();

        StockTakeItem::create([
            'stock_take_id' => $opname->id,
            'barcode' => 'BC-2',
            'product_id' => $this->produk->id,
            'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id,
            'weight' => 20, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'status' => 'MISSING', 'is_manual' => false,
        ]);

        $this->assertTrue($opname->fresh()->isDeletable());
    }

    // =====================================================================
    // Izin
    // =====================================================================

    /**
     * Menyelesaikan opname butuh izinnya sendiri.
     *
     * Tombolnya menghapus PERMANEN setiap baris MISSING dari `beef_stocks`.
     * Sebelumnya satu-satunya syarat menampilkannya adalah statusnya
     * IN_PROGRESS -- artinya siapa pun yang boleh melihat daftar opname boleh
     * menghapus stok.
     */
    public function test_finishing_a_count_needs_its_own_permission(): void
    {
        $this->assertTrue(
            Permission::where('name', 'finish_stock_takes')->exists(),
            'Izin `finish_stock_takes` tidak pernah dibuat, jadi tidak ada yang bisa mencentangnya.',
        );

        $berkas = file_get_contents(base_path('app/Filament/Admin/Resources/StockTakeResource.php'));

        $this->assertStringContainsString(
            "hasPermission('finish_stock_takes')",
            $berkas,
            'Tombol Finish Opname tidak dijaga izin apa pun.',
        );
    }

    // =====================================================================
    // Pembekuan gudang
    // =====================================================================

    /**
     * Pembekuan gudang tidak boleh nyangkut mati.
     *
     * `$bypassed` dulu dikembalikan `false` di baris TERAKHIR transaksinya.
     * Kalau transaksinya gagal di tengah, nilainya tidak pernah dikembalikan
     * -- dan karena ia properti STATIS, seluruh sisa permintaan itu berjalan
     * tanpa pembekuan sama sekali, tepat saat opname sedang berlangsung.
     */
    public function test_the_freeze_is_restored_even_when_finishing_fails(): void
    {
        $berkas = file_get_contents(base_path('app/Filament/Admin/Resources/StockTakeResource.php'));

        $this->assertMatchesRegularExpression(
            '/\}\s*finally\s*\{\s*\\\\App\\\\Services\\\\WarehouseFreezeService::\$bypassed = false;/',
            $berkas,
            'Pembekuan gudang dikembalikan tanpa `finally`, jadi ia nyangkut kalau transaksinya gagal.',
        );

        $this->assertFalse(
            WarehouseFreezeService::$bypassed,
            'Pembekuan gudang tertinggal dalam keadaan dilewati.',
        );
    }

    /** Opname yang berjalan membekukan penulisan stok. */
    public function test_a_running_count_freezes_stock_writes(): void
    {
        $this->opname();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        BeefStock::create([
            'barcode' => 'BC-BEKU',
            'product_id' => $this->produk->id,
            'warehouse_id' => $this->gudang->id,
            'grade_id' => $this->chill->id,
            'weight' => 10, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'status' => 'IN_STOCK',
        ]);
    }

    // =====================================================================

    /**
     * @param  array<int, string>  $akar
     * @return \Generator<string>
     */
    private function berkasPhp(array $akar): \Generator
    {
        foreach ($akar as $satu) {
            $berkas = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($satu))
            );

            foreach ($berkas as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    yield $file->getPathname();
                }
            }
        }
    }

    private function relatif(string $jalur): string
    {
        return str_replace(['\\', base_path().'/'], ['/', ''], $jalur);
    }
}
