<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\QcReportResource;
use App\Filament\Admin\Resources\QcReportResource\Actions\LihatLaporanQc;
use App\Models\CattleClass;
use App\Models\Permission;
use App\Models\QcReport;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keputusan Owner, 7 September 2026: laporan QC yang menebeng modul lain
 * (Carcass, Boning, dst) HANYA untuk melihat, dan hanya muncul kalau sudah
 * diisi -- pengisian/penyuntingannya lewat menu QC > QC Report saja.
 *
 * Sebelumnya `LihatLaporanQc` sudah tampil begitu baris `qc_reports` ADA
 * (termasuk draft kosong yang baru lahir otomatis), dan mengarah ke `edit`
 * kalau belum diisi -- itu pintu masuk pengisian dari luar menu QC yang
 * sekarang ditutup.
 */
class QcReportCompanionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewer = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->viewer->permissions()->attach(Permission::where('name', 'view_qc_reports')->firstOrFail()->id);
    }

    private function warehouse(): Warehouse
    {
        return Warehouse::create(['code' => 'GD1', 'name' => 'GUDANG 1', 'is_active' => true]);
    }

    /** Dokumen pendamping mana pun cukup -- CattleClass dipakai di sini murni sebagai model biasa, BUKAN dokumen QC. */
    private function draftReport(): QcReport
    {
        $warehouse = $this->warehouse();

        return QcReport::create([
            'reportable_type' => Warehouse::class,
            'reportable_id' => $warehouse->id,
        ]);
    }

    public function test_the_button_stays_hidden_while_the_report_is_still_a_blank_draft(): void
    {
        $laporan = $this->draftReport();
        $dokumen = $laporan->reportable;

        $this->actingAs($this->viewer);

        $action = LihatLaporanQc::make()->record($dokumen);

        $this->assertFalse($laporan->fresh()->sudahDiisi());
        $this->assertFalse($action->isVisible(), 'Tombol tampil padahal laporannya masih draft kosong.');
    }

    public function test_the_button_appears_and_always_points_to_view_once_submitted(): void
    {
        $laporan = $this->draftReport();
        $laporan->update(['note' => 'Berjalan baik.', 'occurred_at' => now(), 'submitted_at' => now()]);
        $dokumen = $laporan->reportable;

        $this->actingAs($this->viewer);

        $action = LihatLaporanQc::make()->record($dokumen);

        $this->assertTrue($action->isVisible(), 'Tombol tidak tampil padahal laporannya sudah diisi.');
        $this->assertSame(
            QcReportResource::getUrl('view', ['record' => $laporan]),
            $action->getUrl(),
            'Tombol tidak mengarah ke halaman View.',
        );
    }

    public function test_the_button_is_hidden_without_the_permission_even_when_submitted(): void
    {
        $laporan = $this->draftReport();
        $laporan->update(['note' => 'Berjalan baik.', 'occurred_at' => now(), 'submitted_at' => now()]);
        $dokumen = $laporan->reportable;

        $tanpaIzin = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($tanpaIzin);

        $action = LihatLaporanQc::make()->record($dokumen);

        $this->assertFalse($action->isVisible());
    }
}
