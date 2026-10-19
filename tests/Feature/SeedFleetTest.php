<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `master:seed-fleet`: data awal supir dan armada dari Owner (19 Oktober 2026).
 */
class SeedFleetTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_dry_run_writes_nothing(): void
    {
        $this->artisan('master:seed-fleet')
            ->expectsOutputToContain('DRY-RUN')
            ->expectsOutputToContain('dibuat: 6')
            ->assertSuccessful();

        $this->assertSame(0, Driver::count());
        $this->assertSame(0, Vehicle::count());
    }

    /** @test */
    public function apply_creates_two_drivers_and_four_vehicles_exactly_as_given(): void
    {
        $this->artisan('master:seed-fleet', ['--apply' => true])->assertSuccessful();

        $this->assertSame(['ACEP NURFADILLAH', 'RESKHA PERMANA'], Driver::orderBy('id')->pluck('name')->all());
        $this->assertSame(
            [['CANTER', 'F 8485 HD'], ['TRAGA', 'F 9385 FI'], ['L-300', 'F 8489 HV'], ['DUTRO', 'F 9201 F']],
            Vehicle::orderBy('id')->get(['vehicle_type', 'police_number'])->map(fn ($v) => [$v->vehicle_type, $v->police_number])->all(),
        );
        $this->assertSame(6, Driver::where('is_active', true)->count() + Vehicle::where('is_active', true)->count());
    }

    /** @test */
    public function running_it_twice_creates_nothing_new(): void
    {
        $this->artisan('master:seed-fleet', ['--apply' => true])->assertSuccessful();
        $this->artisan('master:seed-fleet', ['--apply' => true])
            ->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 6')
            ->assertSuccessful();

        $this->assertSame(2, Driver::count());
        $this->assertSame(4, Vehicle::count());
    }

    /** @test */
    public function an_existing_plate_written_differently_is_the_same_vehicle(): void
    {
        Vehicle::create(['vehicle_type' => 'CANTER', 'police_number' => 'F8485HD', 'is_active' => true]);
        Driver::create(['name' => 'acep nurfadillah', 'is_active' => true]);

        $this->artisan('master:seed-fleet', ['--apply' => true])
            ->expectsOutputToContain('dibuat: 4, sudah ada (dilewati): 2')
            ->assertSuccessful();

        $this->assertSame(1, Vehicle::where('vehicle_type', 'CANTER')->count());
        $this->assertSame(2, Driver::count());
    }

    /** @test */
    public function a_plate_used_by_a_different_type_is_a_conflict_and_is_not_overwritten(): void
    {
        Vehicle::create(['vehicle_type' => 'FUSO', 'police_number' => 'F 9385 FI', 'is_active' => true]);

        $this->artisan('master:seed-fleet', ['--apply' => true])
            ->expectsOutputToContain('plat sudah dipakai "FUSO"')
            ->assertFailed();

        $this->assertSame('FUSO', Vehicle::where('police_number', 'F 9385 FI')->value('vehicle_type'), 'Tidak ditimpa.');
    }
}
