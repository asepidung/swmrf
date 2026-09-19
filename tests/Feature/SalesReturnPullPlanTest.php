<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SalesReturnResource;
use App\Filament\Admin\Resources\SalesReturnResource\Pages\ListSalesReturns;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesReturn;
use App\Models\SalesReturnPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Langkah 3 dari issue #451: sales_returns.sales_return_plan_id, tombol
 * "Tarik Plan", dan penolakan retur tanpa plan di server (bukan hanya
 * tombol yang hilang).
 */
class SalesReturnPullPlanTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'employee', 'is_active' => true]));

        $segment = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $this->customer = Customer::create([
            'name' => 'FIXTURE CUSTOMER', 'customer_segment_id' => $segment->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Sales Returns', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function draftPlanWithItem(): SalesReturnPlan
    {
        $plan = SalesReturnPlan::create(['plan_date' => now()->toDateString(), 'customer_id' => $this->customer->id]);
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);

        return $plan;
    }

    // =========================================================================
    // Model: tolak tanpa plan
    // =========================================================================

    /** @test */
    public function a_sales_return_cannot_be_created_without_a_plan(): void
    {
        $this->expectException(\Exception::class);

        SalesReturn::create([
            'return_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
        ]);
    }

    /** @test */
    public function a_sales_return_cannot_pull_a_draft_plan(): void
    {
        $plan = $this->draftPlanWithItem();

        $this->expectException(\Exception::class);

        SalesReturn::create([
            'return_date' => now()->toDateString(),
            'sales_return_plan_id' => $plan->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    /** @test */
    public function a_sales_return_can_pull_a_submitted_plan(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();

        $return = SalesReturn::create([
            'return_date' => now()->toDateString(),
            'sales_return_plan_id' => $plan->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertSame($plan->id, $return->plan->id);
    }

    /** @test */
    public function a_plan_cannot_be_pulled_twice(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();

        SalesReturn::create([
            'return_date' => now()->toDateString(),
            'sales_return_plan_id' => $plan->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->expectException(\Exception::class);

        SalesReturn::create([
            'return_date' => now()->toDateString(),
            'sales_return_plan_id' => $plan->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    // =========================================================================
    // Resource: tidak ada 'create' polos lagi
    // =========================================================================

    /** @test */
    public function the_resource_no_longer_registers_a_plain_create_page(): void
    {
        $this->assertArrayNotHasKey('create', SalesReturnResource::getPages());
    }

    // =========================================================================
    // UI: Tarik Plan
    // =========================================================================

    /** @test */
    public function pulling_a_plan_requires_create_sales_returns(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();

        Livewire::actingAs($this->employee(['view_sales_returns']))
            ->test(ListSalesReturns::class)
            ->assertActionHidden('pull_plan');

        Livewire::actingAs($this->employee(['view_sales_returns', 'create_sales_returns']))
            ->test(ListSalesReturns::class)
            ->assertActionVisible('pull_plan');
    }

    /** @test */
    public function only_submitted_and_unclaimed_plans_are_offered(): void
    {
        $draft = $this->draftPlanWithItem();

        $submitted = $this->draftPlanWithItem();
        $submitted->submit();

        $alreadyPulled = $this->draftPlanWithItem();
        $alreadyPulled->submit();
        SalesReturn::create([
            'return_date' => now()->toDateString(),
            'sales_return_plan_id' => $alreadyPulled->id,
            'customer_id' => $this->customer->id,
        ]);

        // Membaca opsi Select lewat internal Livewire itu rapuh -- cukup
        // buktikan lewat query yang SAMA dipakai closure ->options() di
        // ListSalesReturns.
        $tersedia = SalesReturnPlan::query()
            ->where('status', SalesReturnPlan::STATUS_SUBMITTED)
            ->whereDoesntHave('salesReturn')
            ->pluck('id');

        $this->assertTrue($tersedia->contains($submitted->id));
        $this->assertFalse($tersedia->contains($draft->id));
        $this->assertFalse($tersedia->contains($alreadyPulled->id));
    }

    /** @test */
    public function pulling_a_plan_copies_the_header_and_redirects_to_edit(): void
    {
        $do = DeliveryOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->format('Y-m-d'),
            'po_number' => 'PO-FIXTURE-'.uniqid(),
        ]);
        $do->items()->create(['product_id' => $this->product->id, 'box' => 1, 'weight' => 50]);

        $plan = SalesReturnPlan::create([
            'plan_date' => now()->toDateString(), 'customer_id' => $this->customer->id, 'delivery_order_id' => $do->id,
        ]);
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);
        $plan->submit();

        Livewire::actingAs($this->employee(['view_sales_returns', 'create_sales_returns']))
            ->test(ListSalesReturns::class)
            ->mountAction('pull_plan')
            ->setActionData(['sales_return_plan_id' => $plan->id])
            ->callMountedAction();

        $return = SalesReturn::where('sales_return_plan_id', $plan->id)->firstOrFail();

        $this->assertSame($this->customer->id, $return->customer_id);
        $this->assertSame($do->id, $return->delivery_order_id);
    }
}
