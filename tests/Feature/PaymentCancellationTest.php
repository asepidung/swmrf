<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ReceivableResource\Pages\ReceivePayment;
use App\Filament\Admin\Resources\ReceivableResource\RelationManagers\PaymentsRelationManager;
use App\Models\BankAccount;
use App\Models\BankTransaction;
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
 * Membatalkan pembayaran pelanggan.
 *
 * Sampai 4 September 2026 tidak ada jalan mundur sama sekali: salah ketik
 * nominal hanya bisa diperbaiki lewat basis data langsung.
 *
 * Taruhannya lima hal sekaligus, dan melewatkan satu saja membuat saldo bank
 * atau piutang salah tanpa gejala:
 *
 *   1. alokasi ke tiap invoice
 *   2. sisa tagihan dan status invoice
 *   3. baris buku kas yang masuk
 *   4. baris buku kas potongannya
 *   5. pembayarannya sendiri -- DITANDAI batal, bukan dihapus
 */
class PaymentCancellationTest extends TestCase
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
            'name' => 'Kasir', 'username' => 'kasir_batal', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        foreach (['view_receivables', 'receive_receivables'] as $izin) {
            $this->beriIzin($izin);
        }

        $this->actingAs($this->user->fresh());

        $this->group = CustomerGroup::create(['name' => 'BIDADARI']);

        $this->customer = Customer::create([
            'name' => 'BIDADARI PUSAT', 'address' => 'Bogor', 'top' => 30,
            'customer_group_id' => $this->group->id,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
        ]);

        $this->bank = BankAccount::create([
            'initial' => 'BCA', 'bank_name' => 'BANK CENTRAL ASIA',
            'account_number' => '1234567890', 'account_holder' => 'WIJAYA MEAT',
            'is_active' => true,
        ]);
    }

    private function beriIzin(string $nama): void
    {
        $this->user->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => $nama],
                ['module_name' => 'Receivables', 'description' => $nama],
            )->id
        );

        $this->user->refresh();
    }

    private function invoice(float $jumlah): Invoice
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id, 'status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $this->customer->id, 'sales_order_id' => $so->id,
            'invoice_date' => now()->subDays(10)->toDateString(), 'term_of_payment' => 30,
            'status' => 'Belum Dibayar', 'subtotal' => $jumlah,
            'charge' => 0, 'down_payment' => 0, 'created_by' => $this->user->id,
        ]);

        Receivable::create([
            'invoice_id' => $invoice->id, 'customer_id' => $this->customer->id,
            'customer_group_id' => $this->group->id,
        ]);

        return $invoice->fresh();
    }

    /** Satu pembayaran 6 juta dengan potongan 500 ribu. */
    private function catatPembayaran(Invoice $invoice): Payment
    {
        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.bank_account_id', $this->bank->id)
            ->set('data.payment_date', now()->toDateString())
            ->set('data.amount', '5.500.000')
            ->set('data.deductions', [
                'p1' => [
                    'type' => PaymentDeduction::TYPE_BANK_FEE,
                    'invoice_id' => null,
                    'description' => 'Biaya admin bank',
                    'amount' => '500.000',
                ],
            ])
            ->call('autoAllocate')
            ->call('save');

        return Payment::firstOrFail();
    }

    /**
     * Kelima akibatnya dikembalikan.
     */
    public function test_cancelling_puts_everything_back(): void
    {
        $invoice = $this->invoice(6000000);
        $payment = $this->catatPembayaran($invoice);

        $this->assertSame(0.0, (float) $invoice->fresh()->balance, 'Prasyarat: tagihannya lunas.');
        $this->assertSame(5500000.0, $this->bank->fresh()->currentBalance());

        $payment->cancel('Nominalnya salah', $this->user->id);

        $segar = $invoice->fresh();

        $this->assertSame(0.0, (float) $segar->paid_amount, 'Yang sudah dibayar kembali nol.');
        $this->assertSame(6000000.0, (float) $segar->balance, 'Tagihannya kembali utuh.');
        $this->assertNotSame('Lunas', $segar->status, 'Dan statusnya tidak boleh tetap lunas.');

        $this->assertSame(
            0.0,
            $this->bank->fresh()->currentBalance(),
            'Saldo bank kembali ke titik semula.',
        );
    }

    /**
     * Pembayarannya TETAP ADA, dengan tanda dibatalkan.
     *
     * Membalik, bukan menghapus. Keuangan harus bisa membaca "ada pembayaran,
     * dibatalkan tanggal sekian oleh siapa" -- bukan angka yang lenyap seolah
     * tidak pernah ada.
     */
    public function test_the_payment_stays_on_record(): void
    {
        $invoice = $this->invoice(6000000);
        $payment = $this->catatPembayaran($invoice);
        $nomor = $payment->payment_number;

        $payment->cancel('Nominalnya salah', $this->user->id);

        $segar = $payment->fresh();

        $this->assertNotNull($segar, 'Barisnya tidak boleh hilang.');
        $this->assertTrue($segar->isCancelled());
        $this->assertSame('Nominalnya salah', $segar->cancellation_reason);
        $this->assertSame($this->user->id, $segar->cancelled_by);
        $this->assertSame($nomor, $segar->payment_number, 'Nomornya tidak berubah.');
    }

    /** Nomor dokumennya tetap terpakai, tidak boleh dipakai ulang. */
    public function test_the_cancelled_number_is_not_handed_out_again(): void
    {
        $invoice = $this->invoice(6000000);
        $payment = $this->catatPembayaran($invoice);

        $payment->cancel('Salah catat', $this->user->id);

        $this->assertNotSame(
            $payment->payment_number,
            Payment::nextNumber(),
            'Nomor yang sudah terbit tidak boleh terbit lagi.',
        );
    }

    /**
     * Baris buku kas ASLINYA tidak dihapus, tetapi dibalik.
     *
     * Menghapusnya membuat buku kas berbohong tentang masa lalu. Yang benar
     * menambahkan lawannya, supaya keduanya terbaca dan selisihnya nol.
     */
    public function test_the_cash_book_is_reversed_not_erased(): void
    {
        $invoice = $this->invoice(6000000);
        $payment = $this->catatPembayaran($invoice);

        $sebelum = BankTransaction::count();

        $payment->cancel('Salah catat', $this->user->id);

        $this->assertSame($sebelum * 2, BankTransaction::count(), 'Tiap baris asli dapat lawannya.');
        $this->assertSame(0.0, $this->bank->fresh()->currentBalance());

        $this->assertSame(
            2,
            BankTransaction::where('reference_type', Payment::CANCELLATION_REFERENCE)->count(),
            'Baris pembaliknya bertanda tersendiri, supaya tidak ikut terbalik lagi.',
        );
    }

    /** Membatalkan dua kali ditolak. */
    public function test_a_payment_cannot_be_cancelled_twice(): void
    {
        $invoice = $this->invoice(6000000);
        $payment = $this->catatPembayaran($invoice);

        $payment->cancel('Salah catat', $this->user->id);

        $this->expectException(\RuntimeException::class);

        $payment->fresh()->cancel('Sekali lagi', $this->user->id);
    }

    /**
     * Invoice yang pembayarannya sudah dibatalkan boleh dihapus lagi.
     *
     * Kalau alokasi milik pembayaran batal ikut menahan, satu kali salah catat
     * akan mengunci invoicenya selamanya.
     */
    public function test_an_invoice_can_be_deleted_again_after_the_payment_is_cancelled(): void
    {
        $invoice = $this->invoice(6000000);
        $payment = $this->catatPembayaran($invoice);

        $payment->cancel('Salah catat', $this->user->id);

        $invoice->fresh()->delete();

        $this->assertSoftDeleted($invoice);
    }

    /** Tanpa izinnya, tombol batal tidak muncul. */
    public function test_the_button_is_hidden_without_its_own_permission(): void
    {
        $invoice = $this->invoice(6000000);
        $payment = $this->catatPembayaran($invoice);

        Livewire::actingAs($this->user->fresh())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $this->group,
                'pageClass' => \App\Filament\Admin\Resources\ReceivableResource\Pages\ViewReceivable::class,
            ])
            ->assertTableActionHidden('cancel', $payment);
    }

    /** Dengan izinnya, tombolnya muncul dan bekerja. */
    public function test_the_button_works_for_whoever_holds_the_permission(): void
    {
        $invoice = $this->invoice(6000000);
        $payment = $this->catatPembayaran($invoice);

        $this->beriIzin('cancel_receivable_payments');

        Livewire::actingAs($this->user->fresh())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $this->group,
                'pageClass' => \App\Filament\Admin\Resources\ReceivableResource\Pages\ViewReceivable::class,
            ])
            ->callTableAction('cancel', $payment, data: ['reason' => 'Nominalnya salah']);

        $this->assertTrue($payment->fresh()->isCancelled());
        $this->assertSame(6000000.0, (float) $invoice->fresh()->balance);
    }
}
