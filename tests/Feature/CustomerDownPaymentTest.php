<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SalesOrderResource\Pages\CreateSalesOrder;
use App\Filament\Admin\Resources\SalesOrderResource\Pages\EditSalesOrder;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\CustomerDownPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-05 (keputusan Ayah, 18 November 2026): DP pelanggan dicatat sebagai pembayaran yang masuk buku kas, mengurangi
 * invoice SO-nya, dan tidak menjadi deposit grup. DP lama dibiarkan sebagai riwayat.
 */
class CustomerDownPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private CustomerGroup $group;

    private BankAccount $bank;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->group = CustomerGroup::create(['name' => 'GRUP DP']);
        $segment = CustomerSegment::firstOrCreate(['name' => 'RETAIL'], ['is_active' => true]);
        $this->customer = Customer::create([
            'name' => 'PELANGGAN DP', 'customer_group_id' => $this->group->id, 'customer_segment_id' => $segment->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
        $this->bank = BankAccount::create(['initial' => '0', 'bank_name' => 'BCA', 'account_number' => '123', 'account_holder' => 'X', 'is_active' => true]);
        $category = ProductCategory::firstOrCreate(['name' => 'MEAT'], ['prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::firstOrCreate(['code' => 'MT001'], ['name' => 'SIRLOIN', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
    }

    private function buatSo(array $form)
    {
        return Livewire::actingAs($this->user)->test(CreateSalesOrder::class)->fillForm(array_merge([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDay()->format('Y-m-d'),
            'po_number' => 'PO-DP',
            'items' => ['item_a' => ['product_id' => $this->product->id, 'weight' => '10', 'price' => '50.000', 'discount' => 0, 'note' => '']],
        ], $form))->call('create');
    }

    private function soDenganDp(float $dp): SalesOrder
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->toDateString(), 'created_by' => $this->user->id,
            'status' => 'waiting', 'down_payment' => $dp,
        ]);

        return $so->fresh();
    }

    /** @test */
    public function a_down_payment_on_a_new_sales_order_is_recorded_as_money_in_the_cash_book(): void
    {
        $this->buatSo(['down_payment' => '500.000', 'down_payment_bank_account_id' => $this->bank->id])->assertHasNoFormErrors();

        $so = SalesOrder::firstOrFail();
        $payment = Payment::where('sales_order_id', $so->id)->firstOrFail();

        $this->assertSame($this->group->id, $payment->customer_group_id);
        $this->assertEquals(500000, $payment->amount);
        $this->assertSame(1, BankTransaction::where('type', 'in')->where('reference_type', Payment::class)->where('reference_id', $payment->id)->count());
        $this->assertEquals(500000, $this->bank->fresh()->currentBalance());
        $this->assertSame(0.0, $this->group->availableDeposit(), 'DP bukan deposit grup');
    }

    /** @test */
    public function a_down_payment_without_a_bank_account_is_refused(): void
    {
        $this->buatSo(['down_payment' => '500.000']);

        $this->assertSame(0, SalesOrder::count());
        $this->assertSame(0, Payment::count());
    }

    /** @test */
    public function an_invoice_cannot_take_more_down_payment_than_was_recorded(): void
    {
        $so = $this->soDenganDp(500000);
        CustomerDownPayment::record($so, $this->bank->id);

        $this->assertEquals(500000, CustomerDownPayment::availableForInvoice($so->id));

        Invoice::create([
            'customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'invoice_date' => now()->toDateString(), 'term_of_payment' => 30,
            'status' => 'Belum Dibayar', 'subtotal' => 900000, 'charge' => 0, 'down_payment' => 300000, 'created_by' => $this->user->id,
        ]);

        $this->assertEquals(200000, CustomerDownPayment::availableForInvoice($so->id), 'sisa setelah dipakai invoice pertama');
    }

    /** @test */
    public function without_a_recorded_down_payment_an_invoice_cannot_invent_one(): void
    {
        $so = $this->soDenganDp(0);

        $this->assertSame(0.0, CustomerDownPayment::availableForInvoice($so->id));
    }

    /** @test */
    public function a_legacy_down_payment_without_a_payment_stays_usable_as_history(): void
    {
        $so = $this->soDenganDp(400000); // DP lama: ada di SO, tidak pernah dicatat sebagai pembayaran

        $this->assertNull(CustomerDownPayment::activePayment($so));
        $this->assertEquals(400000, CustomerDownPayment::availableForInvoice($so->id));
    }

    /** @test */
    public function cancelling_the_down_payment_reverses_the_cash_book_and_clears_the_sales_order(): void
    {
        $so = $this->soDenganDp(500000);
        CustomerDownPayment::record($so, $this->bank->id);
        $this->assertEquals(500000, $this->bank->fresh()->currentBalance());

        Livewire::actingAs($this->user)->test(EditSalesOrder::class, ['record' => $so->id])
            ->callAction('cancel_down_payment', ['reason' => 'salah catat']);

        $this->assertEquals(0, $so->fresh()->down_payment);
        $this->assertEquals(0, $this->bank->fresh()->currentBalance(), 'buku kas dibalik');
        $this->assertNull(CustomerDownPayment::activePayment($so));
    }

    /** @test */
    public function a_down_payment_already_used_by_an_invoice_cannot_be_cancelled(): void
    {
        $so = $this->soDenganDp(500000);
        CustomerDownPayment::record($so, $this->bank->id);
        Invoice::create([
            'customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'invoice_date' => now()->toDateString(), 'term_of_payment' => 30,
            'status' => 'Belum Dibayar', 'subtotal' => 900000, 'charge' => 0, 'down_payment' => 500000, 'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)->test(EditSalesOrder::class, ['record' => $so->id])
            ->callAction('cancel_down_payment', ['reason' => 'coba']);

        $this->assertEquals(500000, $so->fresh()->down_payment);
        $this->assertEquals(500000, $this->bank->fresh()->currentBalance(), 'uang masuk tetap');
    }
}
