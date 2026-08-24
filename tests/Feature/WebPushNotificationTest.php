<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use App\Notifications\TaskAlert;
use App\Support\TaskNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

/**
 * Notifikasi tugas antar-peran lewat Web Push.
 *
 * Yang paling mudah salah dan paling mahal akibatnya: notifikasi dikirim ke
 * orang yang tidak berlangganan (sia-sia), ke pelakunya sendiri (mengganggu),
 * atau kegagalan push menggagalkan aksi bisnis yang memicunya.
 */
class WebPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $username, string $role = 'employee', array $permissions = [], bool $subscribed = true): User
    {
        $user = User::create([
            'name' => ucfirst($username),
            'username' => $username,
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => $role,
            'is_active' => true,
        ]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['module_name' => 'Test', 'description' => $name]
            );
            $user->permissions()->attach($permission->id);
        }

        if ($subscribed) {
            $user->updatePushSubscription(
                'https://push.example.test/' . $username,
                'p256dh-' . $username,
                'auth-' . $username
            );
        }

        return $user;
    }

    /** @test */
    public function it_sends_the_alert_to_permission_holders()
    {
        Notification::fake();

        $reviewer = $this->makeUser('reviewer', 'employee', ['review_product_requisitions']);

        $sent = TaskNotifier::notifyPermissionHolders(
            'review_product_requisitions',
            'Judul',
            'Isi',
            '/admin'
        );

        $this->assertSame(1, $sent);
        Notification::assertSentTo($reviewer, TaskAlert::class);
    }

    /** @test */
    public function it_skips_users_without_the_permission()
    {
        Notification::fake();

        $outsider = $this->makeUser('outsider', 'employee', []);

        TaskNotifier::notifyPermissionHolders('review_product_requisitions', 'Judul', 'Isi', '/admin');

        Notification::assertNotSentTo($outsider, TaskAlert::class);
    }

    /**
     * Programmer selalu ikut menerima, mengikuti perilaku hasPermission().
     *
     * @test
     */
    public function it_always_includes_programmers()
    {
        Notification::fake();

        $programmer = $this->makeUser('programmer_push', 'programmer', []);

        TaskNotifier::notifyPermissionHolders('review_product_requisitions', 'Judul', 'Isi', '/admin');

        Notification::assertSentTo($programmer, TaskAlert::class);
    }

    /**
     * Tanpa langganan, notifikasi tidak akan sampai ke mana pun. Menyaringnya
     * lebih dulu menghindari percobaan kirim yang sia-sia.
     *
     * @test
     */
    public function it_skips_permission_holders_who_never_subscribed()
    {
        Notification::fake();

        $unsubscribed = $this->makeUser('unsubscribed', 'employee', ['review_product_requisitions'], subscribed: false);

        $sent = TaskNotifier::notifyPermissionHolders('review_product_requisitions', 'Judul', 'Isi', '/admin');

        $this->assertSame(0, $sent);
        Notification::assertNotSentTo($unsubscribed, TaskAlert::class);
    }

    /** @test */
    public function it_does_not_notify_the_person_who_triggered_the_action()
    {
        Notification::fake();

        $actor = $this->makeUser('actor', 'employee', ['review_product_requisitions']);
        $other = $this->makeUser('other', 'employee', ['review_product_requisitions']);

        TaskNotifier::notifyPermissionHolders(
            'review_product_requisitions',
            'Judul',
            'Isi',
            '/admin',
            null,
            $actor->id
        );

        Notification::assertNotSentTo($actor, TaskAlert::class);
        Notification::assertSentTo($other, TaskAlert::class);
    }

    /** @test */
    public function it_skips_inactive_users()
    {
        Notification::fake();

        $inactive = $this->makeUser('inactive', 'employee', ['review_product_requisitions']);
        $inactive->update(['is_active' => false]);

        TaskNotifier::notifyPermissionHolders('review_product_requisitions', 'Judul', 'Isi', '/admin');

        Notification::assertNotSentTo($inactive, TaskAlert::class);
    }

    /** @test */
    public function it_routes_the_alert_through_the_web_push_channel_only()
    {
        $alert = new TaskAlert('Judul', 'Isi', '/admin');

        $this->assertSame([WebPushChannel::class], $alert->via(new User()));
    }

    /**
     * Tanpa ShouldQueue. QUEUE_CONNECTION masih sync dan tidak ada queue worker
     * di shared hosting, sehingga notifikasi yang diantre justru menumpuk
     * diam-diam tanpa error.
     *
     * @test
     */
    public function it_is_deliberately_not_queued()
    {
        $this->assertNotInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            new TaskAlert('Judul', 'Isi', '/admin'),
            'TaskAlert sengaja tidak di-queue selama belum ada queue worker.'
        );
    }

    /**
     * Angka ini yang mencegah kita mengira fiturnya jalan padahal tidak.
     *
     * @test
     */
    public function it_reports_how_many_active_users_actually_subscribed()
    {
        $this->makeUser('sub_one', 'employee', [], subscribed: true);
        $this->makeUser('sub_two', 'employee', [], subscribed: true);
        $this->makeUser('no_sub', 'employee', [], subscribed: false);

        $coverage = TaskNotifier::subscriptionCoverage();

        $this->assertSame(2, $coverage['subscribed']);
        $this->assertSame(3, $coverage['total']);
    }

    /** @test */
    public function it_renders_the_subscription_coverage_widget()
    {
        $programmer = $this->makeUser('widget_viewer', 'programmer', [], subscribed: true);
        $this->makeUser('widget_other', 'employee', [], subscribed: false);

        \Livewire\Livewire::actingAs($programmer)
            ->test(\App\Filament\Admin\Widgets\PushSubscriptionCoverageWidget::class)
            ->assertSuccessful()
            ->assertSee('1 / 2');
    }

    /** @test */
    public function it_hides_the_coverage_widget_from_users_who_do_not_oversee_the_system()
    {
        $employee = $this->makeUser('plain_employee', 'employee', [], subscribed: false);

        $this->actingAs($employee);

        $this->assertFalse(\App\Filament\Admin\Widgets\PushSubscriptionCoverageWidget::canView());
    }

    /**
     * Notifikasi TIDAK BOLEH menggagalkan aksi bisnis yang memicunya. Kalau
     * layanan push bermasalah, dokumen tetap harus tersimpan.
     *
     * @test
     */
    public function it_never_lets_a_failing_push_break_the_business_action()
    {
        $this->makeUser('reviewer_fail', 'employee', ['review_product_requisitions']);

        Notification::shouldReceive('send')->never();
        Notification::swap(new class extends \Illuminate\Support\Testing\Fakes\NotificationFake
        {
            public function send($notifiables, $notification, ?array $channels = null): void
            {
                throw new \RuntimeException('Layanan push sedang mati');
            }
        });

        $sent = TaskNotifier::notifyPermissionHolders('review_product_requisitions', 'Judul', 'Isi', '/admin');

        $this->assertSame(0, $sent, 'Kegagalan push harus ditelan, bukan dilempar ke atas.');
    }
}
