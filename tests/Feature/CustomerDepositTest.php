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
 * Lebih bayar menjadi DEPOSIT PELANGGAN.
 *
 * Keputusan Project Owner, 4 September 2026 -- pertanyaan terakhir dari lima
 * yang lama menggantung. Sebelumnya kelebihan transfer ditolak di pintu karena
 * tidak ada tempat menaruhnya, sehingga harus diurus di luar sistem.
 *
 * Bentuknya mencerminkan uang muka pemasok yang sudah lebih dulu bekerja di
 * sisi pembelian: uang yang sudah benar-benar diterima, belum menempel ke
 * tagihan mana pun, menunggu invoice berikutnya.
 *
 * Bedanya satu dan disengaja: di sini depositnya DIHITUNG dari baris
 * alokasinya, bukan disimpan di kolom tersendiri. Kolom semacam itu selalu
 * bisa melenceng dari baris alokasinya tanpa ada yang menyadarinya.
 */
class CustomerDepositTest extends TestCase
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
            'name' => 'Kasir', 'username' => 'kasir_deposit', 'password' => 'secret-password',
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

    private function invoice(float $jumlah, int $umurHari = 10): Invoice
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id, 'status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $this->customer->id, 'sales_order_id' => $so->id,
            'invoice_date' => now()->subDays($umurHari)->toDateString(), 'term_of_payment' => 30,
            'status' => 'Belum Dibayar', 'subtotal' => $jumlah,
            'charge' => 0, 'down_payment' => 0, 'created_by' => $this->user->id,
        ]);

        Receivable::create([
            'invoice_id' => $invoice->id, 'customer_id' => $this->customer->id,
            'customer_group_id' => $this->group->id,
        ]);

        return $invoice->fresh();
    }

    /** @param array<int, float> $alokasi */
    private function bayar(float $nominal, array $alokasi, array $potongan = []): void
    {
        $lw = Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.bank_account_id', $this->bank->id)
            ->set('data.payment_date', now()->toDateString())
            ->set('data.amount', number_format($nominal, 0, ',', '.'));

        if ($potongan !== []) {
            $lw->set('data.deductions', $potongan);
        }

        foreach ($alokasi as $invoiceId => $jumlah) {
            $lw->set('data.allocations.'.$invoiceId, number_format($jumlah, 0, ',', '.'));
        }

        $lw->call('save');
    }

    /**
     * Lebih bayar TIDAK lagi ditolak; kelebihannya menjadi deposit.
     */
    public function test_an_overpayment_becomes_a_deposit(): void
    {
        $invoice = $this->invoice(9000000);

        $this->bayar(10000000, [$invoice->id => 9000000]);

        $this->assertSame(1, Payment::count(), 'Pembayarannya tersimpan, bukan ditolak.');
        $this->assertSame(0.0, (float) $invoice->fresh()->balance, 'Tagihannya lunas.');

        $this->assertSame(
            1000000.0,
            $this->group->availableDeposit(),
            'Kelebihannya menjadi deposit, bukan hilang.',
        );

        $this->assertSame(
            10000000.0,
            $this->bank->fresh()->currentBalance(),
            'Dan seluruh uangnya memang ada di bank.',
        );
    }

    /**
     * Deposit dipakai LEBIH DULU pada pembayaran berikutnya.
     *
     * Menghabiskannya lebih dulu membuatnya tidak menumpuk, dan membuat
     * pertanyaan "kenapa pelanggan ini masih punya deposit padahal tagihannya
     * banyak" tidak pernah muncul.
     */
    public function test_the_deposit_is_spent_before_new_money(): void
    {
        $lama = $this->invoice(9000000, umurHari: 30);
        $this->bayar(10000000, [$lama->id => 9000000]);

        $this->assertSame(1000000.0, $this->group->availableDeposit());

        // Invoice berikutnya 3 juta, pelanggan cuma transfer 2 juta.
        $baru = $this->invoice(3000000, umurHari: 5);
        $this->bayar(2000000, [$baru->id => 3000000]);

        $this->assertSame(0.0, (float) $baru->fresh()->balance, 'Tagihan barunya lunas penuh.');
        $this->assertSame(0.0, $this->group->availableDeposit(), 'Depositnya habis terpakai.');

        $this->assertSame(
            12000000.0,
            $this->bank->fresh()->currentBalance(),
            'Saldo bank hanya bertambah sebesar uang yang benar-benar masuk.',
        );
    }

    /**
     * Deposit yang terpakai tetap bisa ditelusuri ke pembayaran asalnya.
     *
     * Alokasinya melekat pada pembayaran yang memberi uangnya, bukan pada
     * pembayaran yang kebetulan sedang dicatat. Itu juga yang membuat
     * pembatalan pembayaran lama mengembalikan tagihan yang ditutupnya.
     */
    public function test_a_spent_deposit_still_points_at_the_payment_it_came_from(): void
    {
        $lama = $this->invoice(9000000, umurHari: 30);
        $this->bayar(10000000, [$lama->id => 9000000]);

        $pembayaranPertama = Payment::orderBy('id')->firstOrFail();

        $baru = $this->invoice(1000000, umurHari: 5);
        $this->bayar(0, [$baru->id => 1000000]);

        $this->assertSame(
            10000000.0,
            (float) $pembayaranPertama->allocations()->sum('amount_allocated'),
            'Seluruh uang pembayaran pertama kini menempel ke invoice.',
        );

        $this->assertTrue(
            $pembayaranPertama->allocations()->where('invoice_id', $baru->id)->exists(),
            'Termasuk ke invoice yang dilunasi belakangan memakai depositnya.',
        );
    }

    /** Alokasi melebihi seluruh uang yang ada tetap ditolak. */
    public function test_allocating_more_than_the_money_available_is_refused(): void
    {
        $invoice = $this->invoice(9000000);

        $this->bayar(1000000, [$invoice->id => 5000000]);

        $this->assertSame(0, Payment::count(), 'Tidak boleh ada yang tersimpan.');
        $this->assertSame(9000000.0, (float) $invoice->fresh()->balance);
    }

    /**
     * Potongan yang tidak menutup tagihan apa pun ditolak.
     *
     * Potongan diberikan JUSTRU karena ada tagihan yang dianggap lunas tanpa
     * uangnya masuk. Potongan yang tidak menempel ke tagihan mana pun berarti
     * uang yang hilang tanpa sebab.
     */
    public function test_a_deduction_that_settles_nothing_is_refused(): void
    {
        $invoice = $this->invoice(9000000);

        $this->bayar(1000000, [$invoice->id => 0], [
            'p1' => [
                'type' => PaymentDeduction::TYPE_BANK_FEE,
                'invoice_id' => null,
                'description' => 'Biaya admin bank',
                'amount' => '25.000',
            ],
        ]);

        $this->assertSame(0, Payment::count());
    }

    /** Deposit milik pembayaran yang dibatalkan tidak bisa dipakai lagi. */
    public function test_a_cancelled_payment_leaves_no_deposit(): void
    {
        $invoice = $this->invoice(9000000);
        $this->bayar(10000000, [$invoice->id => 9000000]);

        $this->assertSame(1000000.0, $this->group->availableDeposit());

        Payment::firstOrFail()->cancel('Salah catat', $this->user->id);

        $this->assertSame(
            0.0,
            $this->group->availableDeposit(),
            'Uangnya sudah dibalik keluar, jadi tidak ada deposit yang tersisa.',
        );
    }

    /**
     * Depositnya terlihat di daftar piutang.
     *
     * Sampai 4 September 2026 ia hanya muncul di halaman Terima Pembayaran --
     * artinya tidak ada satu pun layar yang bisa menjawab "pelanggan mana saja
     * yang masih punya deposit".
     */
    public function test_the_deposit_shows_up_on_the_listing(): void
    {
        $invoice = $this->invoice(9000000);
        $this->bayar(10000000, [$invoice->id => 9000000]);

        Livewire::actingAs($this->user->fresh())
            ->test(\App\Filament\Admin\Resources\ReceivableResource\Pages\ListReceivables::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->group])
            ->assertSee('1.000.000');
    }

    /**
     * Dan grup yang seluruh tagihannya LUNAS tetap muncul selama depositnya ada.
     *
     * Ini lubang yang paling mudah terlewat: daftar piutang hanya menampilkan
     * yang masih berutang, jadi pelanggan yang sudah lunas tetapi menyimpan
     * deposit lenyap sama sekali dari layar -- berikut uang perusahaan yang
     * dipegang atas namanya.
     */
    public function test_a_group_with_no_debt_but_a_deposit_still_appears(): void
    {
        $invoice = $this->invoice(9000000);
        $this->bayar(10000000, [$invoice->id => 9000000]);

        $this->assertSame(0.0, (float) $invoice->fresh()->balance, 'Prasyarat: tidak ada tagihan tersisa.');
        $this->assertSame(1000000.0, $this->group->availableDeposit());

        Livewire::actingAs($this->user->fresh())
            ->test(\App\Filament\Admin\Resources\ReceivableResource\Pages\ListReceivables::class)
            ->assertCanSeeTableRecords([$this->group]);
    }

    /** Begitu depositnya habis dan tagihannya lunas, grupnya boleh hilang. */
    public function test_a_settled_group_without_a_deposit_drops_off_the_listing(): void
    {
        $invoice = $this->invoice(9000000);
        $this->bayar(9000000, [$invoice->id => 9000000]);

        $this->assertSame(0.0, $this->group->availableDeposit());

        Livewire::actingAs($this->user->fresh())
            ->test(\App\Filament\Admin\Resources\ReceivableResource\Pages\ListReceivables::class)
            ->assertCanNotSeeTableRecords([$this->group]);
    }
    /** PAY-01: grup yang kemudian menjadi "bayar per pelanggan" tidak memakai sisa deposit lamanya. */
    public function test_a_per_customer_group_does_not_spend_its_old_shared_deposit(): void
    {
        $lama = $this->invoice(9000000, umurHari: 30);
        $this->bayar(10000000, [$lama->id => 9000000]);
        $this->assertSame(1000000.0, $this->group->availableDeposit());

        $this->group->update(['pays_per_customer' => true]);

        $baru = $this->invoice(3000000, umurHari: 5);
        Livewire::test(ReceivePayment::class, ['record' => $this->group->fresh()])
            ->set('data.customer_id', $this->customer->id)
            ->set('data.bank_account_id', $this->bank->id)
            ->set('data.payment_date', now()->toDateString())
            ->set('data.amount', '3.000.000')
            ->set('data.allocations.'.$baru->id, '3.000.000')
            ->call('save');

        $this->assertSame(0.0, (float) $baru->fresh()->balance, 'tagihan baru lunas dari uang pelanggan sendiri');
        $this->assertSame(1000000.0, (float) Payment::orderBy('id')->first()->fresh()->load('allocations')->unallocatedAmount(), 'deposit lama tidak tersentuh');
    }
    /** INV-01: invoice yang sudah ada pembayarannya terkunci; terbuka lagi bila pembayarannya dibatalkan. */
    public function test_an_invoice_with_a_payment_cannot_be_edited_until_the_payment_is_cancelled(): void
    {
        $this->user->update(['role' => 'programmer']);
        $this->actingAs($this->user->fresh());
        $invoice = $this->invoice(1000000);

        // Kontrol positif: sebelum ada pembayaran, simpan jalan.
        Livewire::test(\App\Filament\Admin\Resources\InvoiceResource\Pages\EditInvoice::class, ['record' => $invoice->id])->call('save');
        $this->assertFalse($invoice->fresh()->hasActivePayment());

        $this->bayar(400000, [$invoice->id => 400000]);
        $this->assertTrue($invoice->fresh()->hasActivePayment());

        $this->get(\App\Filament\Admin\Resources\InvoiceResource::getUrl('edit', ['record' => $invoice->id]))
            ->assertRedirect(\App\Filament\Admin\Resources\InvoiceResource::getUrl('view', ['record' => $invoice->id]));

        // Tab yang sudah terbuka sebelum pembayaran dicatat tidak boleh menimpa.
        $terbuka = Livewire::test(\App\Filament\Admin\Resources\InvoiceResource\Pages\EditInvoice::class, ['record' => $this->invoice(500000)->id]);
        $lain = Invoice::latest('id')->first();
        $p = \App\Models\Payment::create(['customer_group_id' => $this->group->id, 'bank_account_id' => $this->bank->id, 'payment_date' => now()->toDateString(), 'amount' => 100000, 'total_deduction' => 0]);
        $p->allocations()->create(['invoice_id' => $lain->id, 'amount_allocated' => 100000]);
        $this->assertTrue($lain->fresh()->hasActivePayment(), 'fixture: invoice kedua sudah dibayar');
        $terbuka->set('data.invoice_date', now()->subDays(50)->toDateString())->call('save');
        $this->assertNotSame(now()->subDays(50)->toDateString(), $lain->fresh()->invoice_date->toDateString(), 'simpan paksa ditolak');

        // Pembayaran dibatalkan: invoice terbuka lagi.
        \App\Models\Payment::active()->get()->each->cancel('uji');
        $this->assertFalse($invoice->fresh()->hasActivePayment());
    }

    /** OPT-01: rekening nonaktif tidak diterima walau id-nya dikirim dari browser. */
    public function test_an_inactive_account_is_refused_on_the_server(): void
    {
        $this->invoice(500000);
        $this->bank->update(['is_active' => false]);

        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->set('data.bank_account_id', $this->bank->id)
            ->set('data.payment_date', now()->toDateString())
            ->set('data.amount', '500.000')
            ->call('save')
            ->assertHasErrors(['data.bank_account_id']);
    }
}
