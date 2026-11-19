<?php

namespace Tests\Feature;

use App\Console\Concerns\RefusesApplyInProduction;
use Tests\TestCase;

/** CMD-01: perintah impor go-live tidak boleh menulis (--apply) di produksi. */
class LegacyImportProductionGuardTest extends TestCase
{
    public function test_every_one_time_import_command_carries_the_guard(): void
    {
        $tanpa = [];
        $awalan = 'App\\Console\\Commands\\';

        foreach (glob(app_path('Console/Commands/{ImportLegacy*,ImportAccessData,ImportMasterSnapshot,ImportCostingPrices,AssignLegacyCreator,LinkLegacyDeliveryPlans}.php'), GLOB_BRACE) as $berkas) {
            $kelas = $awalan.basename($berkas, '.php');

            if (! in_array(RefusesApplyInProduction::class, class_uses($kelas), true)) {
                $tanpa[] = $kelas;
            }
        }

        $this->assertSame([], $tanpa, 'Tanpa pengaman produksi: '.implode(', ', $tanpa));
    }

    public function test_apply_is_refused_in_production_but_a_dry_run_is_not(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('legacy:import-stock', ['--apply' => true])
            ->expectsOutputToContain('Ditolak')
            ->assertFailed();

        // Dry-run tidak ditolak oleh pengaman (di sini gagal karena --source kosong, pesan lain).
        $this->artisan('legacy:import-stock')
            ->doesntExpectOutputToContain('Ditolak')
            ->assertFailed();
    }

    public function test_apply_is_not_refused_outside_production(): void
    {
        // Gagal karena --source kosong, bukan karena pengaman.
        $this->artisan('legacy:import-stock', ['--apply' => true])
            ->doesntExpectOutputToContain('Ditolak')
            ->assertFailed();
    }
}
