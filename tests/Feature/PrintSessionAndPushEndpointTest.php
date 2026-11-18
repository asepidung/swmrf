<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** A-04: cetakan memutus sesi lama saat kata sandi diganti. PS-01: alamat langganan push dibatasi. */
class PrintSessionAndPushEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function employee(): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true, 'password' => Hash::make('rahasia-kuat-123')]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_delivery_plans'], ['module_name' => 'Delivery Plans', 'description' => 'x'])->id
        );

        return $user->fresh();
    }

    /** @test */
    public function a_session_from_before_a_password_change_can_no_longer_open_a_printout(): void
    {
        $user = $this->employee();

        $this->actingAs($user)->get(route('print.delivery-plan.preview'))->assertOk();

        // Kata sandi diganti dari perangkat lain; sesi ini tidak ikut diperbarui.
        User::whereKey($user->id)->update(['password' => Hash::make('kata-sandi-baru-456')]);

        // Permintaan berikutnya memuat ulang pengguna dari basis data, seperti di dunia nyata.
        $this->actingAs($user->fresh())->get(route('print.delivery-plan.preview'))->assertRedirect();
        $this->assertGuest();
    }

    /** @test */
    public function push_endpoints_must_be_public_https_addresses(): void
    {
        $user = $this->employee();
        $badan = fn (string $endpoint) => ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'abc', 'auth' => 'def']];

        foreach (['http://fcm.googleapis.com/fcm/send/abc', 'https://127.0.0.1/x', 'https://localhost/x', 'https://10.0.0.5/x', 'https://intranet/x'] as $salah) {
            $this->actingAs($user)->postJson(route('push-subscriptions.store'), $badan($salah))->assertStatus(422);
        }

        $this->actingAs($user)->postJson(route('push-subscriptions.store'), $badan('https://fcm.googleapis.com/fcm/send/abc'))
            ->assertOk();
        $this->actingAs($user)->postJson(route('push-subscriptions.store'), $badan('https://updates.push.services.mozilla.com/wpush/v2/abc'))
            ->assertOk();
    }
}
