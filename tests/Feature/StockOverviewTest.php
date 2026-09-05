<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BeefStockResource;
use App\Filament\Admin\Resources\MaterialStockResource;
use App\Models\BeefStock;
use App\Models\Grade;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStockTake;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Stock Overview -- daftar stok daging dan bahan penolong.
 *
 * Halaman ini tidak menyimpan apa pun; ia hanya membaca. Justru itu yang
 * membuat kesalahannya sulit terlihat: tidak ada yang gagal, angkanya cuma
 * salah.
 */
class StockOverviewTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $jonggol;

    private Warehouse $perum;

    private Grade $chill;

    private Grade $frozen;

    private Grade $gradeA;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jonggol = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->perum = Warehouse::create(['code' => 'PRM', 'name' => 'PERUM', 'is_active' => true]);

        $this->chill = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $this->frozen = Grade::create(['name' => 'FROZEN', 'is_active' => true]);
        $this->gradeA = Grade::create(['name' => 'A', 'is_active' => true]);

        $this->produk = Product::create([
            'name' => 'SIRLOIN',
            'code' => 'B001',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    private function stok(Warehouse $gudang, Grade $grade, float $berat, string $barcode): BeefStock
    {
        return BeefStock::create([
            'barcode' => $barcode,
            'product_id' => $this->produk->id,
            'warehouse_id' => $gudang->id,
            'grade_id' => $grade->id,
            'weight' => $berat,
            'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'status' => 'IN_STOCK',
        ]);
    }

    private function pegawai(array $izin): User
    {
        $user = User::create([
            'name' => 'Gudang',
            'username' => 'gudang_' . uniqid(),
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        foreach ($izin as $nama) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => $nama],
                    ['module_name' => 'Beef Stocks', 'description' => $nama],
                )->id
            );
        }

        return $user->fresh();
    }

    // =====================================================================
    // Total harus sama dengan jumlah kolomnya
    // =====================================================================

    /**
     * Stok ber-grade selain CHILL/FROZEN tetap punya kolom.
     *
     * Ini kegagalan aslinya. Keempat kolomnya dipatok mati pada gudang 1 dan 2
     * serta grade 1 dan 2, sementara kolom Total menjumlah SELURUH baris
     * `IN_STOCK`. Satu karton ber-grade A masuk ke Total tetapi tidak muncul
     * di kolom mana pun, sehingga Total lebih besar daripada jumlah kolom yang
     * terlihat -- tanpa satu pun error, dan tanpa ada yang bisa menunjuk
     * selisihnya ada di mana.
     */
    public function test_the_total_always_equals_the_sum_of_the_columns_shown(): void
    {
        $this->stok($this->jonggol, $this->chill, 10.00, 'BC-1');
        $this->stok($this->jonggol, $this->gradeA, 5.00, 'BC-2');
        $this->stok($this->perum, $this->chill, 3.00, 'BC-3');

        $baris = BeefStockResource::getEloquentQuery()->find($this->produk->id);

        $jumlahKolom = 0.0;

        foreach (BeefStockResource::stockBuckets() as $bucket) {
            $jumlahKolom += (float) $baris->{$bucket['key']};
        }

        $this->assertSame(18.0, (float) $baris->total_qty);

        $this->assertSame(
            18.0,
            $jumlahKolom,
            'Ada stok yang dihitung di Total tetapi tidak punya kolom.',
        );
    }

    /** Kombinasi yang tidak ada isinya tidak boleh memakan kolom. */
    public function test_a_combination_with_no_stock_gets_no_column(): void
    {
        $this->stok($this->jonggol, $this->chill, 10.00, 'BC-1');

        $kunci = array_column(BeefStockResource::stockBuckets(), 'key');

        $this->assertSame(['w' . $this->jonggol->id . '_g' . $this->chill->id], $kunci);

        $this->assertNotContains(
            'w' . $this->jonggol->id . '_g' . $this->frozen->id,
            $kunci,
            'Grade tanpa stok tetap memakan kolom.',
        );
    }

    /** Barang yang sudah keluar tidak boleh melahirkan kolom. */
    public function test_stock_that_has_left_does_not_create_a_column(): void
    {
        $keluar = $this->stok($this->jonggol, $this->frozen, 8.00, 'BC-9');
        $keluar->update(['status' => 'OUT']);

        $this->assertSame([], BeefStockResource::stockBuckets());
    }

    // =====================================================================
    // Posisi stok pada tanggal tertentu
    // =====================================================================

    private function gerak(float $in, float $out, string $kapan, ?Grade $grade = null): void
    {
        $gerakan = \App\Models\BeefStockMovement::create([
            'product_id' => $this->produk->id,
            'warehouse_id' => $this->jonggol->id,
            'condition' => ($grade ?? $this->chill)->id,
            'barcode' => 'BC-' . uniqid(),
            'transaction_type' => $in > 0 ? 'IN_GR_BEEF' : 'TALLY',
            'reference_document' => 'DOC',
            'weight_in' => $in,
            'weight_out' => $out,
            'pcs_in' => $in > 0 ? 1 : 0,
            'pcs_out' => $out > 0 ? 1 : 0,
            'created_by' => User::factory()->create(['role' => 'programmer', 'is_active' => true])->id,
        ]);

        $gerakan->forceFill(['created_at' => $kapan])->saveQuietly();
    }

    /**
     * Tanggal 1 September berarti posisi AKHIR HARI 1 September.
     *
     * Pertanyaan Owner: "misal gw filter ke tanggal 1 september itu lu
     * tampilin 1 september jam berapa?" Jawabannya 23:59:59, dan tanggal itu
     * ditulis di atas tabelnya supaya tidak jadi tebak-tebakan.
     */
    public function test_a_date_means_the_end_of_that_day(): void
    {
        $this->gerak(20.00, 0, '2026-09-01 08:00:00');
        $this->gerak(5.00, 0, '2026-09-01 22:30:00');   // masih hari yang sama
        $this->gerak(100.00, 0, '2026-09-02 00:30:00'); // sudah lewat

        \Livewire\Livewire::actingAs($this->pegawai(['view_beef_stocks']))
            ->test(\App\Filament\Admin\Resources\BeefStockResource\Pages\ListBeefStocks::class)
            ->set('tableFilters.as_of.date', '2026-09-01')
            ->assertSee('25.00')
            ->assertDontSee('125.00');
    }

    /**
     * Tanpa tanggal, yang dibaca tetap tabel stok.
     *
     * `beef_stocks` yang memegang kebenaran tentang stok hari ini, dan tidak
     * diganti hanya karena buku besarnya juga bisa menjawab.
     */
    public function test_without_a_date_it_still_reads_the_stock_table(): void
    {
        $this->stok($this->jonggol, $this->chill, 7.00, 'BC-SEKARANG');

        $this->assertNull(BeefStockResource::asOf());

        \Livewire\Livewire::actingAs($this->pegawai(['view_beef_stocks']))
            ->test(\App\Filament\Admin\Resources\BeefStockResource\Pages\ListBeefStocks::class)
            ->assertSee('7.00');
    }

    /**
     * Kolomnya ikut tanggalnya.
     *
     * Ini bagian yang paling gampang salah: tanggal tidak hanya menyaring
     * baris, ia menentukan KOLOM apa saja yang muncul. Kalau kolomnya
     * terlanjur dibentuk untuk tanggal lain, angkanya tampil di kolom yang
     * keliru tanpa satu pun error.
     */
    public function test_the_columns_follow_the_date(): void
    {
        // Grade A pernah ada isinya, lalu habis sebelum tanggal yang dilihat.
        $this->gerak(12.00, 0, '2026-08-01 08:00:00', $this->gradeA);
        $this->gerak(0, 12.00, '2026-08-02 08:00:00', $this->gradeA);
        $this->gerak(30.00, 0, '2026-08-03 08:00:00');

        app()->instance(BeefStockResource::AS_OF, \Illuminate\Support\Carbon::parse('2026-08-31')->endOfDay());
        BeefStockResource::forgetCachedPosition();

        $kunci = array_column(BeefStockResource::stockBuckets(), 'key');

        $this->assertSame(['w' . $this->jonggol->id . '_g' . $this->chill->id], $kunci);

        // Dilihat pada 1 Agustus, grade A masih punya isi -- jadi punya kolom.
        app()->instance(BeefStockResource::AS_OF, \Illuminate\Support\Carbon::parse('2026-08-01')->endOfDay());
        BeefStockResource::forgetCachedPosition();

        $this->assertSame(
            ['w' . $this->jonggol->id . '_g' . $this->gradeA->id],
            array_column(BeefStockResource::stockBuckets(), 'key'),
        );
    }

    /** Total pada tanggal itu tetap sama dengan jumlah kolomnya. */
    public function test_the_total_matches_the_columns_on_a_past_date_too(): void
    {
        $this->gerak(20.00, 0, '2026-08-10 09:00:00');
        $this->gerak(6.00, 0, '2026-08-10 09:00:00', $this->gradeA);
        $this->gerak(0, 5.00, '2026-09-05 09:00:00');

        app()->instance(BeefStockResource::AS_OF, \Illuminate\Support\Carbon::parse('2026-08-31')->endOfDay());
        BeefStockResource::forgetCachedPosition();

        $baris = BeefStockResource::getEloquentQuery()->find($this->produk->id);

        $jumlah = 0.0;

        foreach (BeefStockResource::stockBuckets() as $bucket) {
            $jumlah += (float) $baris->{$bucket['key']};
        }

        $this->assertSame(26.0, (float) $baris->total_qty);
        $this->assertSame(26.0, $jumlah);
    }

    /**
     * Peringatan "waktu input" muncul HANYA saat tanggal mundur dipilih.
     *
     * Angka yang benar tetapi disalahpahami sama merugikannya dengan angka
     * yang salah -- tetapi memasang peringatan sepanjang hari untuk angka
     * yang memang milik hari ini hanya melatih orang mengabaikannya.
     */
    public function test_the_entry_time_warning_appears_only_for_a_past_date(): void
    {
        $this->gerak(20.00, 0, '2026-09-01 08:00:00');

        $halaman = \Livewire\Livewire::actingAs($this->pegawai(['view_beef_stocks']))
            ->test(\App\Filament\Admin\Resources\BeefStockResource\Pages\ListBeefStocks::class);

        $halaman->assertDontSee('23:59:59');

        $halaman->set('tableFilters.as_of.date', '2026-09-01')
            ->assertSee('23:59:59')
            ->assertSee('01 Sep 2026');
    }

    // =====================================================================
    // Izin
    // =====================================================================

    /**
     * Melihat daftar dan membuka satu barisnya dijaga izin yang sama.
     *
     * Model resource-nya `Product`, jadi `canView()` yang tidak ditulis jatuh
     * ke `ProductPolicy` dan meminta `view_products`. Orang gudang melihat
     * daftarnya, mengeklik barisnya -- yang memang dibuat bisa diklik -- lalu
     * menemukan 403.
     */
    public function test_opening_one_product_needs_the_same_permission_as_the_list(): void
    {
        $this->actingAs($this->pegawai(['view_beef_stocks']));

        $this->assertTrue(BeefStockResource::canViewAny());
        $this->assertTrue(
            BeefStockResource::canView($this->produk),
            'Barisnya bisa diklik tetapi halamannya menolak.',
        );
    }

    public function test_the_material_stock_list_and_its_detail_agree_too(): void
    {
        $this->actingAs($this->pegawai(['view_material_stocks']));

        $this->assertTrue(MaterialStockResource::canViewAny());
        $this->assertTrue(MaterialStockResource::canView(new Material()));
    }

    /** Laporan tetap laporan: tidak ada yang boleh membuat atau menyunting. */
    public function test_neither_stock_list_can_ever_be_written_to(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'programmer', 'is_active' => true]));

        $this->assertFalse(BeefStockResource::canCreate());
        $this->assertFalse(BeefStockResource::canEdit($this->produk));
        $this->assertFalse(MaterialStockResource::canCreate());
        $this->assertFalse(MaterialStockResource::canEdit(new Material()));
    }

    /**
     * Izin yang disebut kode harus benar-benar ada.
     *
     * `delete_beef_stocks` menjaga tombol hapus stok, tetapi tidak pernah
     * dibuat -- tidak di seeder, tidak di migrasi mana pun. Akibatnya
     * `hasPermission()` atasnya selalu `false`: hanya akun programmer yang
     * lolos, dan tidak ada cara memberikan hak itu kepada orang gudang.
     *
     * Bentuk kegagalannya diam total. Tombolnya hanya tidak pernah muncul,
     * dan tidak ada yang tahu kenapa.
     */
    public function test_every_permission_the_code_asks_for_actually_exists(): void
    {
        // Dibaca lewat token PHP, bukan pencocokan teks.
        //
        // `TrashedRecords` memuat contoh `hasPermission('view_deleted_...')`
        // di dalam KOMENTAR. Pemindai yang membaca berkas sebagai teks biasa
        // menuduhnya sebagai izin yang hilang, dan tuduhan palsu pada akhirnya
        // membuat penjaga ini dimatikan orang.
        $disebut = [];

        foreach ($this->berkasPhp(['app']) as $berkas) {
            $token = array_values(array_filter(
                token_get_all(file_get_contents($berkas)),
                fn ($t): bool => ! is_array($t)
                    || ! in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
            ));

            foreach ($token as $i => $satu) {
                if (! is_array($satu) || $satu[0] !== T_STRING || $satu[1] !== 'hasPermission') {
                    continue;
                }

                $argumen = $token[$i + 2] ?? null;

                if (is_array($argumen) && $argumen[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $disebut[trim($argumen[1], "'\"")] = true;
                }
            }
        }

        $sumber = '';

        foreach ($this->berkasPhp(['database/seeders', 'database/migrations']) as $berkas) {
            $sumber .= file_get_contents($berkas);
        }

        $hilang = array_values(array_filter(
            array_keys($disebut),
            fn (string $nama): bool => ! str_contains($sumber, "'{$nama}'"),
        ));

        sort($hilang);

        $this->assertSame(
            [],
            $hilang,
            "Izin berikut disebut di kode tetapi tidak pernah dibuat, jadi tidak ada "
            . "yang bisa mencentangnya:\n" . implode("\n", $hilang),
        );
    }

    // =====================================================================
    // Hapus stok
    // =====================================================================

    /**
     * Setiap pergerakan stok harus punya nama pelakunya.
     *
     * Dari dua puluh empat pemanggilan `BeefStockMovement::create`, dua puluh
     * tiga menulis `created_by`. Yang satu tidak: aksi hapus stok manual di
     * halaman ini -- satu-satunya yang menghancurkan baris stok, dan
     * `BeefStock` tidak memakai hapus lunak, sehingga catatan pergerakan
     * itulah satu-satunya yang tersisa.
     *
     * Memperbaiki satu berkas tidak menahan yang kedua puluh lima.
     */
    public function test_every_stock_movement_records_who_wrote_it(): void
    {
        $pelanggar = [];

        foreach ($this->berkasPhp(['app']) as $berkas) {
            $isi = file_get_contents($berkas);
            $posisi = 0;

            while (($mulai = strpos($isi, 'BeefStockMovement::create([', $posisi)) !== false) {
                $mulai = strpos($isi, 'BeefStockMovement::create([', $posisi);
                $akhir = strpos($isi, ']);', $mulai);
                $blok = substr($isi, $mulai, $akhir - $mulai);

                if (! str_contains($blok, 'created_by')) {
                    $pelanggar[] = $this->relatif($berkas)
                        . ':' . (substr_count(substr($isi, 0, $mulai), "\n") + 1);
                }

                $posisi = $akhir + 3;
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Pergerakan stok berikut ditulis tanpa `created_by`, sehingga tidak ada "
            . "yang tahu siapa pelakunya:\n" . implode("\n", $pelanggar),
        );
    }

    // =====================================================================
    // Penyamaran saat opname material
    // =====================================================================

    public function test_a_running_material_count_is_recognised_in_one_place(): void
    {
        $this->assertFalse(MaterialStockTake::isCounting());

        MaterialStockTake::create([
            'document_number' => 'MSO-1',
            'date' => now()->toDateString(),
            'period' => now()->format('Y-m'),
            'status' => 'IN_PROGRESS',
        ]);

        $this->assertTrue(MaterialStockTake::isCounting());
    }

    /**
     * Angka yang disamarkan di layar tidak boleh terbaca lewat ekspor.
     *
     * Sebelumnya kolomnya menjadi `***` tetapi kedua tombol ekspor mencetak
     * angka aslinya. Penyamaran yang punya pintu belakang bukan penyamaran:
     * hitungan yang bisa menyalin jawabannya tidak memeriksa apa pun.
     */
    public function test_the_material_export_is_masked_while_a_count_runs(): void
    {
        $material = $this->material(12.5);

        MaterialStockTake::create([
            'document_number' => 'MSO-2',
            'date' => now()->toDateString(),
            'period' => now()->format('Y-m'),
            'status' => 'IN_PROGRESS',
        ]);

        $html = Blade::render(
            file_get_contents(base_path('resources/views/exports/material-stocks-pdf.blade.php')),
            ['records' => collect([$material]), 'masked' => MaterialStockTake::isCounting(), 'title' => 'x'],
        );

        $this->assertStringContainsString('***', $html);
        $this->assertStringNotContainsString('12,50', $html);
    }

    /**
     * PDF stok material harus memuat isinya, bukan strip.
     *
     * Seluruh kolomnya membaca `$record->material->...`, padahal yang dikirim
     * model `Material` yang tidak punya relasi bernama `material`. Karena
     * setiap pembacaannya berakhir `?? '-'`, tidak ada satu pun error: PDF-nya
     * terbit rapi dengan isi strip semua dan min stock selalu 0,00.
     */
    public function test_the_material_stock_pdf_prints_what_it_is_supposed_to(): void
    {
        $material = $this->material(12.5);

        $html = Blade::render(
            file_get_contents(base_path('resources/views/exports/material-stocks-pdf.blade.php')),
            ['records' => collect([$material]), 'masked' => false, 'title' => 'x'],
        );

        $this->assertStringContainsString($material->code, $html);
        $this->assertStringContainsString('PLASTIK', $html);
        $this->assertStringContainsString('KEMASAN', $html);
        $this->assertStringContainsString('PCS', $html);
        $this->assertStringContainsString('12,50', $html);
        $this->assertStringContainsString('5,00', $html);
    }

    private function material(float $qty): Material
    {
        $material = Material::create([
            'code' => 'MTR-01',
            'name' => 'PLASTIK',
            'material_category_id' => MaterialCategory::create(['name' => 'KEMASAN'])->id,
            'material_unit_id' => MaterialUnit::create(['name' => 'PCS'])->id,
            'min_stock' => 5,
            'show_in_stock' => true,
        ]);

        $material->qty = $qty;

        return $material;
    }

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
        return str_replace(['\\', base_path() . '/'], ['/', ''], $jalur);
    }
}
