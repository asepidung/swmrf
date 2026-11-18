<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A-05 (sudah dikoreksi di audit): akun yang dinonaktifkan ditolak juga pada aksi Livewire di tab yang masih terbuka.
 *
 * Penahannya `Filament\Http\Middleware\Authenticate`, yang terdaftar persistent (jalan di tiap /livewire/update) dan
 * memakai `User::canAccessPanel()`. Tes ini menjaga kedua mata rantai itu supaya upgrade Filament tidak diam-diam memutusnya.
 */
class DeactivatedAccountLivewireGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_panel_authenticate_middleware_runs_on_every_livewire_request(): void
    {
        $this->assertContains(
            Authenticate::class,
            Livewire::getPersistentMiddleware(),
            'Authenticate harus persistent, kalau tidak akun nonaktif bisa terus bekerja di tab yang terbuka.'
        );
    }

    /** @test */
    public function a_deactivated_user_cannot_access_the_panel_but_an_active_one_can(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue(User::factory()->create(['role' => 'employee', 'is_active' => true])->canAccessPanel($panel));
        $this->assertFalse(User::factory()->create(['role' => 'employee', 'is_active' => false])->canAccessPanel($panel));
    }
}
