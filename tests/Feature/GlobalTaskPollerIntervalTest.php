<?php

namespace Tests\Feature;

use App\Livewire\GlobalTaskPoller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** PF-01 (keputusan Ayah): poller yang menjaga sesi hidup berjalan tiap 60 detik, bukan 10. */
class GlobalTaskPollerIntervalTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_poller_fires_every_sixty_seconds(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        $html = Livewire::actingAs($user)->test(GlobalTaskPoller::class)->html();

        $this->assertStringContainsString('wire:poll.60s="checkTasks"', $html);
        $this->assertStringNotContainsString('wire:poll.10s', $html);
    }
}
