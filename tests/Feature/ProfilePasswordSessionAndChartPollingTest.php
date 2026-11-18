<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MyProfile;
use App\Filament\Admin\Widgets\FastMovingChart;
use App\Filament\Admin\Widgets\SalesYearlyChart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** PR-01: ganti kata sandi di Profil tidak mengeluarkan pengguna sendiri. PF-08: grafik laporan tidak memanggil server tiap 5 detik. */
class ProfilePasswordSessionAndChartPollingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function changing_the_password_in_the_profile_updates_this_sessions_password_hash(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true, 'password' => Hash::make('sandi-lama-123')]);

        Livewire::actingAs($user)
            ->test(MyProfile::class)
            ->fillForm([
                'name' => $user->name, 'username' => $user->username,
                'current_password' => 'sandi-lama-123',
                'new_password' => 'sandi-baru-45678', 'new_password_confirmation' => 'sandi-baru-45678',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('sandi-baru-45678', $user->fresh()->password), 'Kontrol positif: kata sandi benar-benar berganti.');
        $this->assertSame($user->fresh()->getAuthPassword(), session('password_hash_web'));
    }

    /** @test */
    public function the_report_charts_do_not_poll_the_server(): void
    {
        foreach ([SalesYearlyChart::class, FastMovingChart::class] as $kelas) {
            $this->assertNull((new \ReflectionClass($kelas))->getProperty('pollingInterval')->getValue(), $kelas);
        }
    }
}
