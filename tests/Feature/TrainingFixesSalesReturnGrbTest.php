<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages\ListPendingPoProducts;
use App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages\NewGoodsReceiptProduct;
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
    public function a_receipt_is_born_on_save_and_a_second_person_saving_the_same_po_is_refused(): void
    {
        $supplier = Supplier::create(['name' => 'FIXTURE SUPPLIER', 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top_days' => 30, 'is_active' => true]);
        $requester = User::factory()->create();
        $requisition = ProductRequisition::create(['supplier_id' => $supplier->id, 'document_number' => 'PR-1', 'user_id' => $requester->id, 'due_date' => now()->addWeek()->toDateString(), 'status' => 'Approved']);
        $po = PurchaseProduct::create(['product_requisition_id' => $requisition->id, 'po_number' => 'PO-1', 'po_date' => now()->toDateString(), 'supplier_id' => $supplier->id, 'total_amount' => 0, 'status' => 'pending']);

        $make = function () {
            $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
            foreach (['view_goods_receipt_products', 'edit_goods_receipt_products'] as $permission) {
                $user->permissions()->attach(Permission::firstOrCreate(['name' => $permission], ['module_name' => 'GR Beef', 'description' => $permission])->id);
            }

            return $user->fresh();
        };

        // Menekan Process GR dan membuka formulir TIDAK melahirkan dokumen.
        $first = Livewire::actingAs($make())->test(NewGoodsReceiptProduct::class, ['purchaseProduct' => $po]);
        $second = Livewire::actingAs($make())->test(NewGoodsReceiptProduct::class, ['purchaseProduct' => $po]);
        $this->assertSame(0, GoodsReceiptProduct::where('purchase_product_id', $po->id)->count());

        $first->set('data.receive_date', now()->toDateString())->call('saveGr');
        $this->assertSame(1, GoodsReceiptProduct::where('purchase_product_id', $po->id)->count());
        $this->assertSame('completed', $po->fresh()->status);

        // Orang kedua menyimpan formulir yang dibuka sebelum orang pertama selesai: ditolak, tidak ada GRB kedua.
        $second->set('data.receive_date', now()->toDateString())->call('saveGr');
        $this->assertSame(1, GoodsReceiptProduct::where('purchase_product_id', $po->id)->count());
    }

    /** @test */
    public function the_pending_list_opens_the_form_or_continues_an_unlocked_receipt(): void
    {
        $supplier = Supplier::create(['name' => 'FIXTURE SUPPLIER', 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top_days' => 30, 'is_active' => true]);
        $requisition = ProductRequisition::create(['supplier_id' => $supplier->id, 'document_number' => 'PR-2', 'user_id' => User::factory()->create()->id, 'due_date' => now()->addWeek()->toDateString(), 'status' => 'Approved']);
        $po = PurchaseProduct::create(['product_requisition_id' => $requisition->id, 'po_number' => 'PO-2', 'po_date' => now()->toDateString(), 'supplier_id' => $supplier->id, 'total_amount' => 0, 'status' => 'pending']);

        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        foreach (['view_goods_receipt_products', 'edit_goods_receipt_products'] as $permission) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $permission], ['module_name' => 'GR Beef', 'description' => $permission])->id);
        }

        Livewire::actingAs($user->fresh())->test(ListPendingPoProducts::class)
            ->assertTableActionHasUrl('process_gr', \App\Filament\Admin\Resources\GoodsReceiptProductResource::getUrl('new', ['purchaseProduct' => $po->id]), $po);
        $this->assertSame(0, GoodsReceiptProduct::count(), 'tombol tidak membuat dokumen');

        $open = GoodsReceiptProduct::create(['purchase_product_id' => $po->id, 'supplier_id' => $supplier->id, 'receive_date' => now()->toDateString(), 'created_by' => $user->id]);

        Livewire::actingAs($user->fresh())->test(ListPendingPoProducts::class)
            ->assertTableActionHasUrl('process_gr', \App\Filament\Admin\Resources\GoodsReceiptProductResource::getUrl('input', ['record' => $open->id]), $po);
    }
}
