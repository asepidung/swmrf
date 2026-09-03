<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentDeduction;
use App\Models\Permission;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bukti terima pembayaran pelanggan, dan penomoran uang masuk/keluar.
 *
 * Keputusan Project Owner, 3 September 2026:
 *
 *   PR#260001   uang masuk dari pelanggan   (Payment Receipt)
 *   PV#260001   uang keluar ke pemasok      (Payment Voucher)
 *
 * Awalannya langsung memberi tahu arah uangnya. Bentuk lamanya --
 * `PAY-0001/IX/26` dan `SP#260001` -- dua format berbeda, dan tidak satu pun
 * menyatakan arah.
 *
 * SATU dokumen, bukan dua: bagian atas untuk pelanggan, rincian alokasi di
 * bawahnya untuk keuangan.
 */
class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CustomerGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Kasir', 'username' => 'kasir_bukti', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'programmer', 'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->group = CustomerGroup::create(['name' => 'BIDADARI']);
    }

    private function pembayaranLengkap(): Payment
    {
        $customer = Customer::create([
            'name' => 'BIDADARI PUSAT',
            'address' => 'Bogor',
            'top' => 30,
            'customer_group_id' => $this->group->id,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
        ]);

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
            'subtotal' => 1000000,
            'charge' => 0,
            'down_payment' => 0,
            'created_by' => $this->user->id,
        ]);

        Receivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'customer_group_id' => $this->group->id,
        ]);

        $payment = Payment::create([
            'customer_group_id' => $this->group->id,
            'bank_account_id' => BankAccount::create([
                'initial' => 'BCA', 'bank_name' => 'BANK CENTRAL ASIA',
                'account_number' => '1234567890', 'account_holder' => 'WIJAYA MEAT',
                'is_active' => true,
            ])->id,
            'payment_date' => now()->toDateString(),
            'amount' => 975000,
            'total_deduction' => 25000,
            'reference_number' => 'TRF-9911',
        ]);

        PaymentDeduction::create([
            'payment_id' => $payment->id,
            'description' => 'Biaya admin bank',
            'amount' => 25000,
        ]);

        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_allocated' => 1000000,
        ]);

        return $payment->fresh();
    }

    /** Uang masuk memakai awalan PR#. */
    public function test_a_customer_payment_is_numbered_as_a_receipt(): void
    {
        $payment = $this->pembayaranLengkap();

        $this->assertSame('PR#'.date('y').'0001', $payment->payment_number);
    }

    /** Uang keluar memakai awalan PV#. */
    public function test_a_supplier_payment_is_numbered_as_a_voucher(): void
    {
        $this->assertSame('PV#'.date('y').'0001', SupplierPayment::generateNumber());
    }

    /**
     * Nomor yang sudah terbit dengan awalan lama tidak mengganggu urutan baru.
     *
     * Nomor SP# yang sudah keluar sengaja TIDAK ditulis ulang -- menulis ulang
     * nomor dokumen yang sudah terbit adalah hal yang tidak boleh dilakukan
     * pembukuan. Urutan PV# berangkat dari awal, dan keduanya bisa hidup
     * berdampingan karena awalannya berbeda.
     */
    public function test_the_old_prefix_is_left_alone(): void
    {
        SupplierPayment::create([
            'payment_number' => 'SP#'.date('y').'0005',
            'supplier_id' => \App\Models\Supplier::create([
                'name' => 'FEEDLOT', 'address' => 'Bogor', 'pic' => 'Doni', 'top_days' => 30,
            ])->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'method' => SupplierPayment::METHOD_CASH,
        ]);

        $this->assertSame('PV#'.date('y').'0001', SupplierPayment::generateNumber());
    }

    /**
     * Urutannya tidak putus di dokumen ke sepuluh ribu.
     *
     * Rakitan lama membaca urutannya dengan `preg_match('/(\d{4})$/')`, jadi
     * `10000` terbaca `0000` dan nomor berikutnya dihitung 1 -- nomor yang
     * sudah dipakai dicoba lagi, dan kolomnya bertanda unique.
     */
    public function test_the_sequence_survives_five_digits(): void
    {
        $supplier = \App\Models\Supplier::create([
            'name' => 'FEEDLOT', 'address' => 'Bogor', 'pic' => 'Doni', 'top_days' => 30,
        ]);

        SupplierPayment::create([
            'payment_number' => 'PV#'.date('y').'10000',
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'method' => SupplierPayment::METHOD_CASH,
        ]);

        $this->assertSame('PV#'.date('y').'10001', SupplierPayment::generateNumber());
    }

    /** Bukti terimanya bisa dicetak, dan memuat kedua bagiannya. */
    public function test_the_receipt_prints_both_halves(): void
    {
        $payment = $this->pembayaranLengkap();

        $this->get(route('print.payment-receipt', $payment->id))
            ->assertSuccessful()
            // Bagian yang dibaca pelanggan.
            ->assertSee($payment->payment_number)
            ->assertSee('BIDADARI')
            ->assertSee('TRF-9911')
            // Bagian yang dibutuhkan keuangan.
            ->assertSee(Invoice::firstOrFail()->invoice_number)
            ->assertSee('1.000.000')
            // Potongannya harus terlihat, karena uang yang masuk bank lebih
            // kecil daripada tagihan yang lunas.
            ->assertSee('Biaya admin bank')
            ->assertSee('975.000');
    }

    /** Dan pembayarannya bisa dilihat lagi dari halaman piutang grupnya. */
    public function test_a_recorded_payment_can_be_found_again(): void
    {
        $payment = $this->pembayaranLengkap();

        $this->user->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'view_receivables'],
                ['module_name' => 'Receivables', 'description' => 'View receivables'],
            )->id
        );

        \Livewire\Livewire::actingAs($this->user->fresh())
            ->test(
                \App\Filament\Admin\Resources\ReceivableResource\RelationManagers\PaymentsRelationManager::class,
                [
                    'ownerRecord' => $this->group,
                    'pageClass' => \App\Filament\Admin\Resources\ReceivableResource\Pages\ViewReceivable::class,
                ],
            )
            ->assertSuccessful()
            ->assertSee($payment->payment_number);
    }
}
