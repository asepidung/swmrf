<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ReceivableResource\Pages\ReceivePayment;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentDeduction;
use App\Models\Permission;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran Receivable, 15 September 2026 -- Temuan 4.
 *
 * Field `amount` pada baris potongan (Repeater `deductions`) dulu memakai
 * `$money()` yang sama dengan field di luar Repeater -- lengkap dengan mask
 * Alpine `$money()`, yang DILARANG di dalam Repeater (project.md) karena jadi
 * salah satu akar "baris hantu". Perbaikannya memindahkan pemformatan ke
 * listener `x-on:input` pada Section pembungkus (pola `SalesOrderResource`),
 * yang HANYA memformat tampilan di browser -- nilai yang sungguh dikirim ke
 * server tetap membawa titik pemisah ribuan, persis seperti field
 * weight/price di Repeater SalesOrder.
 *
 * Karena itu jalur baca nilainya (`save()`) WAJIB memakai `angka()` (yang
 * membuang titik lebih dulu), bukan `(float)` polos -- `(float) "500.000"`
 * di PHP terbaca sebagai 500.0, bukan 500000, karena PHP membaca titik
 * sebagai pemisah desimal.
 */
class ReceivableDeductionAmountFormatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CustomerGroup $group;

    private Invoice $invoice;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Kasir', 'username' => 'kasir_format_potongan', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        foreach (['view_receivables', 'receive_receivables'] as $izin) {
            $this->user->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => $izin],
                    ['module_name' => 'Receivables', 'description' => $izin],
                )->id
            );
        }

        $this->actingAs($this->user->fresh());

        $this->group = CustomerGroup::create(['name' => 'BIDADARI']);

        $customer = Customer::create([
            'name' => 'BIDADARI PUSAT', 'address' => 'Bogor', 'top' => 30,
            'customer_group_id' => $this->group->id,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
        ]);

        $so = SalesOrder::create([
            'customer_id' => $customer->id, 'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id, 'status' => 'completed',
        ]);

        $this->invoice = Invoice::create([
            'customer_id' => $customer->id, 'sales_order_id' => $so->id,
            'invoice_date' => now()->toDateString(), 'term_of_payment' => 30,
            'status' => 'Belum Dibayar', 'subtotal' => 1000000,
            'charge' => 0, 'down_payment' => 0, 'created_by' => $this->user->id,
        ]);

        Receivable::create([
            'invoice_id' => $this->invoice->id, 'customer_id' => $customer->id,
            'customer_group_id' => $this->group->id,
        ]);

        $this->bank = BankAccount::create([
            'initial' => 'BCA', 'bank_name' => 'BANK CENTRAL ASIA',
            'account_number' => '1234567890', 'account_holder' => 'WIJAYA MEAT',
            'is_active' => true,
        ]);
    }

    /**
     * Nilai berpemisah ribuan -- persis bentuk yang dikirim browser setelah
     * listener `x-on:input` memformatnya -- harus terbaca penuh, bukan
     * terpotong di titik pertama seperti desimal.
     */
    public function test_a_thousands_separated_deduction_amount_is_read_in_full(): void
    {
        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group]);

        $lw->call('mountFormComponentAction', 'data.deductions', 'add');
        $key = array_key_first($lw->get('data')['deductions']);

        // "500.000" -- bukan lima ratus, tapi lima ratus ribu. Kalau `save()`
        // masih membaca ini dengan `(float)` polos, hasilnya 500.0.
        $lw->set('data.deductions.'.$key.'.description', 'Biaya admin bank')
            ->set('data.deductions.'.$key.'.amount', '500.000')
            ->fillForm([
                'bank_account_id' => $this->bank->id,
                'payment_date' => now()->toDateString(),
                'amount' => 500000,
                'allocations.'.$this->invoice->id => 1000000,
            ])
            ->call('save');

        $this->assertSame(1, Payment::count());
        $this->assertSame(1, PaymentDeduction::count());
        $this->assertSame(500000.0, (float) PaymentDeduction::first()->amount);
        $this->assertSame(500000.0, (float) Payment::first()->total_deduction);
        $this->assertSame(0.0, (float) $this->invoice->fresh()->balance, 'Tagihan lunas: 500rb transfer + 500rb potongan = 1jt.');
    }

    /** Baris potongan tanpa titik sama sekali (angka polos) tetap benar. */
    public function test_a_plain_deduction_amount_without_dots_still_works(): void
    {
        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group]);

        $lw->call('mountFormComponentAction', 'data.deductions', 'add');
        $key = array_key_first($lw->get('data')['deductions']);

        $lw->set('data.deductions.'.$key.'.description', 'Promo')
            ->set('data.deductions.'.$key.'.amount', '25000')
            ->fillForm([
                'bank_account_id' => $this->bank->id,
                'payment_date' => now()->toDateString(),
                'amount' => 975000,
                'allocations.'.$this->invoice->id => 1000000,
            ])
            ->call('save');

        $this->assertSame(25000.0, (float) PaymentDeduction::first()->amount);
    }
}
