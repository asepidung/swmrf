<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderReceipt;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Tally;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retur penjualan memotong tagihan pelanggan.
 *
 * Sampai 4 September 2026 menyetujui retur mengembalikan barangnya ke stok
 * tetapi tidak menyentuh uang sama sekali: barang senilai tiga juta kembali,
 * tagihannya tetap penuh.
 *
 * Yang diuji di sini SISI PELANGGAN saja -- berapa yang dipotong dari tagihan.
 * Angkanya harga jual, dan harga jual ada di invoice. Nilai persediaan barang
 * returnya (HPP) urusan lain yang menunggu BOM.
 */
class SalesReturnCreditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CustomerGroup $group;

    private Customer $customer;

    private Product $sirloin;

    private Product $ribeye;

    private Warehouse $warehouse;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Penagih', 'username' => 'penagih_retur',
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->group = CustomerGroup::create(['name' => 'BIDADARI']);

        $this->customer = Customer::create([
            'name' => 'BIDADARI PUSAT',
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
            'customer_group_id' => $this->group->id,
            'address' => 'Bogor',
            'top' => 30,
        ]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);

        $this->sirloin = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT00100',
            'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->ribeye = Product::create([
            'name' => 'RIBEYE', 'code' => 'MT00200',
            'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create(['code' => 'PERUM', 'name' => 'PERUM', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
    }

    // =====================================================================
    // Perkakas: satu rangkaian SO -> DO -> Bukti Terima -> Invoice
    // =====================================================================

    private SalesOrder $salesOrder;

    private DeliveryOrder $deliveryOrder;

    private DeliveryOrderReceipt $receipt;

    /**
     * @param  array<int, array{produk: Product, berat: float, harga: float, diskon: float}>  $baris
     */
    private function kirim(array $baris): void
    {
        $this->salesOrder = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        foreach ($baris as $b) {
            SalesOrderItem::create([
                'sales_order_id' => $this->salesOrder->id,
                'product_id' => $b['produk']->id,
                'weight' => $b['berat'],
                'price' => $b['harga'],
                'discount' => $b['diskon'] ?? 0,
            ]);
        }

        $tally = Tally::create(['sales_order_id' => $this->salesOrder->id, 'status' => 'locked']);

        $this->deliveryOrder = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $this->salesOrder->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(),
            'driver' => 'Joko',
            'status' => 'Delivered',
        ]);

        $this->receipt = DeliveryOrderReceipt::create([
            'delivery_order_id' => $this->deliveryOrder->id,
            'sales_order_id' => $this->salesOrder->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(),
            'receipt_number' => 'POD-'.$this->deliveryOrder->id,
            'total_box' => 0,
            'total_weight' => 0,
            'status' => 'Approved',
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * @param  array<int, array{produk: Product, berat: float, harga: float, diskon?: float}>  $baris
     */
    private function tagih(array $baris): Invoice
    {
        $subtotal = 0.0;

        $invoice = Invoice::create([
            'delivery_order_receipt_id' => $this->receipt->id,
            'customer_id' => $this->customer->id,
            'sales_order_id' => $this->salesOrder->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 30,
            'status' => 'Belum Dibayar',
            'subtotal' => 0,
            'charge' => 0,
            'down_payment' => 0,
            'created_by' => $this->user->id,
        ]);

        foreach ($baris as $b) {
            $kotor = $b['berat'] * $b['harga'];
            $diskonRp = round($kotor * (($b['diskon'] ?? 0) / 100), 0);
            $jumlah = round($kotor - $diskonRp, 0);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $b['produk']->id,
                'box' => 1,
                'weight' => $b['berat'],
                'price' => $b['harga'],
                'discount_percent' => $b['diskon'] ?? 0,
                'discount_rp' => $diskonRp,
                'amount' => $jumlah,
            ]);

            $subtotal += $jumlah;
        }

        $invoice->subtotal = $subtotal;
        $invoice->save();

        Receivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'customer_group_id' => $this->group->id,
        ]);

        return $invoice->fresh();
    }

    /**
     * @param  array<int, array{produk: Product, berat: float}>  $baris
     */
    private function retur(array $baris): SalesReturn
    {
        $retur = SalesReturn::create([
            'return_date' => now()->toDateString(),
            'delivery_order_id' => $this->deliveryOrder->id,
            'customer_id' => $this->customer->id,
            'status' => 'Draft',
            'created_by' => $this->user->id,
        ]);

        foreach ($baris as $i => $b) {
            SalesReturnItem::create([
                'sales_return_id' => $retur->id,
                'product_id' => $b['produk']->id,
                'warehouse_id' => $this->warehouse->id,
                'grade_id' => $this->grade->id,
                'barcode' => 'RET-'.$retur->id.'-'.$i,
                'weight' => $b['berat'],
                'qty_pcs' => 1,
                'pack_date' => now()->toDateString(),
                'origin' => '1',
            ]);
        }

        return $retur->fresh();
    }

    private function bayar(Invoice $invoice, float $jumlah): Payment
    {
        $bank = BankAccount::firstOrCreate(
            ['account_number' => '1234567890'],
            [
                'initial' => 'BCA', 'bank_name' => 'BANK CENTRAL ASIA',
                'account_holder' => 'WIJAYA MEAT', 'is_active' => true,
            ],
        );

        $payment = Payment::create([
            'customer_group_id' => $this->group->id,
            'bank_account_id' => $bank->id,
            'payment_date' => now()->toDateString(),
            'amount' => $jumlah,
            'total_deduction' => 0,
            'created_by' => $this->user->id,
        ]);

        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_allocated' => $jumlah,
        ]);

        $invoice->applyPayment($jumlah);

        return $payment->fresh();
    }

    // =====================================================================
    // Keadaan 1: invoice sudah ada, belum dibayar
    // =====================================================================

    public function test_an_approved_return_reduces_an_unpaid_bill(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 0]]);
        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);

        $this->assertSame(10000000.0, $invoice->billedAmount());

        $retur = $this->retur([['produk' => $this->sirloin, 'berat' => 20]]);
        $retur->approve();

        $invoice->refresh();

        $this->assertSame(2000000.0, $invoice->returnedAmount());
        $this->assertSame(8000000.0, $invoice->billedAmount());
        $this->assertSame(8000000.0, (float) $invoice->balance);
        $this->assertSame($invoice->id, $retur->fresh()->invoice_id);
    }

    /**
     * Diskon barisnya ikut, karena harganya diambil dari `amount / weight`.
     *
     * Barang berdiskon 2% yang dikembalikan harus dikreditkan sebesar yang
     * DITAGIHKAN, bukan sebesar harga sebelum diskon. Kalau tidak, tiap retur
     * diam-diam mengembalikan lebih banyak daripada yang pernah diterima.
     */
    public function test_the_credit_follows_the_discount_the_customer_actually_got(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 2]]);
        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 2]]);

        // 100 kg x 100.000 = 10.000.000, diskon 2% = 200.000, ditagih 9.800.000
        $this->assertSame(9800000.0, $invoice->billedAmount());

        $retur = $this->retur([['produk' => $this->sirloin, 'berat' => 10]]);
        $retur->approve();

        // 10 kg dari yang ditagih 98.000/kg
        $this->assertSame(980000.0, $invoice->fresh()->returnedAmount());
        $this->assertSame(98000.0, (float) $retur->items()->first()->unit_price);
    }

    public function test_only_approved_returns_touch_the_bill(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 0]]);
        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);

        $this->retur([['produk' => $this->sirloin, 'berat' => 20]]);

        $this->assertSame(0.0, $invoice->fresh()->returnedAmount());
        $this->assertSame(10000000.0, $invoice->fresh()->billedAmount());
    }

    public function test_unlocking_a_return_puts_the_bill_back(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 0]]);
        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);

        $retur = $this->retur([['produk' => $this->sirloin, 'berat' => 20]]);
        $retur->approve();

        $this->assertSame(8000000.0, $invoice->fresh()->billedAmount());

        $retur->fresh()->unlock();

        $invoice->refresh();
        $this->assertSame(0.0, $invoice->returnedAmount());
        $this->assertSame(10000000.0, $invoice->billedAmount());
        $this->assertNull($retur->fresh()->invoice_id);
        $this->assertSame(0.0, (float) $retur->fresh()->credit_amount);
    }

    // =====================================================================
    // Keadaan 2: invoicenya belum dibuat saat barangnya dikembalikan
    // =====================================================================

    /**
     * Retur bisa terjadi SEBELUM invoicenya ada. Barangnya balik hari itu
     * juga; tagihannya menyusul seminggu kemudian.
     */
    public function test_a_return_made_before_the_bill_exists_waits_and_then_attaches(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 0]]);

        $retur = $this->retur([['produk' => $this->sirloin, 'berat' => 20]]);
        $retur->approve();

        // Belum menempel ke mana pun, tapi sudah dinilai.
        $this->assertNull($retur->fresh()->invoice_id);
        $this->assertSame(2000000.0, (float) $retur->fresh()->credit_amount);

        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);
        $invoice->collectPendingSalesReturns();

        $invoice->refresh();
        $this->assertSame($invoice->id, $retur->fresh()->invoice_id);
        $this->assertSame(2000000.0, $invoice->returnedAmount());
        $this->assertSame(8000000.0, (float) $invoice->balance);
    }

    /**
     * Harganya diambil dari Sales Order, dengan rumus yang sama persis dengan
     * yang dipakai invoice -- jadi angkanya tidak bisa berbeda dari invoice
     * yang akan terbit.
     */
    public function test_a_waiting_return_is_priced_from_the_sales_order(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 2]]);

        $retur = $this->retur([['produk' => $this->sirloin, 'berat' => 10]]);
        $retur->approve();

        // 10 x 100.000 = 1.000.000, diskon 2% = 20.000 -> 980.000
        $this->assertSame(980000.0, (float) $retur->fresh()->credit_amount);
    }

    // =====================================================================
    // Keadaan 3: invoicenya sudah dibayar
    // =====================================================================

    /**
     * Uangnya sudah masuk, jadi tidak ada lagi yang bisa dipotong.
     *
     * Kelebihannya dilepas dari alokasinya dan kembali menjadi DEPOSIT
     * pelanggan -- kolam yang sama dengan lebih bayar biasa, bukan kolam
     * kedua khusus retur.
     */
    public function test_a_return_on_a_paid_bill_turns_into_customer_deposit(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 0]]);
        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);

        $this->bayar($invoice, 10000000);

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(0.0, $this->group->availableDeposit());

        $retur = $this->retur([['produk' => $this->sirloin, 'berat' => 20]]);
        $retur->approve();

        $invoice->refresh();

        // Tagihannya turun, yang sudah dibayar ikut turun, sisanya tetap nol.
        $this->assertSame(8000000.0, $invoice->billedAmount());
        $this->assertSame(8000000.0, (float) $invoice->paid_amount);
        $this->assertSame(0.0, (float) $invoice->balance);

        // Dan dua jutanya sekarang menjadi deposit pelanggan.
        $this->assertSame(2000000.0, $this->group->availableDeposit());
    }

    /**
     * Yang dilepas hanya sebesar kelebihannya, bukan seluruh alokasinya.
     */
    public function test_a_partly_paid_bill_only_releases_what_became_excess(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 0]]);
        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);

        // Dibayar 9 juta dari 10 juta.
        $this->bayar($invoice, 9000000);

        // Lalu dikembalikan 20 kg senilai 2 juta -- tagihannya jadi 8 juta,
        // sementara yang sudah dibayar 9 juta. Kelebihannya 1 juta saja.
        $retur = $this->retur([['produk' => $this->sirloin, 'berat' => 20]]);
        $retur->approve();

        $invoice->refresh();

        $this->assertSame(8000000.0, $invoice->billedAmount());
        $this->assertSame(8000000.0, (float) $invoice->paid_amount);
        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertSame(1000000.0, $this->group->availableDeposit());
    }

    /**
     * Retur yang lebih kecil daripada sisa tagihan tidak melepas apa pun.
     */
    public function test_a_return_smaller_than_the_outstanding_balance_releases_nothing(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 0]]);
        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);

        $this->bayar($invoice, 3000000);

        $retur = $this->retur([['produk' => $this->sirloin, 'berat' => 20]]);
        $retur->approve();

        $invoice->refresh();

        $this->assertSame(8000000.0, $invoice->billedAmount());
        $this->assertSame(3000000.0, (float) $invoice->paid_amount);
        $this->assertSame(5000000.0, (float) $invoice->balance);
        $this->assertSame(0.0, $this->group->availableDeposit());
    }

    // =====================================================================
    // Yang tidak boleh terjadi
    // =====================================================================

    /**
     * Harga di nota retur adalah SNAPSHOT.
     *
     * Sesudah returnya disetujui, mengubah harga di invoicenya tidak boleh
     * menggeser nilai yang sudah dikreditkan. Angka yang sudah disepakati
     * tidak bergerak sendiri.
     */
    public function test_the_credit_does_not_move_when_the_invoice_price_changes_later(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 0]]);
        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);

        $retur = $this->retur([['produk' => $this->sirloin, 'berat' => 20]]);
        $retur->approve();

        $this->assertSame(2000000.0, (float) $retur->fresh()->credit_amount);

        $baris = $invoice->items()->first();
        $baris->update(['price' => 250000, 'amount' => 25000000]);
        $invoice->update(['subtotal' => 25000000]);

        $this->assertSame(2000000.0, (float) $retur->fresh()->credit_amount);
        $this->assertSame(2000000.0, $invoice->fresh()->returnedAmount());
    }

    /**
     * Barang yang tidak ada di invoice maupun Sales Order berharga NOL, dan
     * nolnya terbaca -- bukan ditebak.
     */
    public function test_an_item_that_matches_nothing_is_credited_zero_rather_than_guessed(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 0]]);
        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);

        $retur = $this->retur([['produk' => $this->ribeye, 'berat' => 20]]);
        $retur->approve();

        $this->assertSame(0.0, (float) $retur->fresh()->credit_amount);
        $this->assertSame(10000000.0, $invoice->fresh()->billedAmount());
    }

    /**
     * Invoice yang dihapus melepaskan returnya, bukan menyeretnya ikut hilang.
     *
     * Barangnya sudah benar-benar kembali ke gudang. Yang hilang cuma invoice
     * yang dipotongnya, dan invoice pengganti untuk surat jalan yang sama
     * harus bisa memungutnya lagi.
     */
    public function test_deleting_the_invoice_frees_the_return_for_its_replacement(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 0]]);
        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);

        $retur = $this->retur([['produk' => $this->sirloin, 'berat' => 20]]);
        $retur->approve();

        $this->assertSame($invoice->id, $retur->fresh()->invoice_id);

        $invoice->delete();

        $this->assertNull($retur->fresh()->invoice_id);
        $this->assertSame(2000000.0, (float) $retur->fresh()->credit_amount);

        $pengganti = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);
        $pengganti->collectPendingSalesReturns();

        $this->assertSame($pengganti->id, $retur->fresh()->invoice_id);
        $this->assertSame(8000000.0, (float) $pengganti->fresh()->balance);
    }

    /**
     * Dua retur pada invoice yang sama dijumlahkan, tidak saling menimpa.
     */
    public function test_two_returns_on_one_bill_add_up(): void
    {
        $this->kirim([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000, 'diskon' => 0]]);
        $invoice = $this->tagih([['produk' => $this->sirloin, 'berat' => 100, 'harga' => 100000]]);

        $this->retur([['produk' => $this->sirloin, 'berat' => 20]])->approve();
        $this->retur([['produk' => $this->sirloin, 'berat' => 5]])->approve();

        $invoice->refresh();

        $this->assertSame(2500000.0, $invoice->returnedAmount());
        $this->assertSame(7500000.0, (float) $invoice->balance);
    }
}
