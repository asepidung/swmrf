<?php

namespace Tests\Feature;

use App\Console\Commands\NotifyDuePayables;
use App\Console\Commands\NotifyUnlockedGoodsReceipts;
use App\Filament\Admin\Widgets\ScheduledReminderHealthWidget;
use App\Models\CattleReceiving;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pengingat hutang jatuh tempo.
 *
 * Keputusan Project Owner: SATU ringkasan per hari, bukan satu notifikasi per
 * dokumen. Hari dengan sepuluh tagihan akan mengirim sepuluh notifikasi, dan
 * orang berhenti membacanya justru pada hari yang paling perlu dibaca.
 *
 * Yang paling penting dijaga di sini bukan isinya, melainkan bahwa
 * KEGAGALANNYA TERLIHAT. Seluruh fitur ini bergantung pada satu baris cron di
 * hPanel; kalau baris itu tidak pernah dipasang atau berhenti, akibatnya
 * tidak menghasilkan gejala apa pun -- tidak ada error, hanya notifikasi yang
 * tidak pernah datang lagi, dan tagihan lewat jatuh tempo baru ketahuan saat
 * supplier menagih.
 */
class DuePayableReminderTest extends TestCase
{
    use RefreshDatabase;

    protected User $finance;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->finance = User::create([
            'name' => 'Finance',
            'username' => 'finance_reminder',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        $this->finance->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'pay_payables'],
                ['module_name' => 'Payables', 'description' => 'Pay payables'],
            )->id
        );

        // Tanpa langganan, TaskNotifier membuang penerimanya sebelum mengirim.
        $this->finance->updatePushSubscription(
            'https://push.example.test/finance-reminder',
            'p256dh-finance-reminder',
            'auth-finance-reminder',
        );

        $this->supplier = Supplier::create([
            'name' => 'FEEDLOT JAYA', 'address' => 'Bogor', 'pic' => 'Doni', 'top_days' => 30,
        ]);
    }

    private function payableDue(string $date, string $status = 'unpaid', string $number = null): Payable
    {
        return Payable::create([
            'payableable_type' => CattleReceiving::class,
            'payableable_id' => random_int(1, 100000),
            'supplier_id' => $this->supplier->id,
            'document_number' => $number ?? 'CR#'.random_int(10000, 99999),
            'amount' => 1_000_000,
            'paid_amount' => $status === 'partial' ? 400_000 : 0,
            'balance' => $status === 'partial' ? 600_000 : 1_000_000,
            'due_date' => $date,
            'status' => $status,
            'created_by' => $this->finance->id,
        ]);
    }

    public function test_it_counts_overdue_due_today_and_due_soon_separately(): void
    {
        $this->payableDue(today()->subDays(5)->toDateString());
        $this->payableDue(today()->subDay()->toDateString());
        $this->payableDue(today()->toDateString());
        $this->payableDue(today()->addDays(2)->toDateString());

        // Di luar jangkauan pengingat, jadi tidak boleh ikut terhitung.
        $this->payableDue(today()->addDays(10)->toDateString());

        $summary = NotifyDuePayables::summary();

        $this->assertSame(2, $summary['overdue']);
        $this->assertSame(1, $summary['today']);
        $this->assertSame(1, $summary['soon']);
        $this->assertSame(4, $summary['total']);
    }

    /**
     * Hutang yang sudah dicicil TETAP dihitung, yang lunas tidak.
     *
     * Sisanya masih harus dibayar, dan justru hutang setengah bayar yang
     * paling gampang terlupakan -- ia tidak lagi terasa "belum dibayar".
     */
    public function test_partly_paid_payables_still_count_but_settled_ones_do_not(): void
    {
        $this->payableDue(today()->toDateString(), 'partial');
        $this->payableDue(today()->toDateString(), 'paid');

        $this->assertSame(1, NotifyDuePayables::summary()['total']);
    }

    /** Kalau tidak ada yang jatuh tempo, tidak mengirim apa pun. */
    public function test_a_quiet_day_sends_nothing(): void
    {
        Notification::fake();

        $this->payableDue(today()->addDays(30)->toDateString());

        $this->artisan('payables:notify-due')->assertSuccessful();

        Notification::assertNothingSent();
    }

    /** Satu ringkasan, bukan satu notifikasi per dokumen. */
    public function test_many_due_payables_produce_a_single_summary(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $i) {
            $this->payableDue(today()->toDateString());
        }

        $this->artisan('payables:notify-due')->assertSuccessful();

        Notification::assertSentToTimes($this->finance, \App\Notifications\TaskAlert::class, 1);
    }

    /** Kalimatnya hanya menyebut yang benar-benar ada. */
    public function test_the_sentence_mentions_only_what_actually_exists(): void
    {
        $this->assertSame(
            '2 overdue, 1 due today',
            NotifyDuePayables::describe(['overdue' => 2, 'today' => 1, 'soon' => 0, 'total' => 3]),
        );

        $this->assertSame(
            '3 due within 3 days',
            NotifyDuePayables::describe(['overdue' => 0, 'today' => 0, 'soon' => 3, 'total' => 3]),
        );
    }

    /**
     * Jejak pemeriksaan dicatat walau tidak ada yang dikirim.
     *
     * Yang perlu diketahui Dashboard adalah "pemeriksaannya berjalan", bukan
     * "ada yang dikirim". Hari tanpa tagihan jatuh tempo memang tidak
     * mengirim apa-apa, dan itu benar -- tapi bukan berarti cron-nya mati.
     */
    public function test_the_check_records_that_it_ran_even_on_a_quiet_day(): void
    {
        Cache::forget(NotifyDuePayables::LAST_RUN_CACHE_KEY);

        $this->artisan('payables:notify-due')->assertSuccessful();

        $this->assertNotNull(Cache::get(NotifyDuePayables::LAST_RUN_CACHE_KEY));
    }

    /**
     * Cron yang tidak pernah dipasang HARUS terlihat.
     *
     * Ini inti dari widget itu: tanpa penanda, berhentinya pengingat tidak
     * menghasilkan gejala apa pun sampai supplier menagih.
     */
    public function test_the_dashboard_shows_when_the_check_has_never_run(): void
    {
        Cache::forget(NotifyDuePayables::LAST_RUN_CACHE_KEY);

        $widget = new ScheduledReminderHealthWidget();

        $this->assertNull($widget->getLastRun());
        $this->assertFalse($widget->isHealthy());
    }

    /** Dan pemeriksaan yang basi juga terlihat, bukan cuma yang tidak pernah ada. */
    public function test_the_dashboard_flags_a_stale_check(): void
    {
        $widget = new ScheduledReminderHealthWidget();

        $segar = now()->subHours(2)->toIso8601String();

        Cache::forever(NotifyDuePayables::LAST_RUN_CACHE_KEY, now()->subDays(5)->toIso8601String());
        Cache::forever(NotifyUnlockedGoodsReceipts::LAST_RUN_CACHE_KEY, $segar);
        $this->assertFalse($widget->isHealthy());

        Cache::forever(NotifyDuePayables::LAST_RUN_CACHE_KEY, $segar);
        $this->assertTrue($widget->isHealthy());
    }

    /**
     * Peringatan terjadwal yang KEDUA ikut diawasi.
     *
     * Keduanya menumpang satu baris cron yang sama, tetapi masing-masing
     * menandai waktu jalannya sendiri. Kalau salah satunya melempar error
     * tiap hari sementara yang lain baik-baik saja, penanda "cron hidup" saja
     * tidak akan memperlihatkannya -- karena itu yang ditampilkan adalah yang
     * PALING TERTINGGAL.
     */
    public function test_one_stale_command_is_enough_to_flag_the_dashboard(): void
    {
        $widget = new ScheduledReminderHealthWidget();

        Cache::forever(NotifyDuePayables::LAST_RUN_CACHE_KEY, now()->subHours(2)->toIso8601String());
        Cache::forever(NotifyUnlockedGoodsReceipts::LAST_RUN_CACHE_KEY, now()->subDays(5)->toIso8601String());

        $this->assertFalse($widget->isHealthy());
    }

    /**
     * Satu saja yang belum pernah berjalan sudah cukup.
     *
     * Perintah yang tidak pernah jalan sama sekali adalah kasus terburuk:
     * peringatannya tidak pernah terkirim, dan tidak ada yang menyadarinya.
     */
    public function test_a_command_that_never_ran_is_treated_as_unknown(): void
    {
        $widget = new ScheduledReminderHealthWidget();

        Cache::forever(NotifyDuePayables::LAST_RUN_CACHE_KEY, now()->subHours(2)->toIso8601String());
        Cache::forget(NotifyUnlockedGoodsReceipts::LAST_RUN_CACHE_KEY);

        $this->assertNull($widget->getLastRun());
        $this->assertFalse($widget->isHealthy());
    }

    /** Kedua jadwalnya benar-benar terdaftar, bukan cuma command-nya ada. */
    public function test_both_reminders_are_actually_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('payables:notify-due')
            ->expectsOutputToContain('goods-receipts:notify-unlocked')
            ->assertSuccessful();
    }

    /**
     * Goods Receipt yang baru dibuat belum ditanyakan.
     *
     * Yang dibuat pagi ini memang wajar belum dikunci -- barangnya masih
     * dihitung, labelnya masih dicetak. Yang perlu ditanyakan adalah yang
     * menginap.
     */
    public function test_a_fresh_goods_receipt_is_not_nagged_about(): void
    {
        $this->assertSame(24, NotifyUnlockedGoodsReceipts::GRACE_HOURS);

        $source = file_get_contents(app_path('Console/Commands/NotifyUnlockedGoodsReceipts.php'));

        $this->assertStringContainsString('subHours(static::GRACE_HOURS)', $source);
        $this->assertStringContainsString("where('is_locked', false)", $source);
    }

    /** Hari tanpa GR menggantung tidak mengirim apa-apa. */
    public function test_a_quiet_day_sends_no_goods_receipt_reminder(): void
    {
        Notification::fake();

        $this->artisan('goods-receipts:notify-unlocked')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
