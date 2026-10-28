<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pelatihan 28 Oktober 2026: peserta satu kantor memakai satu alamat internet, dan batas bawaan Filament (5
 * percobaan per alamat) mengunci semua orang yang masuk bersamaan. Batasnya kini per username.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $username): User
    {
        return User::factory()->create(['username' => $username, 'password' => 'secret-1234', 'role' => 'employee', 'is_active' => true]);
    }

    /** @test */
    public function failed_attempts_by_one_username_do_not_lock_out_another_on_the_same_address(): void
    {
        $this->user('candra');
        $this->user('arif');

        foreach (range(1, 6) as $i) {
            Livewire::test(Login::class)
                ->set('data.username', 'candra')
                ->set('data.password', 'salah')
                ->call('authenticate');
        }

        // Candra terkunci sementara, bahkan dengan sandi yang benar...
        Livewire::test(Login::class)
            ->set('data.username', 'candra')
            ->set('data.password', 'secret-1234')
            ->call('authenticate')
            ->assertNoRedirect();
        $this->assertGuest();

        // ...tetapi Arif, dari alamat yang sama, tetap bisa masuk.
        Livewire::test(Login::class)
            ->set('data.username', 'arif')
            ->set('data.password', 'secret-1234')
            ->call('authenticate')
            ->assertHasNoErrors();
        $this->assertAuthenticated();
    }

    /** @test */
    public function a_successful_login_clears_the_counter_for_that_username(): void
    {
        $this->user('arif');

        foreach (range(1, 3) as $i) {
            Livewire::test(Login::class)->set('data.username', 'arif')->set('data.password', 'salah')->call('authenticate');
        }

        Livewire::test(Login::class)->set('data.username', 'arif')->set('data.password', 'secret-1234')->call('authenticate');

        $this->assertSame(0, RateLimiter::attempts('login-user:'.sha1('arif|127.0.0.1')));
    }
}
