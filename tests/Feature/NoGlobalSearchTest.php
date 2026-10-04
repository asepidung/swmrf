<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kolom search di topbar (global search bawaan Filament) dimatikan --
 * keputusan Owner 5 Oktober 2026 (issue #497): "gak guna". Pencarian tetap
 * ada di tiap tabel.
 */
class NoGlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_panel_has_no_global_search_provider(): void
    {
        $this->assertNull(Filament::getPanel('admin')->getGlobalSearchProvider());
    }

    public function test_the_topbar_does_not_render_the_search_field(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $html = $this->actingAs($user)->get('/admin')->assertSuccessful()->getContent();

        $this->assertStringNotContainsString('fi-global-search', $html);
    }
}
