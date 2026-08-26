<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Langganan Web Push harus bisa pulih sendiri.
 *
 * Sebuah langganan terikat pada applicationServerKey yang dipakai saat dibuat.
 * Begitu kunci VAPID dirotasi, langganan lama ditolak layanan push. Dulu
 * pengguna yang sudah menekan "Izinkan" tidak punya jalan keluar sama sekali,
 * karena tombol loncengnya menghilang setelah izin diberikan dan tidak ada
 * satu pun pemeriksaan terhadap langganan yang sebenarnya ada di browser.
 *
 * Gejalanya menyesatkan: barisnya tetap ada di database, penghitung di
 * Dashboard tetap menampilkan angka yang sehat, dan satu-satunya jejak
 * kegagalan cuma satu baris di laravel.log.
 */
class PushSubscriptionSelfHealTest extends TestCase
{
    use RefreshDatabase;

    protected function subscribeView(): string
    {
        return file_get_contents(resource_path('views/push/subscribe.blade.php'));
    }

    /** @test */
    public function it_checks_the_real_subscription_when_the_page_opens()
    {
        $view = $this->subscribeView();

        $this->assertStringContainsString('this.ensureSubscription()', $view);
        $this->assertStringContainsString('pushManager.getSubscription()', $view);
    }

    /**
     * Membaca Notification.permission saja tidak cukup: izin bisa tetap
     * "granted" sementara langganannya sudah tidak berlaku.
     *
     * @test
     */
    public function it_rebuilds_the_subscription_when_the_vapid_key_changed()
    {
        $view = $this->subscribeView();

        $this->assertStringContainsString('this.rememberedKey() !== vapidPublicKey', $view);
        $this->assertStringContainsString('subscription.unsubscribe()', $view);
    }

    /**
     * Kalau pemulihannya gagal, loncengnya harus muncul lagi supaya pengguna
     * punya cara mencoba ulang. Tanpa ini dia terkunci diam-diam.
     *
     * @test
     */
    public function it_brings_the_bell_back_when_the_repair_fails()
    {
        $this->assertStringContainsString(
            "x-show=\"supported && (state !== 'granted' || failed)\"",
            $this->subscribeView(),
        );
    }

    /**
     * Pemulihan berjalan tanpa melibatkan pengguna, jadi izin TIDAK boleh
     * diminta ulang di sana. Browser cuma memberi satu kesempatan: sekali
     * pengguna menekan "Blokir", tidak ada cara memintanya lagi dari kode.
     *
     * @test
     */
    public function it_never_asks_for_permission_while_repairing()
    {
        $view = $this->subscribeView();

        $repair = substr(
            $view,
            strpos($view, 'async ensureSubscription()'),
            strpos($view, 'async store(subscription)') - strpos($view, 'async ensureSubscription()'),
        );

        $this->assertStringNotContainsString('requestPermission', $repair);
    }

    /**
     * Pemulihan mengirim ulang langganan setiap kali halaman dibuka, jadi
     * endpoint-nya wajib idempoten. Kalau tidak, satu perangkat menumpuk
     * puluhan baris dan setiap notifikasi terkirim berkali-kali.
     *
     * @test
     */
    public function it_stores_the_same_device_only_once()
    {
        $user = User::create([
            'name' => 'Ruby',
            'username' => 'push_self_heal_ruby',
            'password' => 'secret-password',
            'gender' => 'P',
            'role' => 'employee',
            'is_active' => true,
        ]);

        $payload = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/contoh-endpoint',
            'keys' => ['p256dh' => str_repeat('a', 87), 'auth' => str_repeat('b', 22)],
            'content_encoding' => 'aes128gcm',
        ];

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)
                ->postJson(route('push-subscriptions.store'), $payload)
                ->assertOk();
        }

        $this->assertSame(1, $user->pushSubscriptions()->count());
    }

    /** @test */
    public function it_keeps_a_second_device_as_its_own_subscription()
    {
        $user = User::create([
            'name' => 'Ruby',
            'username' => 'push_self_heal_ruby_two',
            'password' => 'secret-password',
            'gender' => 'P',
            'role' => 'employee',
            'is_active' => true,
        ]);

        foreach (['hp', 'komputer'] as $device) {
            $this->actingAs($user)
                ->postJson(route('push-subscriptions.store'), [
                    'endpoint' => 'https://fcm.googleapis.com/fcm/send/' . $device,
                    'keys' => ['p256dh' => str_repeat('a', 87), 'auth' => str_repeat('b', 22)],
                    'content_encoding' => 'aes128gcm',
                ])
                ->assertOk();
        }

        $this->assertSame(2, $user->pushSubscriptions()->count());
    }
}
