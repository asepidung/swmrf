<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\Admin\Resources\SalesReturnResource\Pages\ListSalesReturns;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderReceipt;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SalesReturnPlan;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Issue #451 langkah 5, susulan 13 Sep (#387): nilai uang Sales Return
 * (tabel + cetakan) mengikuti view_invoices, dan layar Invoice menandai
 * retur yang memotong tagihannya.
 */
class SalesReturnMoneyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Product $product;

    private Warehouse $warehouse;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Petugas', 'username' => 'money_visibility_user',
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);
        $this->actingAs($this->user);

        $group = CustomerGroup::create(['name' => 'BIDADARI']);
        $this->customer = Customer::create([
            'name' => 'BIDADARI PUSAT',
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
            'customer_group_id' => $group->id,
            'address' => 'Bogor', 'top' => 30,
        ]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT00100',
            'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create(['code' => 'PERUM', 'name' => 'PERUM', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
    }

    private function grant(string $permission): void
    {
        $this->user->permissions()->attach(
            Permission::firstOrCreate(['name' => $permission], ['module_name' => 'x', 'description' => 'x'])->id
        );
        $this->actingAs($this->user->fresh());
    }

    /** Retur yang sudah Approved dan sungguhan memotong sebuah invoice. */
    private function approvedReturnReducingAnInvoice(): SalesReturn
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->toDateString(),
            'created_by' => $this->user->id, 'status' => 'ready',
        ]);
        SalesOrderItem::create([
            'sales_order_id' => $so->id, 'product_id' => $this->product->id,
            'weight' => 100, 'price' => 100000, 'discount' => 0,
        ]);
        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'locked']);
        TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => 'SHIP-001', 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => 100, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1',
        ]);
        $do = DeliveryOrder::create([
            'tally_id' => $tally->id, 'sales_order_id' => $so->id, 'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(),
            'driver_id' => \App\Models\Driver::firstOrCreate(['name' => 'Joko'])->id,
            'status' => 'Delivered',
        ]);
        $do->syncItemsFromTally();
        DeliveryOrderReceipt::create([
            'delivery_order_id' => $do->id, 'sales_order_id' => $so->id, 'customer_id' => $this->customer->id,
            'delivery_date' => now()->toDateString(), 'receipt_number' => 'POD-'.$do->id,
            'total_box' => 0, 'total_weight' => 0, 'status' => 'Approved', 'created_by' => $this->user->id,
        ]);
        $invoice = Invoice::create([
            'delivery_order_receipt_id' => $do->receipt->id, 'customer_id' => $this->customer->id,
            'sales_order_id' => $so->id, 'invoice_date' => now()->toDateString(), 'term_of_payment' => 30,
            'status' => 'Belum Dibayar', 'subtotal' => 10000000, 'charge' => 0, 'down_payment' => 0,
            'created_by' => $this->user->id,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'product_id' => $this->product->id, 'box' => 1,
            'weight' => 100, 'price' => 100000, 'discount_percent' => 0, 'discount_rp' => 0, 'amount' => 10000000,
        ]);
        Receivable::create(['invoice_id' => $invoice->id, 'customer_id' => $this->customer->id, 'customer_group_id' => $this->customer->customer_group_id]);

        $plan = SalesReturnPlan::create([
            'plan_date' => now()->toDateString(), 'customer_id' => $this->customer->id, 'delivery_order_id' => $do->id,
        ]);
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 100]);
        $plan->submit();

        $retur = SalesReturn::create([
            'return_date' => now()->toDateString(), 'sales_return_plan_id' => $plan->id,
            'customer_id' => $this->customer->id, 'delivery_order_id' => $do->id,
        ]);
        SalesReturnItem::create([
            'sales_return_id' => $retur->id, 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'barcode' => 'SHIP-001', 'weight' => 100, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'origin' => '1',
        ]);
        $retur->refresh()->approve();

        return $retur->fresh();
    }

    // =========================================================================
    // Tabel & cetakan Sales Return
    // =========================================================================

    /** @test */
    public function the_credit_and_invoice_columns_are_hidden_without_view_invoices(): void
    {
        $this->approvedReturnReducingAnInvoice();
        $this->grant('view_sales_returns');

        Livewire::test(ListSalesReturns::class)
            ->assertTableColumnDoesNotExist('credit_amount')
            ->assertTableColumnDoesNotExist('invoice_numbers');

        $this->grant('view_invoices');

        Livewire::test(ListSalesReturns::class)
            ->assertTableColumnExists('credit_amount')
            ->assertTableColumnExists('invoice_numbers');
    }

    /** @test */
    public function the_printed_return_hides_money_without_view_invoices(): void
    {
        $retur = $this->approvedReturnReducingAnInvoice();
        $this->grant('view_sales_returns');

        $this->get(route('sales-return.pdf', $retur))
            ->assertSuccessful()
            ->assertDontSee('Nilai retur');

        $this->grant('view_invoices');

        $this->get(route('sales-return.pdf', $retur))
            ->assertSuccessful()
            ->assertSee('Nilai retur');
    }

    // =========================================================================
    // Layar Invoice
    // =========================================================================

    /** @test */
    public function the_invoice_list_flags_invoices_reduced_by_a_return(): void
    {
        $retur = $this->approvedReturnReducingAnInvoice();
        $invoice = $retur->items()->first()->invoice;
        $this->grant('view_invoices');

        Livewire::test(ListInvoices::class)
            ->assertTableColumnStateSet('has_sales_returns', __('Reduced by Return'), $invoice);
    }
}
