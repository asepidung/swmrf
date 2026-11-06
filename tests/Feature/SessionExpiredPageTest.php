<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Sesi habis: halaman 419 dan kotak pengganti dialog bawaan peramban. */
class SessionExpiredPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_419_memakai_tampilan_sendiri_dengan_tombol_masuk_lagi(): void
    {
        Route::get('/_uji-419', fn () => abort(419));

        $this->get('/_uji-419')
            ->assertStatus(419)
            ->assertSee('Sesi Anda sudah berakhir')
            ->assertSee('Masuk lagi')
            ->assertSee(route('filament.admin.auth.login'), false);
    }

    public function test_kotak_sesi_habis_dipasang_untuk_yang_login_saja(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $this->actingAs($user)->get('/admin')->assertOk()->assertSee('id="sesi-habis"', false);

        auth()->logout();
        $this->get('/admin/login')->assertOk()->assertDontSee('id="sesi-habis"', false);
    }
}
