<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStock;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockTake;
use App\Models\MaterialStockTakeItem;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\User;
use App\Services\MaterialStockFreezeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opname material.
 *
 * Konsepnya disamakan dengan opname daging atas keputusan Owner. Yang paling
 * penting dari penyamaan itu: SATU jalur penerapan hasil hitungan, dan stok
 * yang dibekukan selama hitungan berjalan.
 */
class MaterialStockTakeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);

        $this->material = Material::create([
            'code' => 'MTR-01',
            'name' => 'KERTAS HVS',
            'material_category_id' => MaterialCategory::create(['name' => 'KERTAS'])->id,
            'material_unit_id' => MaterialUnit::create(['name' => 'RIM'])->id,
            'min_stock' => 0,
            'show_in_stock' => true,
        ]);
    }

    private function opname(string $status = MaterialStockTake::STATUS_IN_PROGRESS): MaterialStockTake
    {
        return MaterialStockTake::create([
            'document_number' => 'MSO-'.uniqid(),
            'period' => now()->format('Y-m'),
            'date' => now()->toDateString(),
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    private function stok(float $qty): MaterialStock
    {
        return MaterialStockFreezeService::bypass(fn (): MaterialStock => MaterialStock::create([
            'material_id' => $this->material->id,
            'qty' => $qty,
        ]));
    }

    private function hitungan(MaterialStockTake $opname, float $sistem, ?float $fisik): MaterialStockTakeItem
    {
        return MaterialStockTakeItem::create([
            'material_stock_take_id' => $opname->id,
            'material_id' => $this->material->id,
            'system_qty' => $sistem,
            'physical_qty' => $fisik,
            'difference_qty' => $fisik === null ? null : $fisik - $sistem,
        ]);
    }

    // =====================================================================
    // Pembekuan
    // =====================================================================

    /**
     * Stok material tidak boleh bergerak selama opname berjalan.
     *
     * Keputusan Owner: konsepnya sama dengan opname daging. Sebelum ini sisi
     * material tidak punya pembekuan sama sekali, padahal `system_qty` diambil
     * sebagai snapshot saat dokumennya dibuat -- kalau stok bergerak di tengah
     * hitungan, selisih yang tersimpan mengukur jarak ke angka yang sudah
     * tidak ada lagi.
     */
    public function test_material_stock_is_frozen_while_a_count_runs(): void
    {
        $stok = $this->stok(10);

        $this->opname();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $stok->update(['qty' => 12]);
    }

    public function test_material_stock_moves_freely_when_no_count_runs(): void
    {
        $stok = $this->stok(10);

        $stok->update(['qty' => 12]);

        $this->assertEqualsWithDelta(12, (float) $stok->fresh()->qty, 0.001);
    }

    /** Opname yang sudah selesai tidak membekukan apa pun. */
    public function test_a_finished_count_does_not_freeze(): void
    {
        $stok = $this->stok(10);

        $this->opname(MaterialStockTake::STATUS_COMPLETED);

        $stok->update(['qty' => 15]);

        $this->assertEqualsWithDelta(15, (float) $stok->fresh()->qty, 0.001);
    }

    /**
     * Pembekuan HARUS pulih walaupun pekerjaannya gagal.
     *
     * Kalau dikembalikan di baris terakhir blok yang bisa gagal, sekali
     * gagal nilainya tidak pernah pulih -- dan karena ia properti statis,
     * seluruh sisa permintaan berjalan tanpa pembekuan.
     */
    public function test_the_freeze_returns_even_when_the_work_fails(): void
    {
        try {
            MaterialStockFreezeService::bypass(function (): void {
                throw new \RuntimeException('gagal di tengah');
            });
        } catch (\RuntimeException) {
            // memang disengaja
        }

        $this->assertFalse(
            MaterialStockFreezeService::$bypassed,
            'Pembekuan stok material tertinggal dalam keadaan dilewati.',
        );
    }

    // =====================================================================
    // Satu jalur penerapan
    // =====================================================================

    /**
     * Menerapkan hasil hitungan menyesuaikan stok DAN menulis buku besarnya.
     *
     * Sebelumnya ada dua tombol "selesaikan opname" dengan arti berbeda: yang
     * satu menambahkan selisih lewat `StockService`, yang satu menimpa dengan
     * angka hitungan sambil menulis stok dan buku besar dengan tangan tanpa
     * penguncian.
     */
    public function test_applying_a_count_adjusts_the_stock_and_writes_the_ledger(): void
    {
        $this->stok(10);
        $opname = $this->opname();
        $this->hitungan($opname, 10, 7);

        $opname->applyToStock();

        $this->assertEqualsWithDelta(7.00, (float) MaterialStock::where('material_id', $this->material->id)->first()->qty, 0.001);

        $gerakan = MaterialStockMovement::where('material_id', $this->material->id)->latest('id')->first();

        $this->assertNotNull($gerakan, 'Penyesuaian opname tidak menulis buku besar.');
        $this->assertSame('STOCK_TAKE_ADJUSTMENT', $gerakan->transaction_type);
        $this->assertEqualsWithDelta(3, (float) $gerakan->qty_out, 0.001);
        $this->assertSame($this->user->id, $gerakan->created_by);
    }

    /** Yang tidak dihitung sama sekali tidak boleh mengubah stok. */
    public function test_an_uncounted_material_is_left_alone(): void
    {
        $this->stok(10);
        $opname = $this->opname();
        $this->hitungan($opname, 10, null);

        $opname->applyToStock();

        $this->assertEqualsWithDelta(10.00, (float) MaterialStock::where('material_id', $this->material->id)->first()->qty, 0.001);
        $this->assertSame(0, MaterialStockMovement::count());
    }

    /** Selesai berarti tercatat siapa yang menyelesaikannya dan kapan. */
    public function test_finishing_records_who_did_it(): void
    {
        $this->stok(10);
        $opname = $this->opname();
        $this->hitungan($opname, 10, 9);

        $opname->applyToStock();

        $segar = $opname->fresh();

        $this->assertSame(MaterialStockTake::STATUS_COMPLETED, $segar->status);
        $this->assertSame($this->user->id, $segar->completed_by);
        $this->assertNotNull($segar->completed_at);
    }

    /** Penerapannya melewati pembekuan, lalu memulihkannya. */
    public function test_applying_a_count_is_not_blocked_by_its_own_freeze(): void
    {
        $this->stok(10);
        $opname = $this->opname();
        $this->hitungan($opname, 10, 4);

        $opname->applyToStock();

        $this->assertEqualsWithDelta(4.00, (float) MaterialStock::where('material_id', $this->material->id)->first()->qty, 0.001);
        $this->assertFalse(MaterialStockFreezeService::$bypassed);
    }

    /**
     * Hanya boleh ada SATU jalur penerapan di kode.
     *
     * Dua tombol dengan dua arti adalah cara modul ini rusak sebelum ini.
     */
    public function test_only_one_place_applies_a_material_count(): void
    {
        $pelanggar = [];

        foreach ([
            'app/Filament/Admin/Resources/MaterialStockTakeResource/Pages/EditMaterialStockTake.php',
            'app/Filament/Admin/Resources/MaterialStockTakeResource/Pages/ManageMaterialStockTakeItems.php',
        ] as $berkas) {
            $isi = file_get_contents(base_path($berkas));

            if (str_contains($isi, 'StockService::adjustStock')
                || str_contains($isi, '$stock->qty = ')
                || str_contains($isi, 'MaterialStockMovement::create')) {
                $pelanggar[] = $berkas;
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Berkas berikut menerapkan hasil opname sendiri, bukan lewat "
            ."`MaterialStockTake::applyToStock()`:\n".implode("\n", $pelanggar),
        );
    }

    /**
     * Aturan Over / Short / Sesuai hanya boleh ada di SATU tempat.
     *
     * Sebelumnya ia ditulis dua kali, di relation manager dan di halaman
     * hitungan. Salah satu salinannya masih memakai kunci `__('Sesuai')` yang
     * sudah dihapus dari kedua berkas bahasa -- kunci yatim yang tampil apa
     * adanya kepada pengguna berbahasa Inggris.
     */
    public function test_the_variance_rule_lives_in_one_place(): void
    {
        $opname = $this->opname();

        $this->assertSame('-', $this->hitungan($opname, 10, null)->varianceLabel());
        $this->assertSame(__('Over'), $this->hitungan($opname, 10, 12)->varianceLabel());
        $this->assertSame(__('Short'), $this->hitungan($opname, 10, 8)->varianceLabel());
        $this->assertSame(__('Matches'), $this->hitungan($opname, 10, 10)->varianceLabel());

        $this->assertSame('gray', $this->hitungan($opname, 10, null)->varianceColor());
        $this->assertSame('success', $this->hitungan($opname, 10, 10)->varianceColor());

        $pelanggar = [];

        foreach ([
            'app/Filament/Admin/Resources/MaterialStockTakeResource/RelationManagers/ItemsRelationManager.php',
            'app/Filament/Admin/Resources/MaterialStockTakeResource/Pages/ManageMaterialStockTakeItems.php',
        ] as $berkas) {
            $isi = $this->tanpaKomentarPhp(file_get_contents(base_path($berkas)));

            if (str_contains($isi, "__('Over')") || str_contains($isi, "__('Short')")) {
                $pelanggar[] = $berkas;
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Aturan selisih ditulis ulang di luar `MaterialStockTakeItem`:
".implode("
", $pelanggar),
        );
    }

    /** Komentar dibuang supaya penjelasan tidak ikut tertuduh. */
    private function tanpaKomentarPhp(string $isi): string
    {
        $hasil = '';

        foreach (@token_get_all($isi) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $hasil .= is_array($token) ? $token[1] : $token;
        }

        return $hasil;
    }

    // =====================================================================
    // Izin dan penjagaan dokumen
    // =====================================================================

    public function test_finishing_a_material_count_needs_its_own_permission(): void
    {
        $this->assertTrue(
            Permission::where('name', 'finish_material_stock_takes')->exists(),
            'Izin `finish_material_stock_takes` tidak pernah dibuat.',
        );

        foreach ([
            'app/Filament/Admin/Resources/MaterialStockTakeResource/Pages/EditMaterialStockTake.php',
            'app/Filament/Admin/Resources/MaterialStockTakeResource/Pages/ManageMaterialStockTakeItems.php',
        ] as $berkas) {
            $this->assertStringContainsString(
                "hasPermission('finish_material_stock_takes')",
                file_get_contents(base_path($berkas)),
                "Tombol selesai di {$berkas} tidak dijaga izin apa pun.",
            );
        }
    }

    /** Opname yang sudah ada hitungannya tidak boleh dihapus. */
    public function test_a_count_with_results_cannot_be_deleted(): void
    {
        $opname = $this->opname();

        $this->hitungan($opname, 10, null);
        $this->assertTrue($opname->fresh()->isDeletable());

        $this->hitungan($opname, 5, 5);
        $this->assertFalse($opname->fresh()->isDeletable());
    }

    /** Yang sudah selesai tidak bisa dihapus, apa pun isinya. */
    public function test_a_finished_count_cannot_be_deleted(): void
    {
        $this->assertFalse($this->opname(MaterialStockTake::STATUS_COMPLETED)->isDeletable());
        $this->assertFalse($this->opname(MaterialStockTake::STATUS_REVIEW)->isDeletable());
    }

    /**
     * Tidak boleh ada dua opname berjalan sekaligus.
     *
     * Dua dokumen berarti dua snapshot dan dua penerapan selisih ke stok yang
     * sama. Pembekuan tidak menahannya: yang dibekukan penulisan STOK, bukan
     * pembuatan dokumen opnamenya.
     */
    public function test_a_second_count_cannot_be_started(): void
    {
        foreach ([
            'app/Filament/Admin/Resources/MaterialStockTakeResource/Pages/CreateMaterialStockTake.php',
            'app/Filament/Admin/Resources/StockTakeResource/Pages/CreateStockTake.php',
        ] as $berkas) {
            $this->assertStringContainsString(
                'A stock count is already running',
                file_get_contents(base_path($berkas)),
                "Opname kedua tidak ditahan di {$berkas}.",
            );
        }
    }
}
