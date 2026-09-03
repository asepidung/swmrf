<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ReceivableResource\Pages\ListReceivables;
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
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Daftar piutang per grup pelanggan.
 *
 * Tiga hal yang dijaga di sini, ketiganya bekas kerusakan yang nyata:
 *
 *  - nominal dan HITUNGAN invoice harus menjawab dengan aturan yang sama;
 *  - membuka halamannya tidak boleh menembakkan kueri sebanyak jumlah barisnya;
 *  - nomor pembayaran tidak boleh bisa terulang.
 */
class ReceivableListingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Kasir', 'username' => 'kasir_daftar', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        $this->user->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'view_receivables'],
                ['module_name' => 'Receivables', 'description' => 'View receivables'],
            )->id
        );

        $this->actingAs($this->user->fresh());
    }

    /** Satu grup dengan satu invoice piutang. */
    private function grupDenganInvoice(string $nama, float $balance, ?string $dueDate = null): CustomerGroup
    {
        $group = CustomerGroup::create(['name' => $nama]);

        $customer = Customer::create([
            'name' => $nama.' PUSAT',
            'address' => 'Bogor',
            'top' => 30,
            'customer_group_id' => $group->id,
            'customer_segment_id' => CustomerSegment::firstOrCreate(
                ['name' => 'RETAIL'],
                ['is_active' => true],
            )->id,
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
            'subtotal' => $balance,
            'charge' => 0,
            'down_payment' => 0,
            'created_by' => $this->user->id,
        ]);

        if ($dueDate) {
            // Lewat query supaya hook saving tidak menghitung ulang jatuh temponya.
            Invoice::whereKey($invoice->id)->update(['due_date' => $dueDate]);
        }

        Receivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'customer_group_id' => $group->id,
        ]);

        return $group;
    }

    /**
     * Nominal dan hitungan invoice memakai aturan yang SAMA.
     *
     * Dulu nominalnya dijumlahkan lewat `join` mentah -- yang melewati
     * penyaring hapus-lunak invoice -- sementara hitungannya memakai
     * `whereHas` yang menerapkannya. Satu grup bisa menampilkan
     * "Rp 5.000.000 / 0 Inv": dua angka bersebelahan yang saling membantah.
     */
    public function test_the_amount_and_the_invoice_count_answer_with_one_rule(): void
    {
        $group = $this->grupDenganInvoice('BIDADARI', 5000000);

        $invoice = Invoice::firstOrFail();
        $invoice->delete();

        $terhitung = CustomerGroup::query()
            ->whereKey($group->id)
            ->withSum(['invoices as total' => fn ($q) => $q->where('invoices.status', '!=', Invoice::STATUS_PAID)], 'balance')
            ->withCount(['invoices as jumlah' => fn ($q) => $q->where('invoices.status', '!=', Invoice::STATUS_PAID)])
            ->first();

        $this->assertSame(
            0,
            (int) $terhitung->jumlah,
            'Invoice yang sudah dihapus tidak boleh terhitung.',
        );

        $this->assertSame(
            0.0,
            (float) ($terhitung->total ?? 0),
            'Dan nominalnya harus ikut nol, bukan tetap memakai invoice yang sudah dihapus.',
        );
    }

    /**
     * Membuka daftarnya tidak menembakkan kueri sebanyak jumlah barisnya.
     *
     * Dulu tiap kolom punya `getStateUsing` dan `description` yang berkueri
     * sendiri: enam kueri per baris grup. Angka pastinya tidak dipatok di
     * sini -- yang dijaga adalah bahwa menambah grup TIDAK menambah kueri.
     */
    public function test_the_listing_does_not_query_once_per_row(): void
    {
        foreach (['SATU', 'DUA', 'TIGA', 'EMPAT', 'LIMA'] as $nama) {
            $this->grupDenganInvoice($nama, 1000000);
        }

        DB::enableQueryLog();
        Livewire::test(ListReceivables::class)->assertSuccessful();
        $lima = count(DB::getQueryLog());

        foreach (['ENAM', 'TUJUH', 'DELAPAN', 'SEMBILAN', 'SEPULUH'] as $nama) {
            $this->grupDenganInvoice($nama, 1000000);
        }

        DB::flushQueryLog();
        Livewire::test(ListReceivables::class)->assertSuccessful();
        $sepuluh = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $lima,
            $sepuluh,
            "Jumlah kueri ikut bertambah saat grupnya bertambah: {$lima} untuk lima grup, "
            ."{$sepuluh} untuk sepuluh. Berarti masih ada kueri per baris.",
        );
    }

    /** Grup dan rekening seadanya, cuma supaya barisnya bisa tersimpan. */
    private function pembayaran(string $nomor): Payment
    {
        return Payment::create([
            'payment_number' => $nomor,
            'customer_group_id' => CustomerGroup::firstOrCreate(['name' => 'GRUP UJI'])->id,
            'bank_account_id' => BankAccount::firstOrCreate(
                ['account_number' => '0001'],
                [
                    'initial' => 'UJI', 'bank_name' => 'BANK UJI',
                    'account_holder' => 'WIJAYA MEAT', 'is_active' => true,
                ],
            )->id,
            'payment_date' => now()->toDateString(),
            'amount' => 0,
        ]);
    }

    /**
     * Nomor pembayaran tidak terulang meski ada nomor yang tidak terbaca.
     *
     * Rakitan lama membaca nomor pembayaran TERAKHIR menurut id dengan regex,
     * dan mengembalikan urutannya ke 1 begitu nomor itu tidak cocok. Nomor
     * yang sudah dipakai lalu dicoba lagi, dan `payment_number` bertanda
     * unique -- jadi akibatnya bukan salah nomor melainkan crash.
     */
    public function test_an_unreadable_number_does_not_reset_the_sequence(): void
    {
        $prefix = Payment::NUMBER_PREFIX.date('y');

        $this->pembayaran($prefix.'0007');

        // Baris terakhir sengaja berformat lain -- data lama, impor, apa pun.
        $this->pembayaran('WARISAN-LAMA');

        $this->assertSame($prefix.'0008', Payment::nextNumber());
    }

    /** Nomor milik dokumen yang sudah dihapus tidak boleh dipakai ulang. */
    public function test_a_deleted_payment_keeps_its_number_reserved(): void
    {
        $prefix = Payment::NUMBER_PREFIX.date('y');

        $payment = $this->pembayaran($prefix.'0012');
        $payment->delete();

        $this->assertSame($prefix.'0013', Payment::nextNumber());
    }
}
