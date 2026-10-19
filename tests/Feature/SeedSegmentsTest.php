<?php

namespace Tests\Feature;

use App\Models\CustomerSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedSegmentsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_dry_run_writes_nothing(): void
    {
        $this->artisan('master:seed-segments')->expectsOutputToContain('dibuat: 5')->assertSuccessful();

        $this->assertSame(0, CustomerSegment::count());
    }

    /** @test */
    public function apply_creates_the_five_segments_and_is_safe_to_repeat(): void
    {
        $this->artisan('master:seed-segments', ['--apply' => true])->assertSuccessful();
        $this->artisan('master:seed-segments', ['--apply' => true])->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 5')->assertSuccessful();

        $this->assertSame(['MODERN MARKET', 'HOREKA', 'DISTRIBUTOR', 'RETAIL', 'INDUSTRI'], CustomerSegment::orderBy('id')->pluck('name')->all());
    }

    /** @test */
    public function an_existing_segment_written_differently_is_not_duplicated(): void
    {
        CustomerSegment::create(['name' => 'Modern  Market']);

        $this->artisan('master:seed-segments', ['--apply' => true])->expectsOutputToContain('dibuat: 4, sudah ada (dilewati): 1')->assertSuccessful();

        $this->assertSame(5, CustomerSegment::count());
    }
}
