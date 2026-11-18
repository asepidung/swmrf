<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ForceChangePassword;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** A-03 (keputusan Ayah, 18 November 2026): reset kata sandi menghasilkan kata sandi sementara ACAK, bukan 1234, dan tetap wajib diganti. */
class ResetPasswordTemporaryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function resetting_a_password_gives_a_random_temporary_one_that_must_be_changed(): void
    {
        $admin = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'employee', 'is_active' => true, 'password' => Hash::make('sandi-lama-123')]);

        Livewire::actingAs($admin)->test(ListUsers::class)->callTableAction('reset_password', $target);

        $target->refresh();
        $this->assertFalse(Hash::check('1234', $target->password), 'Tidak boleh lagi 1234.');
        $this->assertFalse(Hash::check('sandi-lama-123', $target->password), 'Kontrol positif: kata sandi memang berganti.');
        $this->assertTrue($target->must_change_password, 'Wajib diganti saat login berikutnya.');
    }

    /** @test */
    public function a_user_flagged_to_change_is_sent_to_the_change_page_and_the_flag_clears_afterwards(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true, 'password' => Hash::make('Sementara-9xk2'), 'must_change_password' => true]);

        $this->actingAs($user)
            ->get(route('filament.admin.pages.dashboard'))
            ->assertRedirect(route('filament.admin.pages.force-change-password'));

        Livewire::actingAs($user)
            ->test(ForceChangePassword::class)
            ->set('data.new_password', 'Sandi-baru-4567')
            ->set('data.new_password_confirmation', 'Sandi-baru-4567')
            ->call('changePassword');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Sandi-baru-4567', $user->password));
    }

    /** @test */
    public function an_account_on_the_old_default_1234_is_still_forced_to_change(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true, 'password' => Hash::make('1234')]);

        $this->actingAs($user)
            ->get(route('filament.admin.pages.dashboard'))
            ->assertRedirect(route('filament.admin.pages.force-change-password'));
    }
}
