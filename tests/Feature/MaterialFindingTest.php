<?php

namespace Tests\Feature;

use App\Filament\Clusters\MaterialsStock\Resources\MaterialFindingResource;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialFinding;
use App\Models\MaterialStock;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockTake;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\User;
use App\Services\MaterialStockFreezeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Temuan Material.
 *
 * Modul ini menambah stok bahan dari isian orang, tanpa dokumen asal apa pun
 * -- padanan `FoundItemScanner` di sisi daging. Karena itu penjagaannya
 * diperiksa lebih keras daripada layar yang hanya membaca.
 */
class MaterialFindingTest extends TestCase
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

    private function temuan(int $qty = 5, ?string $tanggal = null): MaterialFinding
    {
        return MaterialFinding::create([
            'date' => $tanggal ?? now()->toDateString(),
            'material_id' => $this->material->id,
            'qty' => $qty,
            'note' => 'ditemukan di rak',
            'created_by' => $this->user->id,
        ]);
    }

    private function opname(): MaterialStockTake
    {
        return MaterialStockTake::create([
            'document_number' => 'MSO-'.uniqid(),
            'period' => now()->format('Y-m'),
            'date' => now()->toDateString(),
            'status' => MaterialStockTake::STATUS_IN_PROGRESS,
            'created_by' => $this->user->id,
        ]);
    }

    // =====================================================================
    // Stok dan buku besarnya
    // =====================================================================

    public function test_a_finding_adds_stock_and_writes_the_ledger(): void
    {
        $this->temuan(5);

        $this->assertEqualsWithDelta(
            5,
            (float) MaterialStock::where('material_id', $this->material->id)->first()->qty,
            0.001,
        );

        $gerakan = MaterialStockMovement::latest('id')->first();

        $this->assertSame('TEMUAN MATERIAL', $gerakan->transaction_type);
        $this->assertSame($this->user->id, $gerakan->created_by);
    }

    public function test_deleting_a_finding_takes_the_stock_back(): void
    {
        $temuan = $this->temuan(5);
        $temuan->delete();

        $this->assertEqualsWithDelta(
            0,
            (float) MaterialStock::where('material_id', $this->material->id)->first()->qty,
            0.001,
        );

        $this->assertSame('PEMBATALAN TEMUAN MATERIAL', MaterialStockMovement::latest('id')->first()->transaction_type);
    }

    // =====================================================================
    // Pembekuan
    // =====================================================================

    /**
     * Temuan yang ditolak TIDAK BOLEH meninggalkan dokumennya.
     *
     * Penyesuaian stoknya dilakukan sesudah barisnya tersimpan. Sejak stok
     * material ikut dibekukan selama opname (#285), penyesuaian itu bisa
     * ditolak -- dan sebelum perbaikan ini barisnya sudah terlanjur ada:
     * dokumen temuan yang mengaku menambah stok padahal tidak ada satu pun
     * pergerakan yang tercatat.
     *
     * Ini kerusakan yang dibawa oleh perbaikan kemarin, bukan warisan lama.
     */
    public function test_a_finding_is_refused_whole_while_a_count_runs(): void
    {
        $this->opname();

        try {
            $this->temuan(5);
            $this->fail('Temuan seharusnya ditolak selama opname berjalan.');
        } catch (\Illuminate\Validation\ValidationException) {
            // memang disengaja
        }

        $this->assertSame(0, MaterialFinding::count(), 'Dokumen temuan tertinggal tanpa pergerakan stok.');
        $this->assertSame(0, MaterialStockMovement::count());
    }

    /** Penghapusan pun ditolak utuh, bukan setengah jalan. */
    public function test_deleting_a_finding_is_refused_whole_while_a_count_runs(): void
    {
        $temuan = $this->temuan(5);

        $this->opname();

        try {
            $temuan->delete();
            $this->fail('Penghapusan seharusnya ditolak selama opname berjalan.');
        } catch (\Illuminate\Validation\ValidationException) {
            // memang disengaja
        }

        $this->assertSame(1, MaterialFinding::count(), 'Dokumennya hilang tanpa mengembalikan stok.');
        $this->assertEqualsWithDelta(
            5,
            (float) MaterialStock::where('material_id', $this->material->id)->first()->qty,
            0.001,
        );
    }

    // =====================================================================
    // Penomoran dokumen
    // =====================================================================

    /**
     * Nomor dokumen yang sudah dipakai tidak boleh dipakai ulang.
     *
     * Penomoran lamanya membaca dokumen TERAKHIR MENURUT ID pada tanggal yang
     * sama lalu memungut empat digit terakhirnya. Model ini tidak memakai
     * hapus lunak, jadi menghapus satu temuan membebaskan nomornya -- dan
     * nomor itu dipakai lagi oleh temuan berikutnya, sementara catatan
     * pergerakan yang lama masih menyebutnya sebagai acuan.
     */
    public function test_a_deleted_finding_keeps_its_number_reserved(): void
    {
        $pertama = $this->temuan(3);
        $nomorPertama = $pertama->document_number;

        $kedua = $this->temuan(4);

        $this->assertNotSame($nomorPertama, $kedua->document_number);

        $kedua->delete();

        $ketiga = $this->temuan(2);

        $this->assertNotSame(
            $kedua->document_number,
            $ketiga->document_number,
            'Nomor dokumen temuan yang sudah dihapus dipakai ulang.',
        );
    }

    public function test_the_number_follows_the_document_date(): void
    {
        $temuan = $this->temuan(1, '2026-09-06');

        $this->assertStringStartsWith('FND-MTR-260906-', $temuan->document_number);
    }

    // =====================================================================
    // Izin
    // =====================================================================

    /**
     * Mencatat temuan butuh izinnya sendiri.
     *
     * Modul ini tidak punya policy dan tidak punya satu pun izin. Laravel
     * mengizinkan apa saja ketika sebuah model tidak punya policy, jadi siapa
     * pun yang bisa membuka rumpun Materials Stock bisa menambah stok bahan
     * sebanyak apa pun.
     */
    public function test_recording_a_finding_needs_its_own_permission(): void
    {
        $this->assertTrue(
            Permission::where('name', 'record_material_findings')->exists(),
            'Izin `record_material_findings` tidak pernah dibuat.',
        );

        $pegawai = User::create([
            'name' => 'Gudang', 'username' => 'gudang_temuan_mtr',
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        $pegawai->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'view_material_stocks'],
                ['module_name' => 'Material Stocks', 'description' => 'View material stocks'],
            )->id
        );

        // Boleh melihat stok bahan, tetapi TIDAK boleh mencetaknya.
        $this->actingAs($pegawai->fresh());
        $this->assertFalse(MaterialFindingResource::canViewAny());
        $this->assertFalse(MaterialFindingResource::canCreate());

        $pegawai->permissions()->attach(
            Permission::where('name', 'record_material_findings')->first()->id
        );

        $this->actingAs($pegawai->fresh());
        $this->assertTrue(MaterialFindingResource::canViewAny());
        $this->assertTrue(MaterialFindingResource::canCreate());
    }

    /** Temuan tidak boleh disunting: stoknya sudah terlanjur bergerak. */
    public function test_a_finding_can_never_be_edited(): void
    {
        $this->assertFalse(MaterialFindingResource::canEdit($this->temuan()));
    }

    /** Jumlahnya bilangan bulat, sama dengan isian hitungan opname. */
    public function test_the_quantity_is_a_whole_number(): void
    {
        $berkas = file_get_contents(base_path(
            'app/Filament/Clusters/MaterialsStock/Resources/MaterialFindingResource.php'
        ));

        $this->assertStringContainsString('->integer()', $berkas);
        $this->assertStringNotContainsString('->step(0.01)', $berkas);
    }

    protected function tearDown(): void
    {
        MaterialStockFreezeService::$bypassed = false;

        parent::tearDown();
    }
}
