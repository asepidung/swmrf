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
 * Baris potongan hantu.
 *
 * Dilaporkan Project Owner, 4 September 2026: menambah beberapa baris potongan
 * lalu menghapus semuanya membuat SATU baris muncul kembali saat tombol simpan
 * ditekan, lengkap dengan pesan kesalahan -- dan pembayarannya gagal tersimpan.
 *
 * Sebabnya di Livewire. Nilai yang diketik dikirim dengan penundaan; kalau
 * barisnya dihapus sebelum nilainya sempat terkirim, nilai itu menumpang
 * permintaan BERIKUTNYA -- dan menulis properti bersarang ke kunci yang sudah
 * tidak ada MEMBUAT ULANG kuncinya.
 *
 * Yang lahir kembali bukan barisnya yang utuh, melainkan satu field saja:
 *
 *     baris asli : {"description": null, "amount": null}
 *     baris hantu: {"amount": "5000"}
 *
 * Itulah pembedanya, dan itu yang dipakai untuk membuangnya.
 */
class DeductionGhostRowTest extends TestCase
{
    use RefreshDatabase;

    private CustomerGroup $group;

    private Invoice $invoice;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Kasir', 'username' => 'kasir_hantu', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        foreach (['view_receivables', 'receive_receivables'] as $izin) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => $izin],
                    ['module_name' => 'Receivables', 'description' => $izin],
                )->id
            );
        }

        $this->actingAs($user->fresh());

        $this->group = CustomerGroup::create(['name' => 'BIDADARI']);

        $customer = Customer::create([
            'name' => 'BIDADARI PUSAT', 'address' => 'Bogor', 'top' => 30,
            'customer_group_id' => $this->group->id,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
        ]);

        $so = SalesOrder::create([
            'customer_id' => $customer->id, 'delivery_date' => now()->toDateString(),
            'created_by' => $user->id, 'status' => 'completed',
        ]);

        $this->invoice = Invoice::create([
            'customer_id' => $customer->id, 'sales_order_id' => $so->id,
            'invoice_date' => now()->toDateString(), 'term_of_payment' => 30,
            'status' => 'Belum Dibayar', 'subtotal' => 1000000,
            'charge' => 0, 'down_payment' => 0, 'created_by' => $user->id,
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
     * Urutan yang dilakukan Owner, apa adanya.
     *
     * Tambah tiga baris potongan, hapus semuanya, lalu simpan. Nilai yang
     * menggantung dari baris yang sudah dihapus ikut terkirim -- dan itulah
     * yang dulu melahirkan baris hantu.
     */
    public function test_deleted_deduction_rows_do_not_come_back(): void
    {
        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group]);

        for ($i = 0; $i < 3; $i++) {
            $lw->call('mountFormComponentAction', 'data.deductions', 'add');
        }

        $baris = $lw->get('data')['deductions'] ?? [];
        $this->assertCount(3, $baris);

        $dihapus = array_key_first($baris);

        foreach (array_keys($baris) as $key) {
            $lw->call('mountFormComponentAction', 'data.deductions', 'delete', ['item' => $key]);
        }

        $this->assertSame([], $lw->get('data')['deductions'] ?? []);

        // Nilai yang menggantung dari baris yang sudah dihapus.
        $lw->set('data.deductions.'.$dihapus.'.amount', '5000');

        $this->assertNotSame(
            [],
            $lw->get('data')['deductions'],
            'Prasyarat: Livewire memang membuat ulang kuncinya.',
        );

        $lw->fillForm([
            'bank_account_id' => $this->bank->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000000,
            'allocations.'.$this->invoice->id => 1000000,
        ])->call('save');

        $this->assertSame(1, Payment::count(), 'Pembayarannya harus tersimpan, bukan ditolak baris hantu.');
        $this->assertSame(0, PaymentDeduction::count(), 'Dan tidak boleh ada potongan yang ikut tercatat.');
        $this->assertSame(0.0, (float) Payment::first()->total_deduction);
        $this->assertSame(0.0, (float) $this->invoice->fresh()->balance);
    }

    /**
     * Baris yang KOSONG TAPI UTUH tetap ditolak.
     *
     * Itu baris yang benar-benar ditambahkan lalu tidak diisi, dan pengguna
     * berhak diberi tahu. Penyaring hantu tidak boleh diam-diam menelannya.
     */
    public function test_a_row_that_was_really_added_but_left_empty_still_complains(): void
    {
        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group]);

        $lw->call('mountFormComponentAction', 'data.deductions', 'add');

        $lw->fillForm([
            'bank_account_id' => $this->bank->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000000,
            'allocations.'.$this->invoice->id => 1000000,
        ])->call('save');

        $this->assertSame(0, Payment::count(), 'Baris kosong yang sungguhan harus tetap menahan penyimpanan.');
    }

    /** Dan potongan yang benar-benar diisi tetap tercatat seperti biasa. */
    public function test_a_real_deduction_is_still_recorded(): void
    {
        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group]);

        $lw->call('mountFormComponentAction', 'data.deductions', 'add');

        $key = array_key_first($lw->get('data')['deductions']);

        $lw->set('data.deductions.'.$key.'.description', 'Biaya admin bank')
            ->set('data.deductions.'.$key.'.amount', '25000')
            ->fillForm([
                'bank_account_id' => $this->bank->id,
                'payment_date' => now()->toDateString(),
                'amount' => 975000,
                'allocations.'.$this->invoice->id => 1000000,
            ])
            ->call('save');

        $this->assertSame(1, Payment::count());
        $this->assertSame(1, PaymentDeduction::count());
        $this->assertSame(0.0, (float) $this->invoice->fresh()->balance, 'Tagihan lunas penuh meski uangnya kurang.');
    }
}
