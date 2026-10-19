<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedGroupsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_dry_run_writes_nothing(): void
    {
        $this->artisan('master:seed-groups')->expectsOutputToContain('dibuat: 3')->assertSuccessful();

        $this->assertSame(0, CustomerGroup::count());
    }

    /** @test */
    public function apply_creates_the_three_groups_and_is_safe_to_repeat(): void
    {
        $this->artisan('master:seed-groups', ['--apply' => true])->assertSuccessful();
        $this->artisan('master:seed-groups', ['--apply' => true])->expectsOutputToContain('dibuat: 0, sudah ada (dilewati): 3')->assertSuccessful();

        $this->assertSame(['KARYAWAN', 'WARGA +62', 'GRATISAN'], CustomerGroup::orderBy('id')->pluck('name')->all());
    }

    /** @test */
    public function an_existing_segment_written_differently_is_not_duplicated(): void
    {
        CustomerGroup::create(['name' => 'Warga  +62']);

        $this->artisan('master:seed-groups', ['--apply' => true])->expectsOutputToContain('dibuat: 2, sudah ada (dilewati): 1')->assertSuccessful();

        $this->assertSame(3, CustomerGroup::count());
    }
}
