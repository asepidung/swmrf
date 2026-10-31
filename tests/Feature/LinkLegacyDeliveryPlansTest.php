<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\DeliveryPlan;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SO impor yang belum selesai harus punya Rencana Pengiriman, supaya muncul di layar itu pada hari kerja pertama.
 * Rencana dibuat oleh event model SalesOrder; importer menulis tanpa event itu, jadi dipasang belakangan oleh perintah ini.
 */
class LinkLegacyDeliveryPlansTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'name' => 'PELANGGAN UJI', 'address' => 'Bogor', 'top_days' => 30,
            'customer_segment_id' => CustomerSegment::firstOrCreate(['name' => 'RETAIL'])->id,
        ]);
    }

    /** SO seperti hasil importer: tanpa event model, jadi tanpa rencana. */
    private function so(string $nomor, string $tanggal, string $status): SalesOrder
    {
        return SalesOrder::withoutEvents(fn () => SalesOrder::create([
            'so_number' => $nomor, 'customer_id' => $this->customer->id,
            'delivery_date' => $tanggal, 'status' => $status,
        ]));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->so('SO-1', '2026-11-02', 'waiting');

        $this->artisan('legacy:link-delivery-plans')->expectsOutputToContain('DRY-RUN')->assertSuccessful();

        $this->assertSame(0, DeliveryPlan::count());
        $this->assertNull(SalesOrder::where('so_number', 'SO-1')->value('delivery_plan_id'));
    }

    public function test_open_orders_get_one_plan_per_customer_and_date(): void
    {
        $a = $this->so('SO-1', '2026-11-02', 'waiting');
        $b = $this->so('SO-2', '2026-11-02', 'processing');
        $c = $this->so('SO-3', '2026-11-03', 'on_delivery');

        $this->artisan('legacy:link-delivery-plans', ['--apply' => true])->assertSuccessful();

        $this->assertSame(2, DeliveryPlan::count());
        $this->assertSame($a->fresh()->delivery_plan_id, $b->fresh()->delivery_plan_id);
        $this->assertNotSame($a->fresh()->delivery_plan_id, $c->fresh()->delivery_plan_id);
    }

    public function test_finished_and_cancelled_orders_are_left_alone(): void
    {
        $done = $this->so('SO-1', '2026-10-20', 'completed');
        $cancelled = $this->so('SO-2', '2026-10-21', 'cancelled');

        $this->artisan('legacy:link-delivery-plans', ['--apply' => true])->assertSuccessful();

        $this->assertSame(0, DeliveryPlan::count());
        $this->assertNull($done->fresh()->delivery_plan_id);
        $this->assertNull($cancelled->fresh()->delivery_plan_id);
    }

    public function test_it_reuses_an_existing_plan_and_is_safe_to_repeat(): void
    {
        $existing = DeliveryPlan::create(['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-02']);
        $so = $this->so('SO-1', '2026-11-02', 'waiting');

        $this->artisan('legacy:link-delivery-plans', ['--apply' => true])->assertSuccessful();
        $this->artisan('legacy:link-delivery-plans', ['--apply' => true])->assertSuccessful();

        $this->assertSame(1, DeliveryPlan::count());
        $this->assertSame($existing->id, $so->fresh()->delivery_plan_id);
    }
}
