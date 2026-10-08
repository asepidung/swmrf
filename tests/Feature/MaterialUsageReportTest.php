<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MaterialUsageReport;
use App\Models\Boning;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\ProductionBomSnapshot;
use App\Models\Repack;
use App\Models\User;
use App\Services\ProductionMaterialReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Issue #509, langkah 5b: laporan pemakaian bahan per periode untuk atasan.
 *
 * Hanya Boning/Repack yang TERKUNCI yang dihitung. Dokumennya disusun langsung
 * (kunci, snapshot, baris terbuang bernilai) -- cara kerja Lock sendiri diuji di
 * BomUsageTest dan MaterialWasteTest.
 */
class MaterialUsageReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Material $karton;

    private Material $plastik;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer', 'username' => 'prog_'.uniqid(), 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'programmer', 'is_active' => true,
        ]);
        $this->actingAs($this->user);

        $category = MaterialCategory::firstOrCreate(['name' => 'PACKAGING']);
        $unit = MaterialUnit::firstOrCreate(['name' => 'PCS']);

        $this->karton = Material::create(['name' => 'KARTON TOP', 'material_category_id' => $category->id, 'material_unit_id' => $unit->id, 'min_stock' => 0, 'is_active' => true]);
        $this->plastik = Material::create(['name' => 'PLASTIK VAKUM', 'material_category_id' => $category->id, 'material_unit_id' => $unit->id, 'min_stock' => 0, 'is_active' => true]);
    }

    /**
     * Boning (atau Repack) yang sudah dibekukan: snapshot BOM, drylog, dan satu
     * baris terbuang bernilai.
     */
    private function document(string $class, string $date, bool $locked = true, int $karton = 2, int $drylog = 2, int $wasteQty = 3, float $wasteAmount = 3000): Boning|Repack
    {
        $column = $class === Boning::class ? 'boning_date' : 'repack_date';

        $document = $class::create([$column => $date, 'created_by' => $this->user->id]);
        $document->forceFill(['drylog_qty' => $drylog, 'drylog_amount' => $locked ? 100 * $drylog : null])->save();

        ProductionBomSnapshot::create(['snapshotable_type' => $class, 'snapshotable_id' => $document->id, 'material_id' => $this->karton->id, 'qty' => $karton]);

        $document->materialWastes()->create([
            'material_id' => $this->plastik->id, 'qty' => $wasteQty, 'reason' => 'gagal vakum',
            'unit_price' => $locked ? $wasteAmount / max(1, $wasteQty) : null, 'amount' => $locked ? $wasteAmount : null,
        ]);

        if ($locked) {
            $document->forceFill(['kunci' => true, 'status' => 'LOCKED'])->save();
        }

        return $document->fresh();
    }

    private function between(string $from, string $until): array
    {
        return ProductionMaterialReport::between(Carbon::parse($from), Carbon::parse($until));
    }

    // ---------------------------------------------------------------------
    // Penjumlahan
    // ---------------------------------------------------------------------

    /** @test */
    public function it_sums_the_locked_documents_of_the_period_per_material(): void
    {
        $this->document(Boning::class, '2026-10-02', karton: 2, drylog: 2, wasteQty: 3, wasteAmount: 3000);
        $this->document(Boning::class, '2026-10-05', karton: 3, drylog: 3, wasteQty: 2, wasteAmount: 2000);

        $report = $this->between('2026-10-01', '2026-10-31');

        $this->assertSame([['material' => 'KARTON TOP', 'qty' => 5]], $report['bom']);
        $this->assertSame(['qty' => 5, 'amount' => 500.0], $report['drylog']);
        $this->assertSame([['material' => 'PLASTIK VAKUM', 'qty' => 5, 'amount' => 5000.0]], $report['wastes']);
        $this->assertSame(['qty' => 5, 'amount' => 5000.0], $report['waste_total']);
        $this->assertCount(2, $report['documents']);
    }

    /** @test */
    public function repack_documents_are_counted_with_the_bonings(): void
    {
        $this->document(Boning::class, '2026-10-02', karton: 2, wasteQty: 3, wasteAmount: 3000);
        $this->document(Repack::class, '2026-10-03', karton: 4, wasteQty: 1, wasteAmount: 1000);

        $report = $this->between('2026-10-01', '2026-10-31');

        $this->assertSame(6, (int) $report['bom'][0]['qty']);
        $this->assertSame(4, $report['waste_total']['qty']);
        $this->assertSame(['boning', 'repack'], array_column($report['documents'], 'kind'));
    }

    /** @test */
    public function unlocked_documents_and_documents_outside_the_period_are_left_out(): void
    {
        $this->document(Boning::class, '2026-10-02');
        $this->document(Boning::class, '2026-10-03', locked: false);   // belum terkunci
        $this->document(Boning::class, '2026-09-30');                  // sehari sebelum periode
        $this->document(Boning::class, '2026-11-01');                  // sehari sesudah periode

        $report = $this->between('2026-10-01', '2026-10-31');

        $this->assertCount(1, $report['documents']);
        $this->assertSame(2, (int) $report['bom'][0]['qty']);
    }

    /** @test */
    public function the_first_and_last_day_of_the_period_are_included(): void
    {
        $this->document(Boning::class, '2026-10-01');
        $this->document(Boning::class, '2026-10-31');

        $this->assertCount(2, $this->between('2026-10-01', '2026-10-31')['documents']);
    }

    /** @test */
    public function an_empty_period_gives_empty_totals(): void
    {
        $report = $this->between('2026-01-01', '2026-01-31');

        $this->assertSame([], $report['documents']);
        $this->assertSame([], $report['bom']);
        $this->assertSame(['qty' => 0, 'amount' => 0.0], $report['waste_total']);
    }

    /** @test */
    public function rows_without_a_price_are_counted_as_zero_and_reported(): void
    {
        $this->document(Boning::class, '2026-10-02', wasteQty: 4, wasteAmount: 0);

        $report = $this->between('2026-10-01', '2026-10-31');

        $this->assertSame(0.0, $report['waste_total']['amount']);
        $this->assertSame(1, $report['unpriced']);
    }

    // ---------------------------------------------------------------------
    // Halaman, izin, dan unduhan
    // ---------------------------------------------------------------------

    private function asUserWith(string ...$permissions): void
    {
        $user = User::create([
            'name' => 'Penguji', 'username' => 'uji_'.uniqid(), 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        foreach ($permissions as $permission) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $permission], ['module_name' => 'Test', 'description' => $permission])->id);
        }

        $this->actingAs($user->fresh());
    }

    /** @test */
    public function the_page_needs_the_material_usage_permission(): void
    {
        $this->asUserWith('view_products');
        $this->assertFalse(MaterialUsageReport::canAccess(), 'Halaman terbuka tanpa izin view_material_usages.');

        $this->asUserWith('view_material_usages');
        $this->assertTrue(MaterialUsageReport::canAccess());
    }

    /** @test */
    public function the_period_defaults_to_the_current_month_and_the_page_uses_it(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15'));
        $this->document(Boning::class, '2026-10-02', wasteAmount: 3000);
        $this->document(Boning::class, '2026-09-20', wasteAmount: 9000);

        $page = Livewire::test(MaterialUsageReport::class)
            ->assertSet('periodFrom', '2026-10-01')
            ->assertSet('periodUntil', '2026-10-15');

        $this->assertSame(1, count($page->instance()->getReport()['documents']));
        $page->assertSee('KARTON TOP')->assertSee('Rp 3.000')->assertDontSee('Rp 9.000');

        // Memperlebar periode ikut mengubah hitungannya.
        $page->set('periodFrom', '2026-09-01');
        $this->assertSame(2, count($page->instance()->getReport()['documents']));
    }

    /** @test */
    public function a_reversed_period_is_read_the_right_way_round(): void
    {
        $this->document(Boning::class, '2026-10-02');

        $page = Livewire::test(MaterialUsageReport::class)
            ->set('periodFrom', '2026-10-31')
            ->set('periodUntil', '2026-10-01');

        $this->assertSame(1, count($page->instance()->getReport()['documents']));
    }

    /** @test */
    public function excel_and_pdf_follow_the_chosen_period(): void
    {
        $this->document(Boning::class, '2026-10-02');

        Livewire::test(MaterialUsageReport::class)
            ->set('periodFrom', '2026-10-01')
            ->set('periodUntil', '2026-10-31')
            ->call('downloadExcel')
            ->assertFileDownloaded('Material_Usage_Report_2026-10-01_2026-10-31.xlsx');

        Livewire::test(MaterialUsageReport::class)
            ->set('periodFrom', '2026-10-01')
            ->set('periodUntil', '2026-10-31')
            ->call('downloadPdf')
            ->assertFileDownloaded('Material_Usage_Report_2026-10-01_2026-10-31.pdf');
    }
}
