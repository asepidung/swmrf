<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Migrasi backfill Customer tanpa grup, 15 September 2026.
 *
 * Sebelum form mewajibkan `customer_group_id`, Customer boleh dibuat tanpa
 * grup -- dan piutangnya lenyap total dari modul Piutang (lihat laporan
 * Receivable Temuan 1). Migrasi ini membereskan data lama yang sudah
 * terlanjur begitu.
 */
class CustomerGroupBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function jalankanMigrasi(): void
    {
        $migration = require base_path(
            'database/migrations/2026_09_15_130000_backfill_customer_group_for_ungrouped_customers.php'
        );

        $migration->up();
    }

    private User $user;

    private CustomerSegment $segmen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->segmen = CustomerSegment::create(['name' => 'RETAIL']);
    }

    private function customerTanpaGrup(string $nama): Customer
    {
        return Customer::create([
            'name' => $nama,
            'customer_segment_id' => $this->segmen->id,
            'address' => 'Bogor',
            'top' => 30,
        ]);
    }

    /** @test */
    public function a_customer_without_a_group_gets_one_named_after_itself(): void
    {
        $customer = $this->customerTanpaGrup('TOKO SENDIRIAN');

        $this->assertNull($customer->customer_group_id);

        $this->jalankanMigrasi();

        $customer->refresh();

        $this->assertNotNull($customer->customer_group_id);
        $this->assertSame('TOKO SENDIRIAN', $customer->group->name);
    }

    /** @test */
    public function the_new_group_name_is_made_unique_when_it_already_exists(): void
    {
        CustomerGroup::create(['name' => 'TOKO KEMBAR', 'top' => 30]);
        $customer = $this->customerTanpaGrup('TOKO KEMBAR');

        $this->jalankanMigrasi();

        $customer->refresh();

        $this->assertSame('TOKO KEMBAR 2', $customer->group->name);
    }

    /** @test */
    public function an_ungrouped_customers_receivable_is_backfilled_too(): void
    {
        $customer = $this->customerTanpaGrup('TOKO PIUTANG');

        $so = SalesOrder::create([
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id,
            'status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'sales_order_id' => $so->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 30,
            'status' => 'Belum Dibayar',
            'subtotal' => 500000,
            'charge' => 0,
            'down_payment' => 0,
            'created_by' => $this->user->id,
        ]);

        // Dibuat langsung lewat DB, bukan Receivable::create(), supaya
        // benar-benar meniru baris lama yang customer_group_id-nya NULL --
        // persis skenario yang dilaporkan Temuan 1.
        DB::table('receivables')->insert([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'customer_group_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->jalankanMigrasi();

        $customer->refresh();
        $receivable = Receivable::where('invoice_id', $invoice->id)->first();

        $this->assertSame($customer->customer_group_id, $receivable->customer_group_id);
        $this->assertNotNull($receivable->customer_group_id);
    }

    /** @test */
    public function a_customer_that_already_has_a_group_is_left_alone(): void
    {
        $group = CustomerGroup::create(['name' => 'GRUP ASLI', 'top' => 14]);
        $customer = Customer::create([
            'name' => 'TOKO SUDAH ADA GRUP',
            'customer_group_id' => $group->id,
            'customer_segment_id' => $this->segmen->id,
            'address' => 'Bogor',
            'top' => 30,
        ]);

        $this->jalankanMigrasi();

        $this->assertSame($group->id, $customer->fresh()->customer_group_id);
        $this->assertSame(1, CustomerGroup::where('name', 'GRUP ASLI')->count());
    }
}
