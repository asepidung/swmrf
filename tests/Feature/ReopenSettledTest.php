<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Akunting membuka kembali invoice/hutang yang dianggap lunas (riwayat impor legacy: semuanya lunas).
 */
class ReopenSettledTest extends TestCase
{
    use RefreshDatabase;

    private function user(string ...$permissions): User
    {
        $user = User::create(['name' => 'Akunting', 'username' => 'ak_'.uniqid(), 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);

        foreach ($permissions as $name) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $name], ['module_name' => 'Test', 'description' => $name])->id);
        }

        return $user;
    }

    private function settledInvoice(bool $exchangeCustomer = false, ?string $exchangeDate = null): Invoice
    {
        $now = now();
        $segment = DB::table('customer_segments')->insertGetId(['name' => 'S'.uniqid(), 'created_at' => $now, 'updated_at' => $now]);
        $group = DB::table('customer_groups')->insertGetId(['name' => 'G'.uniqid(), 'created_at' => $now, 'updated_at' => $now]);
        $customer = DB::table('customers')->insertGetId(['name' => 'C'.uniqid(), 'customer_group_id' => $group, 'customer_segment_id' => $segment, 'address' => '-', 'top' => 14, 'default_discount' => 0, 'invoice_exchange' => $exchangeCustomer ? 1 : 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $so = DB::table('sales_orders')->insertGetId(['so_number' => 'SO-'.uniqid(), 'customer_id' => $customer, 'delivery_date' => '2026-09-01', 'status' => 'completed', 'down_payment' => 0, 'created_by' => $this->user()->id, 'created_at' => $now, 'updated_at' => $now]);
        $id = DB::table('invoices')->insertGetId([
            'invoice_number' => 'INV-'.uniqid(), 'customer_id' => $customer, 'sales_order_id' => $so, 'invoice_date' => '2026-09-01', 'term_of_payment' => 14, 'status' => Invoice::STATUS_PAID,
            'invoice_exchange_date' => $exchangeDate, 'subtotal' => 1000000, 'charge' => 0, 'down_payment' => 0, 'paid_amount' => 1000000, 'balance' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return Invoice::findOrFail($id);
    }

    private function settledPayable(): Payable
    {
        $now = now();
        $supplier = DB::table('suppliers')->insertGetId(['name' => 'S'.uniqid(), 'address' => '-', 'pic' => '-', 'top_days' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $id = DB::table('payables')->insertGetId([
            'payableable_type' => 'App\\Models\\GoodsReceiptMaterial', 'payableable_id' => 1, 'supplier_id' => $supplier, 'document_number' => 'GR-'.uniqid(),
            'amount' => 500000, 'paid_amount' => 500000, 'balance' => 0, 'due_date' => '2026-09-01', 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return Payable::findOrFail($id);
    }

    public function test_an_invoice_is_reopened_with_the_unpaid_part_and_the_reason_is_logged(): void
    {
        $invoice = $this->settledInvoice();
        $by = $this->user('reopen_settled_invoices');

        $invoice->reopenSettled(250000, 'Belum ditransfer customer', $by);

        $fresh = $invoice->fresh();
        $this->assertSame(750000.0, (float) $fresh->paid_amount);
        $this->assertSame(250000.0, (float) $fresh->balance);
        $this->assertSame(Invoice::STATUS_UNPAID, $fresh->status);
        $this->assertSame('2026-09-15', $fresh->due_date?->toDateString(), 'jatuh tempo mengikuti tanggal invoice + TOP');
        $this->assertDatabaseHas('activity_log', ['subject_id' => $invoice->id, 'subject_type' => Invoice::class, 'causer_id' => $by->id]);
    }

    public function test_the_exchange_history_decides_the_status_it_returns_to(): void
    {
        $by = $this->user('reopen_settled_invoices');

        $pending = $this->settledInvoice(true);
        $pending->reopenSettled(1000000, 'belum ditukar', $by);
        $this->assertSame(Invoice::STATUS_EXCHANGE_PENDING, $pending->fresh()->status);
        $this->assertNull($pending->fresh()->due_date, 'belum tukar faktur: jatuh tempo kosong');

        $exchanged = $this->settledInvoice(true, '2026-09-10');
        $exchanged->reopenSettled(1000000, 'sudah ditukar, belum bayar', $by);
        $this->assertSame(Invoice::STATUS_EXCHANGED, $exchanged->fresh()->status);
        $this->assertSame('2026-09-24', $exchanged->fresh()->due_date?->toDateString(), 'tanggal tukar + TOP');
    }

    public function test_without_the_permission_nothing_changes(): void
    {
        $invoice = $this->settledInvoice();

        $this->expectException(AuthorizationException::class);

        try {
            $invoice->reopenSettled(1000, 'iseng', $this->user('view_invoices'));
        } finally {
            $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
        }
    }

    public function test_only_a_settled_invoice_with_a_valid_amount_and_reason_can_be_reopened(): void
    {
        $by = $this->user('reopen_settled_invoices');
        $invoice = $this->settledInvoice();

        foreach ([[0, 'x'], [2000000, 'x'], [1000, '  ']] as [$amount, $reason]) {
            try {
                $invoice->reopenSettled($amount, $reason, $by);
                $this->fail('seharusnya ditolak');
            } catch (\RuntimeException) {
                $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
            }
        }

        $invoice->reopenSettled(1000, 'ok', $by);

        $this->expectException(\RuntimeException::class);
        $invoice->fresh()->reopenSettled(1000, 'sudah dibuka', $by);
    }

    public function test_a_payable_is_reopened_with_the_unpaid_part(): void
    {
        $payable = $this->settledPayable();
        $by = $this->user('reopen_settled_payables');

        $payable->reopenSettled(200000, 'Belum dibayar ke supplier', $by);

        $fresh = $payable->fresh();
        $this->assertSame(300000.0, (float) $fresh->paid_amount);
        $this->assertSame(200000.0, (float) $fresh->balance);
        $this->assertSame('partial', $fresh->status);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $payable->id, 'subject_type' => Payable::class, 'causer_id' => $by->id]);

        $full = $this->settledPayable();
        $full->reopenSettled(500000, 'belum sama sekali', $by);
        $this->assertSame('unpaid', $full->fresh()->status);
    }

    public function test_the_two_permissions_are_separate(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->settledPayable()->reopenSettled(1000, 'x', $this->user('reopen_settled_invoices'));
    }
}
