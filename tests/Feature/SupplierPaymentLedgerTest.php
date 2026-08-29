<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PurchaseProductResource\Pages\ViewPurchaseProduct;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\PurchaseProduct;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * DP ke supplier adalah uang yang sungguh-sungguh keluar dan wajib tercatat
 * sebagai pengeluaran, terlepas dari kapan utangnya lahir.
 *
 * Sebelum ini, SupplierPayment tidak pernah menulis bank_transactions sama
 * sekali — DP transfer tidak mengurangi saldo akun mana pun, dan DP tunai
 * tidak tercatat di buku kas mana pun. Sisi piutang (ReceivePayment) sudah
 * benar sejak awal; sisi supplier belum punya pasangannya.
 */
class SupplierPaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer',
            'username' => 'ledger_programmer',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'H DONI',
            'address' => 'Bogor',
            'pic' => 'Doni',
            'top_days' => 30,
        ]);
    }

    protected function makePayment(array $overrides = []): SupplierPayment
    {
        return SupplierPayment::create(array_merge([
            'supplier_id' => $this->supplier->id,
            'source_type' => ProductRequisition::class,
            'source_id' => 1,
            'payment_date' => now()->toDateString(),
            'method' => SupplierPayment::METHOD_CASH,
            'amount' => 1000000,
        ], $overrides));
    }

    /** @test */
    public function a_cash_payment_lands_in_the_cash_account()
    {
        $payment = $this->makePayment(['method' => SupplierPayment::METHOD_CASH]);

        $kas = BankAccount::where('initial', BankAccount::CASH_INITIAL)->first();
        $this->assertNotNull($kas, 'Akun KAS tidak terbuat otomatis.');

        $transaction = BankTransaction::where('reference_type', SupplierPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($transaction, 'DP tunai tidak menulis bank_transactions sama sekali.');
        $this->assertSame('out', $transaction->type);
        $this->assertEquals(1000000, $transaction->amount);
        $this->assertSame($kas->id, $transaction->bank_account_id);
        $this->assertEquals(-1000000, $kas->fresh()->balance, 'Saldo KAS tidak berkurang.');
    }

    /** @test */
    public function two_cash_payments_share_the_same_cash_account()
    {
        $this->makePayment(['method' => SupplierPayment::METHOD_CASH, 'amount' => 500000]);
        $this->makePayment(['method' => SupplierPayment::METHOD_CASH, 'amount' => 300000]);

        $this->assertSame(1, BankAccount::where('initial', BankAccount::CASH_INITIAL)->count());

        $kas = BankAccount::where('initial', BankAccount::CASH_INITIAL)->first();
        $this->assertEquals(-800000, $kas->balance);
    }

    /** @test */
    public function a_transfer_payment_lands_in_the_chosen_bank_account()
    {
        $bca = BankAccount::create([
            'initial' => 'BCA',
            'bank_name' => 'Bank Central Asia',
            'account_number' => '1234567890',
            'account_holder' => 'Wijaya Meat',
            'is_active' => true,
        ]);

        $payment = $this->makePayment([
            'method' => SupplierPayment::METHOD_TRANSFER,
            'bank_account_id' => $bca->id,
            'amount' => 2000000,
        ]);

        $transaction = BankTransaction::where('reference_id', $payment->id)->first();

        $this->assertNotNull($transaction);
        $this->assertSame('out', $transaction->type);
        $this->assertSame($bca->id, $transaction->bank_account_id);
        $this->assertEquals(-2000000, $bca->fresh()->balance);

        // Transfer tidak boleh ikut membuat/menyentuh akun KAS.
        $this->assertNull(BankAccount::where('initial', BankAccount::CASH_INITIAL)->first());
    }

    /**
     * Data lama atau tidak lengkap tidak boleh menjatuhkan aplikasi.
     *
     * @test
     */
    public function a_transfer_payment_without_a_bank_account_does_not_crash()
    {
        $payment = $this->makePayment([
            'method' => SupplierPayment::METHOD_TRANSFER,
            'bank_account_id' => null,
        ]);

        $this->assertSame(0, BankTransaction::where('reference_id', $payment->id)->count());
    }

    /**
     * Rantai penuh: dari tombol Pay Down Payment di halaman PO sampai buku kas.
     *
     * Jalur DP-nya pindah (Finance Approval -> View PO, 27-28 Agustus 2026),
     * dan pencatatan kasnya ikut tanpa disentuh sama sekali. Itu memang
     * gunanya recordCashOutflow() dipasang sebagai model event di
     * SupplierPayment, bukan dipanggil manual di halaman yang membuatnya.
     *
     * @test
     */
    public function paying_a_down_payment_on_the_po_writes_the_ledger_end_to_end()
    {
        $category = ProductCategory::create(['name' => 'PRIMARY CUTS', 'prefix' => 1]);

        $product = Product::create([
            'name' => 'CUBEROLL',
            'code' => '100100',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        $requisition = ProductRequisition::create([
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => 'PO Created',
        ]);

        $requisition->items()->create([
            'product_id' => $product->id,
            'qty' => 300,
            'price' => 250000,
            'subtotal' => 75000000,
        ]);
        $requisition->updateTotalAmount();
        $requisition->generatePurchaseOrder();

        $po = PurchaseProduct::where('product_requisition_id', $requisition->id)->firstOrFail();

        Livewire::actingAs($this->user)
            ->test(ViewPurchaseProduct::class, ['record' => $po->id])
            ->callAction('pay_down_payment', [
                'payment_date' => now()->toDateString(),
                'method' => SupplierPayment::METHOD_CASH,
                'amount_input' => '10.000.000',
            ])
            ->assertHasNoActionErrors();

        $payment = SupplierPayment::where('source_type', PurchaseProduct::class)
            ->where('source_id', $po->id)
            ->first();

        $this->assertNotNull($payment, 'DP tidak tersimpan dari halaman PO.');
        $this->assertEquals(10000000, $payment->amount);

        $transaction = BankTransaction::where('reference_id', $payment->id)->first();
        $this->assertNotNull($transaction, 'DP dari halaman PO tidak sampai ke buku kas.');
        $this->assertSame('out', $transaction->type);
        $this->assertEquals(10000000, $transaction->amount);
    }
}
