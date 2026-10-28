<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SalesOrderResource\Pages\ListSalesOrders;
use App\Models\Customer;
use App\Models\MaterialRequisition;
use App\Models\ProductRequisition;
use App\Models\Supplier;
use App\Models\CustomerSegment;
use App\Models\Permission;
use App\Models\PurchaseMaterial;
use App\Models\PurchaseProduct;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\PlanDateFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pelatihan 28 Oktober 2026: marketing membuat SO untuk tanggal 29 tiga kali dan tidak muncul, karena filter
 * bawaan "tanggal 1 sampai hari ini". Dokumen plan (SO, PO) kini diperlihatkan sampai tanggal terjauhnya.
 */
class PlanDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private function salesOrder(string $date): SalesOrder
    {
        $customer = Customer::firstOrCreate(
            ['name' => 'PELANGGAN UJI'],
            ['address' => 'Bogor', 'top_days' => 30, 'customer_segment_id' => CustomerSegment::firstOrCreate(['name' => 'RETAIL'])->id],
        );

        return SalesOrder::create(['customer_id' => $customer->id, 'delivery_date' => $date, 'po_number' => 'PO-'.uniqid(), 'status' => 'ready']);
    }

    /** @test */
    public function the_limit_is_today_when_nothing_is_dated_in_the_future(): void
    {
        $this->assertSame(now()->toDateString(), PlanDateFilter::until(SalesOrder::class, 'delivery_date')->toDateString());

        $this->salesOrder(now()->subDay()->toDateString());

        $this->assertSame(now()->toDateString(), PlanDateFilter::until(SalesOrder::class, 'delivery_date')->toDateString());
    }

    /** @test */
    public function the_limit_follows_the_furthest_future_document(): void
    {
        $this->salesOrder(now()->addDay()->toDateString());
        $this->salesOrder(now()->addDays(5)->toDateString());

        $this->assertSame(now()->addDays(5)->toDateString(), PlanDateFilter::until(SalesOrder::class, 'delivery_date')->toDateString());
    }

    /** @test */
    public function a_sales_order_for_tomorrow_is_visible_in_the_list_by_default(): void
    {
        $tomorrow = $this->salesOrder(now()->addDay()->toDateString());
        $today = $this->salesOrder(now()->toDateString());

        $viewer = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $viewer->permissions()->attach(Permission::firstOrCreate(['name' => 'view_sales_orders'], ['module_name' => 'Sales Orders', 'description' => 'View'])->id);

        Livewire::actingAs($viewer->fresh())
            ->test(ListSalesOrders::class)
            ->assertCanSeeTableRecords([$tomorrow, $today]);
    }

    /** @test */
    public function purchase_orders_dated_ahead_move_the_limit_too(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'SUPPLIER A', 'address' => 'Bogor', 'pic' => 'A', 'phone' => '1', 'top_days' => 30, 'is_tax_11' => false, 'is_active' => true]);
        $mr = MaterialRequisition::create(['supplier_id' => $supplier->id, 'document_number' => 'MR-1', 'user_id' => $user->id, 'due_date' => now()->toDateString(), 'status' => 'Requested']);
        $pr = ProductRequisition::create(['supplier_id' => $supplier->id, 'document_number' => 'PR-1', 'user_id' => $user->id, 'due_date' => now()->toDateString(), 'status' => 'Requested']);

        PurchaseMaterial::create(['po_number' => 'PO-M', 'material_requisition_id' => $mr->id, 'supplier_id' => $supplier->id, 'approved_by' => $user->id, 'po_date' => now()->addDays(2)->toDateString(), 'status' => 'pending']);
        PurchaseProduct::create(['po_number' => 'PO-P', 'product_requisition_id' => $pr->id, 'supplier_id' => $supplier->id, 'approved_by' => $user->id, 'po_date' => now()->addDays(3)->toDateString(), 'status' => 'pending']);

        $this->assertSame(now()->addDays(2)->toDateString(), PlanDateFilter::until(PurchaseMaterial::class, 'po_date')->toDateString());
        $this->assertSame(now()->addDays(3)->toDateString(), PlanDateFilter::until(PurchaseProduct::class, 'po_date')->toDateString());
    }
}
