<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TaskAlert;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\PushSubscription;
use Tests\TestCase;

/**
 * Notifikasi push yang gagal tidak boleh hilang tanpa jejak, dan tugas yang menunggu seseorang dikirim dengan
 * urgensi tinggi (tidak ditunda Doze di Android).
 */
class PushDeliveryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function failedEvent(int $status, string $reason): NotificationFailed
    {
        $user = User::factory()->create();
        $subscription = $user->pushSubscriptions()->create([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'public_key' => 'k',
            'auth_token' => 'a',
            'content_encoding' => 'aes128gcm',
        ]);

        $report = new MessageSentReport(
            new \GuzzleHttp\Psr7\Request('POST', $subscription->endpoint),
            new Response($status),
            false,
            $reason,
        );

        return new NotificationFailed($report, $subscription, new \NotificationChannels\WebPush\WebPushMessage());
    }

    /** @test */
    public function a_failed_push_is_logged_with_who_which_device_status_and_reason_but_no_keys(): void
    {
        Log::spy();

        $event = $this->failedEvent(410, 'Gone');
        event($event);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($event): bool {
            return $message === 'Notifikasi push gagal terkirim'
                && (int) $context['subscription_id'] === (int) $event->subscription->id
                && (int) $context['user_id'] === (int) $event->subscription->subscribable_id
                && $context['host'] === 'fcm.googleapis.com'
                && $context['status'] === 410
                && $context['expired_and_removed'] === true
                && ! array_key_exists('public_key', $context)
                && ! array_key_exists('endpoint', $context);
        })->once();
    }

    /** @test */
    public function a_task_alert_asks_for_high_urgency_so_a_sleeping_android_does_not_delay_it(): void
    {
        $alert = new TaskAlert('Judul', 'Isi', url('/admin'), 'tag');
        $message = $alert->toWebPush(User::factory()->create(), $alert);

        $options = $message->toArray()['options'] ?? (fn () => $this->options)->call($message);

        $this->assertSame('high', $options['urgency']);
        $this->assertSame(3600, $options['TTL']);
    }
}
