<?php

namespace Tests\Feature;

use App\Filament\Clusters\MaterialsStock\Resources\MaterialFindingResource\Pages\ManageMaterialFindings;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialFinding;
use App\Models\MaterialStockMovement;
use App\Models\MaterialUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Susulan penyisiran cluster Material Stock, 15 September 2026 (Temuan 7-11).
 *
 * Temuan 12 (dua Filament Exporter kode mati) tidak butuh test -- berkasnya
 * dihapus, tidak ada perilaku baru untuk dibuktikan.
 */
class MaterialStockClusterSusulanTest extends TestCase
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
            'min_stock' => 10,
            'show_in_stock' => true,
        ]);
    }

    // =====================================================================
    // Temuan 7 -- MaterialFinding tidak punya jejak audit
    // =====================================================================

    /** @test */
    public function creating_a_material_finding_is_logged(): void
    {
        $finding = MaterialFinding::create([
            'date' => now()->toDateString(),
            'material_id' => $this->material->id,
            'qty' => 5,
            'note' => 'ditemukan di rak',
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue(
            Activity::where('subject_type', MaterialFinding::class)
                ->where('subject_id', $finding->id)
                ->where('event', 'created')
                ->exists(),
        );
    }

    // =====================================================================
    // Temuan 8 -- filter tanggal Temuan Material harus default bulan berjalan
    // =====================================================================

    /** @test */
    public function the_finding_listing_hides_last_months_row_by_default_but_shows_it_when_widened(): void
    {
        $lama = MaterialFinding::create([
            'date' => now()->subMonth()->startOfMonth()->toDateString(),
            'material_id' => $this->material->id,
            'qty' => 3,
            'note' => 'temuan lama',
            'created_by' => $this->user->id,
        ]);

        $baru = MaterialFinding::create([
            'date' => now()->toDateString(),
            'material_id' => $this->material->id,
            'qty' => 7,
            'note' => 'temuan baru',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageMaterialFindings::class)
            ->assertSee($baru->document_number)
            ->assertDontSee($lama->document_number);

        Livewire::actingAs($this->user)
            ->test(ManageMaterialFindings::class)
            ->set('tableFilters.created_at.created_from', now()->subMonths(2)->format('Y-m-d'))
            ->assertSee($baru->document_number)
            ->assertSee($lama->document_number);
    }

    // =====================================================================
    // Temuan 9 -- badge warna pergerakan material harus kenal semua 9 jenis
    // =====================================================================

    /** @test */
    public function every_real_movement_type_gets_a_meaningful_color_not_gray(): void
    {
        $this->assertSame('success', MaterialStockMovement::typeColor('GR'));
        $this->assertSame('out', MaterialStockMovement::TYPES['RETUR']);
        $this->assertSame('danger', MaterialStockMovement::typeColor('RETUR'));
        $this->assertSame('warning', MaterialStockMovement::typeColor('ADJUSTMENT'));

        // Sebelum diperbaiki, keempat ini SELALU abu-abu -- padahal
        // mayoritas pergerakan sungguhan (pemakaian material, opname,
        // temuan) justru berasal dari sini.
        $this->assertSame('danger', MaterialStockMovement::typeColor('MATERIAL_USAGE'));
        $this->assertSame('success', MaterialStockMovement::typeColor('MATERIAL_USAGE_REVERT'));
        $this->assertSame('warning', MaterialStockMovement::typeColor('MATERIAL_USAGE_ADJUST'));
        $this->assertSame('warning', MaterialStockMovement::typeColor('STOCK_TAKE_ADJUSTMENT'));
        $this->assertSame('success', MaterialStockMovement::typeColor('TEMUAN MATERIAL'));
        $this->assertSame('danger', MaterialStockMovement::typeColor('PEMBATALAN TEMUAN MATERIAL'));

        $this->assertSame('gray', MaterialStockMovement::typeColor('TIDAK_DIKENAL'));
    }
}
