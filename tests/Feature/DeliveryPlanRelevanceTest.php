<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\DeliveryPlan;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kapan sebuah jadwal kirim berhenti tampil di daftar.
 *
 * Daftar ini alat kerja petugas distribusi: jadwal muncul begitu Sales Order
 * dibuat, dan dibagikan H-1 sekitar pukul sepuluh malam.
 *
 * Sebelum ini, jadwalnya TIDAK PERNAH hilang -- daftarnya memuat seluruh
 * jadwal yang pernah dibuat sejak sistem berdiri, dan bertambah panjang tiap
 * hari.
 *
 * Ketiga peristiwa dokumen yang tersedia sama-sama keliru sebagai penanda,
 * dan alasannya dari Project Owner:
 *
 *  - Surat jalan dibuat: kadang dibuat sehari sebelum berangkat, jadi
 *    jadwalnya hilang padahal barangnya belum ke mana-mana.
 *  - Resi penerimaan dibuat: itu berarti sopir sudah pulang.
 *  - Lewat dari hari kirim: pengiriman sore hilang sejak pagi.
 *
 * Yang dipakai karena itu HARI KIRIM ITU SENDIRI: sebuah jadwal berhenti
 * menjadi jadwal ketika harinya habis.
 */
class DeliveryPlanRelevanceTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Distribusi', 'username' => 'distribusi',
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'PELANGGAN UJI',
            'customer_group_id' => CustomerGroup::create(['name' => 'GRUP UJI'])->id,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL'])->id,
            'address' => 'Jalan Uji',
            'top' => 30,
            'invoice_exchange' => false,
            'is_active' => true,
        ]);
    }

    private function plan(string $date, ?string $salesOrderStatus = null): DeliveryPlan
    {
        $plan = DeliveryPlan::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => $date,
            'created_by' => $this->user->id,
        ]);

        if ($salesOrderStatus !== null) {
            SalesOrder::create([
                'customer_id' => $this->customer->id,
                'delivery_date' => $date,
                'created_by' => $this->user->id,
                'status' => $salesOrderStatus,
                'delivery_plan_id' => $plan->id,
            ]);
        }

        return $plan;
    }

    /** @return array<int, int> */
    private function visibleIds(): array
    {
        return DeliveryPlan::query()->stillRelevant()->pluck('id')->all();
    }

    /**
     * Pengiriman HARI INI tetap tampil, berapa pun jamnya.
     *
     * Inilah keberatan Owner terhadap aturan "lewat dari hari kirim":
     * pengiriman sore tidak boleh hilang sejak pagi.
     */
    public function test_a_delivery_scheduled_for_today_stays_all_day(): void
    {
        $plan = $this->plan(now()->toDateString(), 'completed');

        $this->assertContains($plan->id, $this->visibleIds());
    }

    /** Jadwal hari-hari mendatang jelas tetap tampil. */
    public function test_a_future_delivery_stays(): void
    {
        $plan = $this->plan(now()->addDays(3)->toDateString(), 'waiting');

        $this->assertContains($plan->id, $this->visibleIds());
    }

    /** Hari kirimnya lewat DAN pekerjaannya selesai: hilang. */
    public function test_a_finished_delivery_leaves_the_list_after_its_day(): void
    {
        $plan = $this->plan(now()->subDay()->toDateString(), 'completed');

        $this->assertNotContains($plan->id, $this->visibleIds());
    }

    /**
     * Hari kirimnya lewat tetapi pekerjaannya BELUM selesai: tetap tampil.
     *
     * Inilah lubang yang ditutup. Tanpa ini, pengiriman yang tertunda lenyap
     * diam-diam pada tengah malam justru ketika ia paling perlu dilihat.
     */
    public function test_an_unfinished_delivery_stays_even_after_its_day(): void
    {
        $plan = $this->plan(now()->subDays(3)->toDateString(), 'waiting');

        $this->assertContains($plan->id, $this->visibleIds());
        $this->assertTrue($plan->fresh()->load('salesOrders')->isOverdue());
    }

    /** Sales Order yang dibatalkan terhitung selesai. */
    public function test_a_cancelled_sales_order_counts_as_finished(): void
    {
        $plan = $this->plan(now()->subDay()->toDateString(), 'cancelled');

        $this->assertNotContains($plan->id, $this->visibleIds());
    }

    /**
     * Ejaan 'canceled' yang hanya satu huruf L ikut terhitung.
     *
     * Kedua ejaan ada di basis data warisan, dan yang terlewat akan membuat
     * jadwalnya menggantung selamanya di daftar.
     */
    public function test_the_other_spelling_of_cancelled_counts_too(): void
    {
        $plan = $this->plan(now()->subDay()->toDateString(), 'canceled');

        $this->assertNotContains($plan->id, $this->visibleIds());
        $this->assertContains('canceled', DeliveryPlan::FINISHED_SALES_ORDER_STATUSES);
    }

    /** Jadwal lama tanpa Sales Order sama sekali tidak menggantung. */
    public function test_an_old_plan_without_any_sales_order_does_not_linger(): void
    {
        $plan = $this->plan(now()->subMonth()->toDateString());

        $this->assertNotContains($plan->id, $this->visibleIds());
    }

    /**
     * Penanda dokumen sengaja TIDAK dipakai.
     *
     * Ketiganya sudah ditolak Owner dengan alasan masing-masing. Perhatikan
     * bahwa `on_delivery` pada Sales Order dipasang saat surat jalan DIBUAT,
     * bukan saat truk berangkat, jadi status itu pun bukan penanda yang
     * dicari.
     */
    public function test_the_rule_does_not_depend_on_any_document_event(): void
    {
        $model = file_get_contents(app_path('Models/DeliveryPlan.php'));
        $scope = substr($model, strpos($model, 'public function scopeStillRelevant'), 900);

        $this->assertStringContainsString("whereDate('delivery_date', '>='", $scope);
        $this->assertStringNotContainsString('deliveryOrder', $scope);
        $this->assertStringNotContainsString('receipt', $scope);
        $this->assertStringNotContainsString('on_delivery', $scope);
    }

    /**
     * Riwayatnya tetap bisa dibuka.
     *
     * Aturannya dipasang sebagai SARINGAN bawaan, bukan batasan tetap pada
     * kueri -- mematikan saringannya mengembalikan seluruh daftar.
     */
    public function test_the_history_is_still_reachable(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/DeliveryPlanResource.php'));

        $this->assertStringContainsString("Filter::make('still_relevant')", $source);
        $this->assertStringContainsString('->default()', $source);

        // Bukan di getEloquentQuery(), yang tidak bisa dimatikan pengguna.
        $query = substr($source, strpos($source, 'public static function getEloquentQuery'), 700);
        $this->assertStringNotContainsString('stillRelevant', $query);
    }

    /**
     * Kolom Qty tidak lagi menembak kueri per Sales Order.
     *
     * Bentuk sebelumnya memanggil `$so->items()->sum('weight')` untuk setiap
     * Sales Order pada setiap baris tabel -- dan daftar ini dulu menampilkan
     * seluruh jadwal yang pernah dibuat.
     */
    public function test_the_totals_do_not_query_per_row(): void
    {
        $model = file_get_contents(app_path('Models/DeliveryPlan.php'));

        $this->assertStringNotContainsString("items()->sum('weight')", $model);
        $this->assertStringContainsString("items->sum('weight')", $model);

        $resource = file_get_contents(app_path('Filament/Admin/Resources/DeliveryPlanResource.php'));

        $this->assertStringContainsString('salesOrders.items', $resource);
    }
}
