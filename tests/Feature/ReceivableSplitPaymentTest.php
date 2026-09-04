<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ReceivableResource\Pages\ReceivePayment;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Skenario Project Owner, 4 September 2026, apa adanya.
 *
 * Dua invoice belum dibayar -- 2 juta dan 3 juta -- lalu pelanggan mentransfer
 * 2.500.000. Uangnya tidak pas untuk satu invoice pun, dan tidak cukup untuk
 * keduanya.
 *
 * Sekalian aturan jatuh temponya:
 *
 *  - invoice biasa   : jatuh tempo = tanggal invoice + TOP pelanggan
 *  - tukar faktur    : jatuh tempo BELUM dihitung sampai fakturnya ditukar
 */
class ReceivableSplitPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CustomerGroup $group;

    private Customer $customer;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Kasir', 'username' => 'kasir_pecah', 'password' => 'secret-password',
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

        $this->customer = Customer::create([
            'name' => 'BIDADARI PUSAT',
            'address' => 'Bogor',
            'top' => 30,
            'customer_group_id' => $this->group->id,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
        ]);

        $this->bank = BankAccount::create([
            'initial' => 'BCA', 'bank_name' => 'BANK CENTRAL ASIA',
            'account_number' => '1234567890', 'account_holder' => 'WIJAYA MEAT',
            'is_active' => true,
        ]);
    }

    private function invoice(float $jumlah, string $status = 'Belum Dibayar'): Invoice
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id,
            'status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'sales_order_id' => $so->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => $this->customer->top,
            'status' => $status,
            'subtotal' => $jumlah,
            'charge' => 0,
            'down_payment' => 0,
            'created_by' => $this->user->id,
        ]);

        Receivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'customer_group_id' => $this->group->id,
        ]);

        return $invoice->fresh();
    }

    /**
     * Dua invoice, satu transfer yang tidak pas untuk keduanya.
     *
     * 2 juta + 3 juta, dibayar 2.500.000. Yang mencatat memecahnya sendiri:
     * 2 juta melunasi invoice pertama, sisanya 500 ribu menjadi cicilan untuk
     * yang kedua.
     */
    public function test_one_transfer_can_settle_one_invoice_and_partly_pay_another(): void
    {
        $duaJuta = $this->invoice(2000000);
        $tigaJuta = $this->invoice(3000000);

        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->fillForm([
                'bank_account_id' => $this->bank->id,
                'payment_date' => now()->toDateString(),
                'amount' => 2500000,
                'allocations.'.$duaJuta->id => 2000000,
                'allocations.'.$tigaJuta->id => 500000,
            ])
            ->call('save');

        $a = $duaJuta->fresh();
        $b = $tigaJuta->fresh();

        $this->assertSame(0.0, (float) $a->balance, 'Invoice 2 juta harus lunas.');
        $this->assertSame('Lunas', $a->status);

        $this->assertSame(2500000.0, (float) $b->balance, 'Invoice 3 juta menyisakan 2,5 juta.');
        $this->assertSame(500000.0, (float) $b->paid_amount);
        $this->assertNotSame('Lunas', $b->status, 'Yang baru dicicil tidak boleh berstatus lunas.');

        $this->assertSame(1, Payment::count(), 'Satu transfer = satu dokumen pembayaran.');
    }

    /** Seluruh transfer boleh ditumpuk ke satu invoice saja. */
    public function test_the_whole_transfer_may_go_to_a_single_invoice(): void
    {
        $duaJuta = $this->invoice(2000000);
        $tigaJuta = $this->invoice(3000000);

        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->fillForm([
                'bank_account_id' => $this->bank->id,
                'payment_date' => now()->toDateString(),
                'amount' => 2500000,
                'allocations.'.$duaJuta->id => 0,
                'allocations.'.$tigaJuta->id => 2500000,
            ])
            ->call('save');

        $this->assertSame(2000000.0, (float) $duaJuta->fresh()->balance, 'Yang tidak dialokasikan tidak berubah.');
        $this->assertSame(500000.0, (float) $tigaJuta->fresh()->balance);
    }

    /**
     * Uang yang tidak habis dibagi DITOLAK.
     *
     * Kalau 2.500.000 masuk tetapi hanya 2.000.000 yang dialokasikan, sisanya
     * 500 ribu tidak menempel ke tagihan mana pun -- uang yang sudah diterima
     * tetapi tidak mengurangi piutang siapa pun.
     */
    public function test_money_that_is_not_fully_split_is_refused(): void
    {
        $duaJuta = $this->invoice(2000000);
        $this->invoice(3000000);

        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->fillForm([
                'bank_account_id' => $this->bank->id,
                'payment_date' => now()->toDateString(),
                'amount' => 2500000,
                'allocations.'.$duaJuta->id => 2000000,
            ])
            ->call('save');

        $this->assertSame(0, Payment::count(), 'Tidak boleh ada yang tersimpan.');
        $this->assertSame(2000000.0, (float) $duaJuta->fresh()->balance);
    }

    /** Invoice biasa: jatuh tempo dihitung dari tanggal invoice + TOP. */
    public function test_a_normal_invoice_is_due_after_the_customer_term(): void
    {
        $invoice = $this->invoice(2000000);

        $this->assertSame(
            now()->addDays(30)->toDateString(),
            $invoice->due_date?->toDateString(),
        );
    }

    /**
     * Tukar faktur: jatuh tempo BELUM dihitung sampai fakturnya ditukar.
     *
     * Aturan Project Owner. Selama masih 'Belum TF', pelanggan belum menerima
     * fakturnya, jadi hitungan TOP-nya belum boleh berjalan.
     */
    public function test_an_exchange_invoice_has_no_due_date_yet(): void
    {
        $invoice = $this->invoice(2000000, status: 'Belum TF');

        $this->assertNull($invoice->due_date, 'Jatuh tempo belum boleh ada sebelum tukar faktur.');
    }

    /** Dan mulai dihitung dari TANGGAL TUKAR FAKTUR, bukan tanggal invoice. */
    public function test_the_term_starts_at_the_exchange_date(): void
    {
        $invoice = $this->invoice(2000000, status: 'Belum TF');

        $tanggalTukar = now()->addDays(10)->toDateString();

        $invoice->update([
            'status' => 'Sudah TF',
            'invoice_exchange_date' => $tanggalTukar,
        ]);

        $this->assertSame(
            now()->addDays(40)->toDateString(),
            $invoice->fresh()->due_date?->toDateString(),
            'Jatuh tempo = tanggal tukar faktur + TOP.',
        );
    }

    /**
     * Invoice tukar faktur yang dicicil TIDAK kehilangan statusnya.
     *
     * Kalau status 'Belum TF' ikut tertimpa saat pembayaran sebagian, jatuh
     * temponya akan langsung dihitung dari tanggal invoice -- padahal
     * fakturnya belum ditukar.
     */
    public function test_a_partly_paid_exchange_invoice_keeps_waiting_for_its_exchange(): void
    {
        $invoice = $this->invoice(2000000, status: 'Belum TF');

        $invoice->applyPayment(500000);

        $segar = $invoice->fresh();

        $this->assertSame('Belum TF', $segar->status);
        $this->assertNull($segar->due_date, 'Jatuh tempo tidak boleh muncul hanya karena dicicil.');
    }
}
