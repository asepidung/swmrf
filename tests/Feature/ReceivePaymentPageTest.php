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
 * Halaman penerimaan pembayaran piutang.
 *
 * Halaman ini MENERIMA UANG dan sampai sekarang tidak punya satu pun
 * pengujian. Satu-satunya cara mencobanya adalah merangkai pelanggan, produk,
 * Sales Order, tally, surat jalan, bukti terima, dan invoice lebih dulu --
 * berjam-jam mengeklik, hanya untuk melihat apakah satu tombol bekerja.
 *
 * Itulah sebabnya ia tidak pernah diuji, dan itulah sebabnya kerusakannya
 * baru ketahuan dari Project Owner.
 *
 * Berkas ini merangkai keadaan yang sama dalam hitungan detik.
 */
class ReceivePaymentPageTest extends TestCase
{
    use RefreshDatabase;

    private CustomerGroup $group;

    private Invoice $invoice;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Kasir', 'username' => 'kasir_piutang', 'password' => 'secret-password',
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
            'name' => 'BIDADARI PUSAT',
            'address' => 'Bogor',
            'top' => 30,
            'customer_group_id' => $this->group->id,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
        ]);

        $so = SalesOrder::create([
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
            'created_by' => $user->id,
            'status' => 'completed',
        ]);

        $this->invoice = Invoice::create([
            'customer_id' => $customer->id,
            'sales_order_id' => $so->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 30,
            'status' => 'Belum Dibayar',
            'subtotal' => 11179000,
            'charge' => 0,
            'down_payment' => 0,
            'created_by' => $user->id,
        ]);

        Receivable::create([
            'invoice_id' => $this->invoice->id,
            'customer_id' => $customer->id,
            'customer_group_id' => $this->group->id,
        ]);

        $this->bank = BankAccount::create([
            'initial' => 'BCA',
            'bank_name' => 'BANK CENTRAL ASIA',
            'account_number' => '1234567890',
            'account_holder' => 'WIJAYA MEAT',
            'is_active' => true,
        ]);
    }

    /**
     * Total piutang grupnya ikut ditampilkan.
     *
     * Tanpa angka ini yang mencatat mengetik nominal tanpa pembanding apa
     * pun -- ia tahu sisa tiap invoice satu per satu, tetapi tidak tahu
     * berapa seluruhnya.
     */
    public function test_the_page_shows_the_total_outstanding(): void
    {
        $this->get('/admin/receivables/'.$this->group->id.'/payment')
            ->assertSuccessful()
            ->assertSee('11.179.000');
    }

    /**
     * Halamannya terbuka lewat URL yang sungguhan.
     *
     * Sengaja lewat HTTP, bukan lewat Livewire::test, supaya jalurnya sama
     * persis dengan yang dibuka peramban -- termasuk route, middleware, dan
     * cara Filament menyerahkan parameter record ke halamannya.
     */
    public function test_the_page_opens_over_http(): void
    {
        $this->withoutExceptionHandling();

        $this->get('/admin/receivables/'.$this->group->id.'/payment')
            ->assertSuccessful()
            ->assertSee($this->invoice->invoice_number);
    }

    /** Pembayaran penuh tercatat, dan piutangnya lunas. */
    public function test_a_full_payment_settles_the_invoice(): void
    {
        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->fillForm([
                'bank_account_id' => $this->bank->id,
                'payment_date' => now()->toDateString(),
                'amount' => 11179000,
                'allocations.'.$this->invoice->id => 11179000,
            ])
            ->call('save');

        $segar = $this->invoice->fresh();

        $this->assertSame(11179000.0, (float) $segar->paid_amount);
        $this->assertSame(0.0, (float) $segar->balance);
        $this->assertSame('Lunas', $segar->status);

        $this->assertSame(1, Payment::count());
        $this->assertSame(11179000.0, (float) Payment::first()->amount);
    }

    /** Cicilan menyisakan sisa tagihan yang benar. */
    public function test_a_partial_payment_leaves_the_right_balance(): void
    {
        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->fillForm([
                'bank_account_id' => $this->bank->id,
                'payment_date' => now()->toDateString(),
                'amount' => 5000000,
                'allocations.'.$this->invoice->id => 5000000,
            ])
            ->call('save');

        $segar = $this->invoice->fresh();

        $this->assertSame(5000000.0, (float) $segar->paid_amount);
        $this->assertSame(6179000.0, (float) $segar->balance);
        $this->assertNotSame('Lunas', $segar->status);
    }

    /**
     * Alokasi yang tidak seimbang DITOLAK, dan tidak menyisakan apa pun.
     *
     * Uang yang masuk harus habis dibagi ke invoice. Kalau tidak, pembayaran
     * tercatat sementara tagihannya tidak berkurang sebanyak itu -- selisihnya
     * hilang tanpa jejak.
     */
    public function test_an_unbalanced_allocation_is_refused(): void
    {
        Livewire::test(ReceivePayment::class, ['record' => $this->group])
            ->fillForm([
                'bank_account_id' => $this->bank->id,
                'payment_date' => now()->toDateString(),
                'amount' => 5000000,
                'allocations.'.$this->invoice->id => 1000000,
            ])
            ->call('save');

        $this->assertSame(0, Payment::count(), 'Pembayaran tidak boleh tercatat.');
        $this->assertSame(0.0, (float) $this->invoice->fresh()->paid_amount);
    }

    /** Yang tidak berhak menerima uang tidak boleh membuka halamannya. */
    public function test_the_page_is_closed_to_everyone_without_the_permission(): void
    {
        $biasa = User::create([
            'name' => 'Biasa', 'username' => 'tanpa_hak_terima', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        $this->actingAs($biasa);

        $this->assertFalse(ReceivePayment::canAccess(['record' => $this->group]));
    }
}
