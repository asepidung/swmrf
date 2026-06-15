<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\DeliveryPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryPlanTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $normalUser;
    protected Customer $customer1;
    protected Customer $customer2;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'programmer',
            'is_active' => true
        ]);

        $this->normalUser = User::factory()->create([
            'role' => 'employee',
            'is_active' => true
        ]);

        $segment = CustomerSegment::create([
            'name' => 'RETAIL',
            'is_active' => true,
        ]);

        $this->customer1 = Customer::create([
            'name' => 'CUSTOMER ALPHA',
            'customer_segment_id' => $segment->id,
            'address' => 'Jakarta',
            'pic' => 'John',
            'phone' => '0811',
            'top' => 30,
        ]);

        $this->customer2 = Customer::create([
            'name' => 'CUSTOMER BETA',
            'customer_segment_id' => $segment->id,
            'address' => 'Tangerang',
            'pic' => 'Jane',
            'phone' => '0812',
            'top' => 15,
        ]);

        $category = ProductCategory::create([
            'name' => 'MEAT',
            'prefix' => 'MT',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'TENDERLOIN BEEF',
            'code' => 'MT00100',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_creates_delivery_plan_automatically_when_sales_order_is_created()
    {
        $deliveryDate = now()->addDays(2)->format('Y-m-d');

        $so = SalesOrder::create([
            'customer_id' => $this->customer1->id,
            'delivery_date' => $deliveryDate,
            'po_number' => 'PO-001',
            'shipping_address' => 'Jakarta Barat',
            'created_by' => $this->adminUser->id,
        ]);

        $this->assertDatabaseHas('delivery_plans', [
            'customer_id' => $this->customer1->id,
            'delivery_date' => $deliveryDate,
        ]);

        $plan = DeliveryPlan::where('customer_id', $this->customer1->id)
            ->where('delivery_date', $deliveryDate)
            ->first();

        $this->assertNotNull($plan);
        $this->assertEquals($plan->id, $so->fresh()->delivery_plan_id);
    }

    /** @test */
    public function it_groups_multiple_sales_orders_for_the_same_customer_and_date()
    {
        $deliveryDate = now()->addDays(2)->format('Y-m-d');

        $so1 = SalesOrder::create([
            'customer_id' => $this->customer1->id,
            'delivery_date' => $deliveryDate,
            'po_number' => 'PO-A',
            'created_by' => $this->adminUser->id,
        ]);

        $so2 = SalesOrder::create([
            'customer_id' => $this->customer1->id,
            'delivery_date' => $deliveryDate,
            'po_number' => 'PO-B',
            'created_by' => $this->adminUser->id,
        ]);

        $this->assertEquals($so1->fresh()->delivery_plan_id, $so2->fresh()->delivery_plan_id);

        $plan = DeliveryPlan::find($so1->delivery_plan_id);
        $this->assertEquals(2, $plan->sales_orders_count);
    }

    /** @test */
    public function it_relocates_sales_order_to_new_plan_when_customer_or_date_changes()
    {
        $date1 = now()->addDays(2)->format('Y-m-d');
        $date2 = now()->addDays(3)->format('Y-m-d');

        $so = SalesOrder::create([
            'customer_id' => $this->customer1->id,
            'delivery_date' => $date1,
            'created_by' => $this->adminUser->id,
        ]);

        $oldPlanId = $so->delivery_plan_id;

        // Change delivery date
        $so->update(['delivery_date' => $date2]);

        $so = $so->fresh();
        $this->assertNotEquals($oldPlanId, $so->delivery_plan_id);

        $this->assertDatabaseHas('delivery_plans', [
            'customer_id' => $this->customer1->id,
            'delivery_date' => $date2,
        ]);
    }

    /** @test */
    public function it_does_not_delete_empty_plans_retaining_history()
    {
        $deliveryDate = now()->addDays(2)->format('Y-m-d');

        $so = SalesOrder::create([
            'customer_id' => $this->customer1->id,
            'delivery_date' => $deliveryDate,
            'created_by' => $this->adminUser->id,
        ]);

        $planId = $so->delivery_plan_id;

        // Move the SO to a different date
        $so->update(['delivery_date' => now()->addDays(5)->format('Y-m-d')]);

        // The old plan should still exist in the database (history retained)
        $this->assertDatabaseHas('delivery_plans', [
            'id' => $planId,
        ]);
    }

    /** @test */
    public function it_calculates_total_qty_correctly_on_delivery_plan()
    {
        $deliveryDate = now()->addDays(2)->format('Y-m-d');

        $so = SalesOrder::create([
            'customer_id' => $this->customer1->id,
            'delivery_date' => $deliveryDate,
            'created_by' => $this->adminUser->id,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $so->id,
            'product_id' => $this->product->id,
            'weight' => 20,
            'price' => 100000,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $so->id,
            'product_id' => $this->product->id,
            'weight' => 35,
            'price' => 100000,
        ]);

        $plan = DeliveryPlan::find($so->delivery_plan_id);
        $this->assertEquals(55, $plan->total_qty);
    }
}
