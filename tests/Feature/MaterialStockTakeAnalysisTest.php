<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStockTake;
use App\Models\MaterialStockTakeItem;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\User;
use App\Support\MaterialStockTakeAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Laporan analisa opname material: disusun sistem dari data, hanya membaca, hanya setelah COMPLETED. */
class MaterialStockTakeAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private MaterialStockTake $opname;

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = MaterialCategory::create(['name' => 'KERTAS']);
        $unit = MaterialUnit::create(['name' => 'RIM']);

        $this->opname = MaterialStockTake::create([
            'document_number' => 'MST#26001', 'period' => 'Oktober 2026',
            'date' => '2026-11-05', 'status' => MaterialStockTake::STATUS_COMPLETED,
            'created_by' => User::factory()->create()->id,
        ]);

        $baris = function (string $kode, string $nama, float $sistem, ?float $fisik) use ($kategori, $unit) {
            $m = Material::create([
                'code' => $kode, 'name' => $nama, 'material_category_id' => $kategori->id,
                'material_unit_id' => $unit->id, 'min_stock' => 0, 'show_in_stock' => true,
            ]);

            return MaterialStockTakeItem::create([
                'material_stock_take_id' => $this->opname->id, 'material_id' => $m->id,
                'system_qty' => $sistem, 'physical_qty' => $fisik,
                'difference_qty' => $fisik === null ? null : $fisik - $sistem,
            ]);
        };

        $baris('M1', 'AMPLOP', 10, 10);   // sesuai
        $baris('M2', 'BOLPEN', 20, 15);   // kurang 5
        $baris('M3', 'CAP', 5, 8);        // lebih 3
        $baris('M4', 'DUS', 7, null);     // belum dihitung
    }

    private function pemeriksa(array $izin = ['view_material_stock_takes']): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        foreach ($izin as $name) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $name], ['module_name' => 'Material Stock Take', 'description' => $name])->id);
        }

        return $user->fresh();
    }

    public function test_angka_dihitung_dari_data(): void
    {
        $a = MaterialStockTakeAnalysis::for($this->opname);

        $this->assertSame(4, $a['total']);
        $this->assertSame(3, $a['counted']);
        $this->assertSame(1, $a['not_counted']->count());
        $this->assertSame(1, $a['matches']->count());
        $this->assertSame('BOLPEN', $a['short']->first()['name']);
        $this->assertSame('CAP', $a['over']->first()['name']);
        $this->assertEqualsWithDelta(-5, $a['short']->first()['diff'], 0.001);
    }

    public function test_riwayat_hitung_ulang_ikut_dilaporkan(): void
    {
        $item = $this->opname->items()->whereHas('material', fn ($q) => $q->where('code', 'M2'))->first();
        activity()->performedOn($item)->withProperties(['previous_physical_qty' => 12, 'note' => 'Cek rak belakang'])->log('Recount requested');

        $a = MaterialStockTakeAnalysis::for($this->opname);

        $this->assertSame(1, $a['recounts']->count());
        $this->assertSame('Cek rak belakang', $a['recounts']->first()['note']);
    }

    public function test_halaman_laporan_tampil_untuk_yang_berizin(): void
    {
        $this->actingAs($this->pemeriksa())->get(route('material-stock-take.analysis', $this->opname->id))
            ->assertOk()
            ->assertSee('Analisa Hasil Opname Material MST#26001')
            ->assertSee('BOLPEN')
            ->assertSee('belum dihitung')
            ->assertSee('Lampiran Detail');
    }

    public function test_belum_selesai_ditolak_karena_hitungan_buta(): void
    {
        $this->opname->update(['status' => MaterialStockTake::STATUS_REVIEW]);

        $this->actingAs($this->pemeriksa())->get(route('material-stock-take.analysis', $this->opname->id))->assertForbidden();
    }

    public function test_tanpa_izin_ditolak(): void
    {
        $this->actingAs($this->pemeriksa([]))->get(route('material-stock-take.analysis', $this->opname->id))->assertForbidden();
    }

    public function test_tanpa_selisih_laporan_tidak_mengarang_cerita(): void
    {
        $this->opname->items()->update(['physical_qty' => 1, 'system_qty' => 1, 'difference_qty' => 0]);

        $this->actingAs($this->pemeriksa())->get(route('material-stock-take.analysis', $this->opname->id))
            ->assertOk()
            ->assertSee('Tidak ada selisih')
            ->assertDontSee('Selisih terbesar')
            ->assertDontSee('Barang yang selisih');
    }
}
