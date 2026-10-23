<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ReceivableResource\Pages\ReceivePayment;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Grup yang anggotanya orang berbeda-beda (KARYAWAN, WARGA +62) membayar piutang SATUAN per customer (Owner, 23 Oktober 2026):
 * customer dipilih lebih dulu, hanya invoice customer itu yang tampil, dan uang satu orang tidak pernah melunasi tagihan orang lain.
 */
class ReceivablePerCustomerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BankAccount $bank;

    private CustomerGroup $group;

    private Customer $ani;

    private Customer $budi;

    private Invoice $aniInvoice;

    private Invoice $budiInvoice;

    private function customer(string $name, CustomerGroup $group): Customer
    {
        return Customer::create([
            'name' => $name, 'address' => 'Bogor', 'top' => 0, 'customer_group_id' => $group->id,
            'customer_segment_id' => CustomerSegment::firstOrCreate(['name' => 'RETAIL'], ['is_active' => true])->id,
        ]);
    }

    private function invoice(Customer $customer, float $amount, string $date): Invoice
    {
        $so = SalesOrder::create(['customer_id' => $customer->id, 'delivery_date' => $date, 'created_by' => $this->user->id, 'status' => 'completed']);
        $invoice = Invoice::create([
            'customer_id' => $customer->id, 'sales_order_id' => $so->id, 'invoice_date' => $date, 'term_of_payment' => 0,
            'status' => 'Belum Dibayar', 'subtotal' => $amount, 'charge' => 0, 'down_payment' => 0, 'created_by' => $this->user->id,
        ]);
        Receivable::create(['invoice_id' => $invoice->id, 'customer_id' => $customer->id, 'customer_group_id' => $customer->customer_group_id]);

        return $invoice->fresh();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create(['name' => 'Kasir', 'username' => 'kasir_pc', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);

        foreach (['view_receivables', 'receive_receivables'] as $izin) {
            $this->user->permissions()->attach(Permission::firstOrCreate(['name' => $izin], ['module_name' => 'Receivables', 'description' => $izin])->id);
        }

        $this->actingAs($this->user->fresh());
        $this->bank = BankAccount::create(['initial' => 'BCA', 'bank_name' => 'BANK CENTRAL ASIA', 'account_number' => '1234567890', 'account_holder' => 'WIJAYA MEAT', 'is_active' => true]);

        $this->group = CustomerGroup::create(['name' => 'KARYAWAN', 'pays_per_customer' => true]);
        $this->ani = $this->customer('ANI', $this->group);
        $this->budi = $this->customer('BUDI', $this->group);
        $this->aniInvoice = $this->invoice($this->ani, 1000000, now()->subDays(10)->toDateString());
        $this->budiInvoice = $this->invoice($this->budi, 2000000, now()->subDays(20)->toDateString());
    }

    private function pay(string $customerId, string $amount, array $allocations)
    {
        $page = Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.customer_id', $customerId)
            ->set('data.bank_account_id', $this->bank->id)
            ->set('data.payment_date', now()->toDateString())
            ->set('data.amount', $amount);

        foreach ($allocations as $invoiceId => $value) {
            $page->set("data.allocations.{$invoiceId}", $value);
        }

        return $page->call('save');
    }

    public function test_the_group_flag_defaults_to_off(): void
    {
        $this->assertTrue($this->group->fresh()->pays_per_customer);
        $this->assertFalse((bool) CustomerGroup::create(['name' => 'LION'])->fresh()->pays_per_customer);
    }

    public function test_no_invoice_is_offered_until_a_customer_is_chosen(): void
    {
        $page = Livewire::test(ReceivePayment::class, ['record' => $this->group]);

        $this->assertSame([], array_keys($page->get('data')['allocations'] ?? []), 'tanpa customer terpilih tidak ada kotak alokasi');

        $page->set('data.customer_id', (string) $this->ani->id);

        $this->assertSame([$this->aniInvoice->id], array_keys($page->get('data')['allocations']), 'hanya invoice customer yang dipilih');
    }

    public function test_paying_one_customer_never_touches_the_other(): void
    {
        $this->pay((string) $this->ani->id, '1.000.000', [$this->aniInvoice->id => '1.000.000']);

        $this->assertSame('Lunas', $this->aniInvoice->fresh()->status);
        $this->assertSame(0.0, (float) $this->aniInvoice->fresh()->balance);
        $this->assertSame(2000000.0, (float) $this->budiInvoice->fresh()->balance, 'tagihan orang lain tidak tersentuh');
        $this->assertNotSame('Lunas', $this->budiInvoice->fresh()->status);
    }

    public function test_a_payment_larger_than_the_customers_bills_is_refused_because_there_is_no_shared_deposit(): void
    {
        $this->pay((string) $this->ani->id, '1.500.000', [$this->aniInvoice->id => '1.000.000']);

        $this->assertNotSame('Lunas', $this->aniInvoice->fresh()->status, 'ditolak: tidak ada yang tersimpan');
        $this->assertSame(0, \DB::table('payments')->count());
    }

    public function test_without_a_customer_the_payment_is_refused(): void
    {
        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.bank_account_id', $this->bank->id)
            ->set('data.payment_date', now()->toDateString())
            ->set('data.amount', '1.000.000')
            ->call('save');

        $this->assertSame(0, \DB::table('payments')->count());
    }

    public function test_a_normal_group_still_shows_all_invoices_and_the_customer_is_optional(): void
    {
        $normal = CustomerGroup::create(['name' => 'LION']);
        $dc = $this->customer('LION DCA', $normal);
        $invoice = $this->invoice($dc, 500000, now()->subDays(3)->toDateString());

        $page = Livewire::test(ReceivePayment::class, ['record' => $normal]);

        $this->assertSame([$invoice->id], array_keys($page->get('data')['allocations']), 'grup biasa: seluruh invoice grup tampil tanpa memilih customer');
    }

    public function test_a_normal_group_can_pay_only_the_ticked_invoices(): void
    {
        $normal = CustomerGroup::create(['name' => 'LION']);
        $dc = $this->customer('LION DCA', $normal);
        $first = $this->invoice($dc, 500000, now()->subDays(9)->toDateString());
        $second = $this->invoice($dc, 700000, now()->subDays(3)->toDateString());

        $page = Livewire::test(ReceivePayment::class, ['record' => $normal]);

        $this->assertEqualsCanonicalizing([(string) $first->id, (string) $second->id], $page->get('data')['selected_invoices'], 'awalnya semua tercentang');

        $page->set('data.selected_invoices', [(string) $second->id])
            ->set('data.bank_account_id', $this->bank->id)
            ->set('data.payment_date', now()->toDateString())
            ->set('data.amount', '700.000')
            ->call('save');

        $this->assertSame('Lunas', $second->fresh()->status);
        $this->assertSame(500000.0, (float) $first->fresh()->balance, 'yang tidak dicentang tidak tersentuh walau lebih tua');
        $this->assertNotSame('Lunas', $first->fresh()->status);
    }

    public function test_saving_with_nothing_ticked_is_refused(): void
    {
        $normal = CustomerGroup::create(['name' => 'LION']);
        $dc = $this->customer('LION DCA', $normal);
        $this->invoice($dc, 500000, now()->subDays(3)->toDateString());

        Livewire::test(ReceivePayment::class, ['record' => $normal])
            ->set('data.selected_invoices', [])
            ->set('data.bank_account_id', $this->bank->id)
            ->set('data.payment_date', now()->toDateString())
            ->set('data.amount', '500.000')
            ->call('save');

        $this->assertSame(0, \DB::table('payments')->count());
    }
}
