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
 * Uang yang masuk dibagikan sendiri ke invoice, TERTUA LEBIH DULU.
 *
 * Keputusan Project Owner, 4 September 2026, termasuk dasar urutannya:
 * TANGGAL INVOICE, bukan jatuh tempo. Bedanya nyata untuk pelanggan tukar
 * faktur -- invoice yang fakturnya belum ditukar belum punya jatuh tempo sama
 * sekali, jadi kalau diurutkan dari jatuh tempo ia tersingkir ke belakang,
 * padahal justru dialah yang paling lama menunggu.
 *
 * Ini hanya MENGISIKAN, bukan mengunci: tiap kotak tetap bisa diubah, dan
 * penjaga keseimbangannya tidak berubah.
 */
class ReceivableAutoAllocationTest extends TestCase
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
            'name' => 'Kasir', 'username' => 'kasir_auto', 'password' => 'secret-password',
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

    private function invoice(float $jumlah, string $tanggal, string $status = 'Belum Dibayar'): Invoice
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => $tanggal,
            'created_by' => $this->user->id,
            'status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'sales_order_id' => $so->id,
            'invoice_date' => $tanggal,
            'term_of_payment' => 30,
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

    /** Angka dari state form, yang masih membawa titik pemisah ribuan. */
    private function angka(mixed $nilai): float
    {
        return (float) str_replace('.', '', (string) ($nilai ?? '0'));
    }

    /**
     * Skenario Owner: invoice 2 juta (lebih tua) dan 3 juta, transfer 2,5 juta.
     *
     * Yang tertua dilunasi lebih dulu, sisanya turun ke berikutnya -- tanpa
     * satu pun kotak diketik tangan.
     */
    public function test_the_oldest_invoice_is_settled_first(): void
    {
        $tua = $this->invoice(2000000, now()->subDays(20)->toDateString());
        $muda = $this->invoice(3000000, now()->subDays(5)->toDateString());

        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.amount', '2.500.000')
            ->call('autoAllocate');

        $alokasi = $lw->get('data')['allocations'];

        $this->assertSame(2000000.0, $this->angka($alokasi[$tua->id]), 'Yang tertua dilunasi penuh.');
        $this->assertSame(500000.0, $this->angka($alokasi[$muda->id]), 'Sisanya turun ke berikutnya.');
    }

    /** Urutannya dari TANGGAL INVOICE, bukan dari besarnya maupun dari id. */
    public function test_the_order_follows_the_invoice_date(): void
    {
        // Sengaja dibuat terbalik: yang dibuat DULUAN justru bertanggal muda.
        $muda = $this->invoice(3000000, now()->subDays(5)->toDateString());
        $tua = $this->invoice(2000000, now()->subDays(20)->toDateString());

        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.amount', '2.000.000')
            ->call('autoAllocate');

        $alokasi = $lw->get('data')['allocations'];

        $this->assertSame(2000000.0, $this->angka($alokasi[$tua->id]), 'Yang bertanggal tua duluan, bukan yang dibuat duluan.');
        $this->assertSame(0.0, $this->angka($alokasi[$muda->id]));
    }

    /**
     * Invoice tukar faktur yang belum punya jatuh tempo tetap dapat giliran
     * pertama kalau tanggalnya memang paling tua.
     *
     * Inilah sebabnya urutannya bukan dari jatuh tempo.
     */
    public function test_an_invoice_still_waiting_for_its_exchange_is_not_pushed_to_the_back(): void
    {
        $tukarFaktur = $this->invoice(2000000, now()->subDays(30)->toDateString(), status: 'Belum TF');
        $biasa = $this->invoice(3000000, now()->subDays(5)->toDateString());

        $this->assertNull($tukarFaktur->due_date, 'Prasyarat: belum punya jatuh tempo.');

        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.amount', '2.000.000')
            ->call('autoAllocate');

        $alokasi = $lw->get('data')['allocations'];

        $this->assertSame(2000000.0, $this->angka($alokasi[$tukarFaktur->id]));
        $this->assertSame(0.0, $this->angka($alokasi[$biasa->id]));
    }

    /** Potongan ikut dihitung sebagai uang yang membayar. */
    public function test_deductions_count_as_money_that_pays(): void
    {
        $invoice = $this->invoice(1000000, now()->subDays(10)->toDateString());

        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.amount', '975.000')
            ->set('data.deductions', [
                'baris-satu' => ['description' => 'Biaya admin bank', 'amount' => '25.000'],
            ])
            ->call('autoAllocate');

        $alokasi = $lw->get('data')['allocations'];

        $this->assertSame(
            1000000.0,
            $this->angka($alokasi[$invoice->id]),
            'Tagihan lunas penuh: 975 ribu uang masuk ditambah 25 ribu potongan.',
        );
    }

    /** Uang yang lebih besar daripada seluruh piutang tidak dipaksakan masuk. */
    public function test_no_invoice_is_allocated_more_than_it_owes(): void
    {
        $invoice = $this->invoice(1000000, now()->subDays(10)->toDateString());

        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.amount', '5.000.000')
            ->call('autoAllocate');

        $this->assertSame(
            1000000.0,
            $this->angka($lw->get('data')['allocations'][$invoice->id]),
            'Alokasinya berhenti di sisa tagihannya sendiri.',
        );
    }

    /**
     * Hasil isian otomatis itu benar-benar bisa langsung disimpan.
     *
     * Bentuk berpemisah ribuan yang ditulisnya harus lolos validasi dan
     * tersimpan sebagai angka yang benar -- bukan sekadar enak dilihat.
     */
    public function test_the_filled_allocation_saves_as_it_stands(): void
    {
        $tua = $this->invoice(2000000, now()->subDays(20)->toDateString());
        $muda = $this->invoice(3000000, now()->subDays(5)->toDateString());

        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.bank_account_id', $this->bank->id)
            ->set('data.payment_date', now()->toDateString())
            ->set('data.amount', '2.500.000')
            ->call('autoAllocate')
            ->call('save');

        $this->assertSame(0.0, (float) $tua->fresh()->balance);
        $this->assertSame(2500000.0, (float) $muda->fresh()->balance);
    }

    /**
     * Potongan yang MENUNJUK sebuah invoice mendarat di invoice itu.
     *
     * Skenario Project Owner: tiga invoice 1jt, 3jt, dan 5jt, pelanggan
     * mentransfer 5.500.000 dengan potongan promo 500 ribu.
     *
     * Tanpa penunjukan, 500 ribu itu larut ke kantong dan mendarat di invoice
     * 5jt -- tempat uangnya kebetulan habis. Padahal promonya untuk invoice
     * 1jt. Totalnya tetap benar, tetapi catatan invoice MANA yang didiskon
     * jadi salah.
     */
    public function test_a_deduction_that_points_at_an_invoice_lands_there(): void
    {
        $satu = $this->invoice(1000000, now()->subDays(30)->toDateString());
        $tiga = $this->invoice(3000000, now()->subDays(20)->toDateString());
        $lima = $this->invoice(5000000, now()->subDays(10)->toDateString());

        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.amount', '5.500.000')
            ->set('data.deductions', [
                'p1' => [
                    'type' => \App\Models\PaymentDeduction::TYPE_PROMOTION,
                    'invoice_id' => $satu->id,
                    'description' => 'Klaim promo',
                    'amount' => '500.000',
                ],
            ])
            ->call('autoAllocate');

        $alokasi = $lw->get('data')['allocations'];

        // Invoice 1jt: 500rb dari promonya + 500rb dari uang transfer.
        $this->assertSame(1000000.0, $this->angka($alokasi[$satu->id]));
        $this->assertSame(3000000.0, $this->angka($alokasi[$tiga->id]));
        $this->assertSame(2000000.0, $this->angka($alokasi[$lima->id]));

        // Totalnya tetap sama dengan uang masuk ditambah potongannya.
        $this->assertSame(
            6000000.0,
            array_sum(array_map(fn ($v) => $this->angka($v), $alokasi)),
        );
    }

    /**
     * Potongan TANPA tujuan tetap dibagi bersama uang riilnya.
     *
     * Itu perlakuan yang benar untuk biaya bank: pelanggan bermaksud membayar
     * penuh, banknya yang memotong di jalan.
     */
    public function test_a_deduction_without_a_target_is_still_pooled(): void
    {
        $satu = $this->invoice(1000000, now()->subDays(30)->toDateString());
        $lima = $this->invoice(5000000, now()->subDays(10)->toDateString());

        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.amount', '1.500.000')
            ->set('data.deductions', [
                'p1' => [
                    'type' => \App\Models\PaymentDeduction::TYPE_BANK_FEE,
                    'invoice_id' => null,
                    'description' => 'Biaya admin bank',
                    'amount' => '25.000',
                ],
            ])
            ->call('autoAllocate');

        $alokasi = $lw->get('data')['allocations'];

        $this->assertSame(1000000.0, $this->angka($alokasi[$satu->id]));
        $this->assertSame(525000.0, $this->angka($alokasi[$lima->id]), 'Sisanya termasuk biaya banknya.');
    }

    /**
     * Potongan bertujuan yang lebih besar daripada invoicenya DITOLAK.
     *
     * Kalau dibiarkan, kelebihannya akan diam-diam menutup tagihan invoice
     * lain -- persis kekacauan yang hendak dihindari dengan menunjuknya.
     */
    public function test_a_targeted_deduction_larger_than_its_invoice_is_refused(): void
    {
        $satu = $this->invoice(1000000, now()->subDays(30)->toDateString());
        $lima = $this->invoice(5000000, now()->subDays(10)->toDateString());

        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.bank_account_id', $this->bank->id)
            ->set('data.payment_date', now()->toDateString())
            ->set('data.amount', '1.000.000')
            ->set('data.deductions', [
                'p1' => [
                    'type' => \App\Models\PaymentDeduction::TYPE_PROMOTION,
                    'invoice_id' => $satu->id,
                    'description' => 'Promo kebesaran',
                    'amount' => '2.000.000',
                ],
            ])
            ->call('autoAllocate')
            ->call('save');

        $this->assertSame(0, \App\Models\Payment::count(), 'Tidak boleh ada yang tersimpan.');
        $this->assertSame(1000000.0, (float) $satu->fresh()->balance);
        $this->assertSame(5000000.0, (float) $lima->fresh()->balance);
    }

    /** Jenis dan tujuan potongannya ikut tersimpan. */
    public function test_the_kind_and_the_target_are_recorded(): void
    {
        $satu = $this->invoice(1000000, now()->subDays(30)->toDateString());

        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.bank_account_id', $this->bank->id)
            ->set('data.payment_date', now()->toDateString())
            ->set('data.amount', '500.000')
            ->set('data.deductions', [
                'p1' => [
                    'type' => \App\Models\PaymentDeduction::TYPE_PROMOTION,
                    'invoice_id' => $satu->id,
                    'description' => 'Klaim promo',
                    'amount' => '500.000',
                ],
            ])
            ->call('autoAllocate')
            ->call('save');

        $potongan = \App\Models\PaymentDeduction::firstOrFail();

        $this->assertSame(\App\Models\PaymentDeduction::TYPE_PROMOTION, $potongan->type);
        $this->assertSame($satu->id, $potongan->invoice_id);
        $this->assertSame(0.0, (float) $satu->fresh()->balance, 'Tagihannya lunas oleh 500rb uang + 500rb promo.');
    }
}
