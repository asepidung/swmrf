<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Invoice impor tidak punya pembuat di legacy, jadi bertuliskan akun cadangan impor (QC). Diganti akun netral "Legacy".
 */
class AssignLegacyCreatorTest extends TestCase
{
    use RefreshDatabase;

    private User $opik;

    private User $asli;

    protected function setUp(): void
    {
        parent::setUp();

        $make = fn (string $u) => User::create(['name' => strtoupper($u), 'username' => $u, 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        $this->opik = $make('opik');
        $this->asli = $make('asli');

        $now = now();
        $segment = DB::table('customer_segments')->insertGetId(['name' => 'S', 'created_at' => $now, 'updated_at' => $now]);
        $group = DB::table('customer_groups')->insertGetId(['name' => 'G', 'created_at' => $now, 'updated_at' => $now]);
        $customer = DB::table('customers')->insertGetId(['name' => 'C', 'customer_group_id' => $group, 'customer_segment_id' => $segment, 'address' => '-', 'top' => 14, 'default_discount' => 0, 'invoice_exchange' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $so = DB::table('sales_orders')->insertGetId(['so_number' => 'SO-1', 'customer_id' => $customer, 'delivery_date' => '2026-09-01', 'status' => 'completed', 'down_payment' => 0, 'created_by' => $this->asli->id, 'created_at' => $now, 'updated_at' => $now]);

        foreach ([['INV-1', $this->opik->id], ['INV-2', $this->opik->id], ['INV-3', $this->asli->id]] as [$no, $by]) {
            DB::table('invoices')->insert(['invoice_number' => $no, 'customer_id' => $customer, 'sales_order_id' => $so, 'invoice_date' => '2026-09-01', 'term_of_payment' => 14, 'status' => Invoice::STATUS_PAID, 'subtotal' => 1000, 'charge' => 0, 'down_payment' => 0, 'paid_amount' => 1000, 'balance' => 0, 'created_by' => $by, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('legacy:assign-legacy-creator')->expectsOutputToContain('DRY-RUN')->assertSuccessful();

        $this->assertNull(User::where('username', 'legacy')->first());
        $this->assertSame(2, DB::table('invoices')->where('created_by', $this->opik->id)->count());
    }

    public function test_only_the_fallback_accounts_invoices_move_to_a_login_less_legacy_account(): void
    {
        $this->artisan('legacy:assign-legacy-creator', ['--apply' => true])->assertSuccessful();

        $legacy = User::where('username', 'legacy')->firstOrFail();
        $this->assertFalse((bool) $legacy->is_active, 'Akun Legacy tidak boleh bisa dipakai masuk.');
        $this->assertSame(2, DB::table('invoices')->where('created_by', $legacy->id)->count());
        $this->assertSame(1, DB::table('invoices')->where('created_by', $this->asli->id)->count(), 'Invoice yang pembuatnya sungguhan tidak boleh ikut berpindah.');
        $this->assertSame(0, DB::table('invoices')->where('created_by', $this->opik->id)->count());
    }

    public function test_it_is_safe_to_repeat(): void
    {
        $this->artisan('legacy:assign-legacy-creator', ['--apply' => true])->assertSuccessful();
        $this->artisan('legacy:assign-legacy-creator', ['--apply' => true])->assertSuccessful();

        $this->assertSame(1, User::where('username', 'legacy')->count());
    }

    public function test_other_tables_are_refused(): void
    {
        $this->artisan('legacy:assign-legacy-creator', ['--tables' => 'users'])->assertFailed();
    }
}
