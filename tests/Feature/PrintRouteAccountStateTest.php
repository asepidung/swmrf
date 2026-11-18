<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Route cetak di luar panel ikut menegakkan keadaan akun (audit pra-rilis 14 November 2026, A-02).
 *
 * `CheckActiveUser` dan `CheckPasswordChange` dulu hanya terpasang di panel Filament. Route cetak di
 * `routes/web.php` cuma memakai `auth`, jadi akun yang sudah dinonaktifkan tetapi sesinya masih hidup,
 * dan akun yang masih memakai kata sandi bawaan, tetap bisa membuka cetakan apa pun yang izinnya ia
 * pegang -- cukup dengan mengetik alamatnya.
 */
class PrintRouteAccountStateTest extends TestCase
{
    use RefreshDatabase;

    private function employee(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'employee', 'is_active' => true], $attributes));

        $user->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_delivery_plans'], ['module_name' => 'Delivery Plans', 'description' => 'x'])->id
        );

        return $user->fresh();
    }

    /** @test */
    public function an_active_user_with_the_permission_still_opens_the_printout(): void
    {
        $this->actingAs($this->employee())
            ->get(route('print.delivery-plan.preview'))
            ->assertOk();
    }

    /** @test */
    public function a_deactivated_user_with_a_live_session_cannot_open_a_printout(): void
    {
        $user = $this->employee();
        $this->actingAs($user);

        // Dinonaktifkan admin selagi sesinya masih hidup.
        User::whereKey($user->id)->update(['is_active' => false]);
        $user->refresh();

        $this->get(route('print.delivery-plan.preview'))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }

    /** @test */
    public function a_user_still_on_the_default_password_is_sent_to_change_it_first(): void
    {
        $this->actingAs($this->employee(['password' => Hash::make('1234')]))
            ->get(route('print.delivery-plan.preview'))
            ->assertRedirect(route('filament.admin.pages.force-change-password'));
    }
}
