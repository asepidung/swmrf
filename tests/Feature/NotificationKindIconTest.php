<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TaskAlert;
use App\Support\NotificationKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gambar besar notifikasi mengikuti JENIS notifikasi (dari awalan tag), bukan logo yang sama dengan ikon aplikasi.
 */
class NotificationKindIconTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function every_existing_tag_maps_to_its_kind(): void
    {
        $this->assertSame('permintaan', NotificationKind::forTag('material-request-12'));
        $this->assertSame('permintaan', NotificationKind::forTag('beef-request-3'));
        $this->assertSame('pembayaran', NotificationKind::forTag('payables-due'));
        $this->assertSame('stok', NotificationKind::forTag('goods-receipts-unlocked'));
        $this->assertSame('stok', NotificationKind::forTag('sales-order-7'));
        $this->assertSame('umum', NotificationKind::forTag('qc-report-4'));
        $this->assertSame('umum', NotificationKind::forTag('costing-boning-9'));
        $this->assertSame('umum', NotificationKind::forTag(null));
    }

    /** @test */
    public function every_kind_has_an_opaque_square_image_in_the_app(): void
    {
        foreach ([NotificationKind::REQUEST, NotificationKind::PAYMENT, NotificationKind::STOCK, NotificationKind::GENERAL] as $kind) {
            $path = public_path("img/notif/notif-{$kind}.png");
            $this->assertFileExists($path);

            [$w, $h] = getimagesize($path);
            $this->assertSame($w, $h, "{$kind}: harus persegi");
            $this->assertGreaterThanOrEqual(192, $w);

            // Gambar transparan dirender Android (HyperOS) sebagai kotak biru polos: sudut dan tepi harus buram.
            $image = imagecreatefrompng($path);
            foreach ([[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1], [(int) ($w / 2), 0]] as [$x, $y]) {
                $this->assertSame(0, (imagecolorat($image, $x, $y) >> 24) & 0x7F, "{$kind}: piksel ({$x},{$y}) transparan");
            }
        }
    }

    /** @test */
    public function a_task_alert_carries_the_image_for_its_kind(): void
    {
        $alert = new TaskAlert('Judul', 'Isi', url('/admin'), 'material-request-5');
        $payload = $alert->toWebPush(User::factory()->create(), $alert)->toArray();

        $this->assertSame('/img/notif/notif-permintaan.png', $payload['icon']);

        $payment = new TaskAlert('Judul', 'Isi', url('/admin'), 'payables-due');
        $this->assertSame('/img/notif/notif-pembayaran.png', $payment->toWebPush(User::factory()->create(), $payment)->toArray()['icon']);
    }
}
