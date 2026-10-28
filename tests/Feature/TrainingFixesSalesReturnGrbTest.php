<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages\ListPendingPoProducts;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\GoodsReceiptProduct;
use App\Models\Permission;
use App\Models\ProductRequisition;
use App\Models\PurchaseProduct;
use App\Models\SalesReturn;
use App\Models\SalesReturnPlan;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Temuan pelatihan 28 Oktober 2026: (1) retur yang dihapus lalu dibuat ulang dari plan yang sama error;
 * (2) dua orang menekan Process GR untuk PO yang sama dan lahir dua GRB.
 */
class TrainingFixesSalesReturnGrbTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): SalesReturnPlan
    {
        $segment = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $customer = Customer::create(['name' => 'FIXTURE CUSTOMER', 'customer_segment_id' => $segment->id, 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30]);

        $category = \App\Models\ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $product = \App\Models\Product::create(['name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);

        $plan = SalesReturnPlan::create(['plan_date' => now()->toDateString(), 'customer_id' => $customer->id]);
        $plan->items()->create(['product_id' => $product->id, 'claimed_weight' => 10]);

        return $plan;
    }

    private function pull(SalesReturnPlan $plan): SalesReturn
    {
        return SalesReturn::create(['return_date' => now()->toDateString(), 'sales_return_plan_id' => $plan->id, 'customer_id' => $plan->customer_id]);
    }

    /** @test */
    public function a_deleted_sales_return_releases_its_plan_so_it_can_be_pulled_again(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'employee', 'is_active' => true]));
        $plan = $this->plan();

        $first = $this->pull($plan);
        $first->delete();

        $second = $this->pull($plan);

        $this->assertSame($plan->id, $second->fresh()->sales_return_plan_id);
        $this->assertNull(SalesReturn::withTrashed()->find($first->id)->sales_return_plan_id);
        $this->assertSame($plan->id, SalesReturn::withTrashed()->find($first->id)->released_plan_id);
    }

    /** @test */
    public function a_deleted_return_can_only_be_restored_while_its_plan_is_still_free(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'employee', 'is_active' => true]));
        $plan = $this->plan();

        $first = $this->pull($plan);
        $first->delete();

        $trashed = SalesReturn::withTrashed()->find($first->id);
        $trashed->restore();
        $this->assertSame($plan->id, SalesReturn::find($first->id)->sales_return_plan_id, 'plan bebas: pemulihan memasangnya kembali');

        SalesReturn::find($first->id)->delete();
        $this->pull($plan);

        $this->expectException(\Exception::class);
        SalesReturn::withTrashed()->find($first->id)->restore();
    }

    /** @test */
    public function two_people_pressing_process_gr_on_the_same_po_share_one_receipt_until_it_is_locked(): void
    {
        $supplier = Supplier::create(['name' => 'FIXTURE SUPPLIER', 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top_days' => 30, 'is_active' => true]);
        $requester = User::factory()->create();
        $requisition = ProductRequisition::create(['supplier_id' => $supplier->id, 'document_number' => 'PR-1', 'user_id' => $requester->id, 'due_date' => now()->addWeek()->toDateString(), 'status' => 'Approved']);
        $po = PurchaseProduct::create(['product_requisition_id' => $requisition->id, 'po_number' => 'PO-1', 'po_date' => now()->toDateString(), 'supplier_id' => $supplier->id, 'total_amount' => 0, 'status' => 'pending']);

        $make = function (string $name) {
            $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
            foreach (['view_goods_receipt_products', 'edit_goods_receipt_products'] as $permission) {
                $user->permissions()->attach(Permission::firstOrCreate(['name' => $permission], ['module_name' => 'GR Beef', 'description' => $permission])->id);
            }

            return $user->fresh();
        };

        Livewire::actingAs($make('a'))->test(ListPendingPoProducts::class)->callTableAction('process_gr', $po);
        Livewire::actingAs($make('b'))->test(ListPendingPoProducts::class)->callTableAction('process_gr', $po);

        $this->assertSame(1, GoodsReceiptProduct::where('purchase_product_id', $po->id)->count());

        // Setelah penerimaan itu terkunci, penerimaan bertahap berikutnya memang boleh lahir.
        GoodsReceiptProduct::where('purchase_product_id', $po->id)->update(['is_locked' => true]);
        Livewire::actingAs($make('c'))->test(ListPendingPoProducts::class)->callTableAction('process_gr', $po);

        $this->assertSame(2, GoodsReceiptProduct::where('purchase_product_id', $po->id)->count());
    }
}
