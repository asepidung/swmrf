<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SalesReturnPlanResource;
use App\Filament\Admin\Resources\SalesReturnPlanResource\Pages\CreateSalesReturnPlan;
use App\Filament\Admin\Resources\SalesReturnPlanResource\Pages\EditSalesReturnPlan;
use App\Filament\Admin\Resources\SalesReturnPlanResource\Pages\ListSalesReturnPlans;
use App\Filament\Admin\Resources\SalesReturnPlanResource\Pages\ManageSalesReturnPlanItems;
use App\Filament\Admin\Resources\SalesReturnPlanResource\Pages\ViewSalesReturnPlan;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesReturnPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Langkah 2 dari issue #451: Resource + halaman untuk Plan Sales Return,
 * dan route cetaknya. Aturan bisnis (klaim vs terkirim, kunci status)
 * sudah diuji tuntas di `SalesReturnPlanTest` (model) -- di sini fokus ke
 * OTORISASI dan NAVIGASI halaman, plus satu jalur end-to-end lewat
 * Livewire untuk membuktikan pengkabelannya benar.
 */
class SalesReturnPlanResourceTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

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
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Sales Return Plans', 'description' => $name])->id
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
    // Otorisasi Resource
    // =========================================================================

    /** @test */
    public function the_list_page_requires_view_sales_return_plans(): void
    {
        $this->actingAs($this->employee())
            ->get(SalesReturnPlanResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($this->employee(['view_sales_return_plans']))
            ->get(SalesReturnPlanResource::getUrl('index'))
            ->assertSuccessful();
    }

    /** @test */
    public function creating_a_plan_requires_create_sales_return_plans(): void
    {
        $this->actingAs($this->employee(['view_sales_return_plans']))
            ->get(SalesReturnPlanResource::getUrl('create'))
            ->assertForbidden();

        $this->actingAs($this->employee(['view_sales_return_plans', 'create_sales_return_plans']))
            ->get(SalesReturnPlanResource::getUrl('create'))
            ->assertSuccessful();
    }

    // =========================================================================
    // Navigasi status
    // =========================================================================

    /** @test */
    public function editing_a_non_draft_plan_redirects_to_the_view_page(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();

        Livewire::actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->test(EditSalesReturnPlan::class, ['record' => $plan->getKey()])
            ->assertRedirect(SalesReturnPlanResource::getUrl('view', ['record' => $plan]));
    }

    /** @test */
    public function a_draft_plan_can_be_opened_for_editing(): void
    {
        $plan = $this->draftPlanWithItem();

        Livewire::actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->test(EditSalesReturnPlan::class, ['record' => $plan->getKey()])
            ->assertSuccessful();
    }

    /** @test */
    public function submitting_from_the_edit_page_moves_the_plan_to_submitted(): void
    {
        $plan = $this->draftPlanWithItem();

        Livewire::actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->test(EditSalesReturnPlan::class, ['record' => $plan->getKey()])
            ->callAction('submit_plan');

        $this->assertSame(SalesReturnPlan::STATUS_SUBMITTED, $plan->fresh()->status);
    }

    /**
     * `view_sales_return_plans` sendirian tidak cukup untuk membatalkan
     * plan -- itu perubahan status, butuh `edit_sales_return_plans`.
     * Ditambal setelah `ActionAuthorizationTest` menangkap `cancel_plan`
     * tanpa pemeriksaan izin sama sekali.
     *
     * @test
     */
    public function cancelling_a_plan_requires_edit_sales_return_plans_not_just_view(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();

        Livewire::actingAs($this->employee(['view_sales_return_plans']))
            ->test(ViewSalesReturnPlan::class, ['record' => $plan->getKey()])
            ->assertActionHidden('cancel_plan');

        $this->assertSame(SalesReturnPlan::STATUS_SUBMITTED, $plan->fresh()->status);
    }

    /** @test */
    public function the_view_page_only_offers_negotiate_and_cancel_while_submitted(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();

        $user = $this->employee(['view_sales_return_plans', 'edit_sales_return_plans']);

        Livewire::actingAs($user)
            ->test(ViewSalesReturnPlan::class, ['record' => $plan->getKey()])
            ->assertActionVisible('manage_items')
            ->assertActionVisible('cancel_plan');

        $plan->markReceived();

        Livewire::actingAs($user)
            ->test(ViewSalesReturnPlan::class, ['record' => $plan->getKey()])
            ->assertActionHidden('manage_items')
            ->assertActionHidden('cancel_plan');
    }

    // =========================================================================
    // Halaman item: canAccess()
    // =========================================================================

    /** @test */
    public function the_items_page_requires_edit_sales_return_plans_not_just_view(): void
    {
        $plan = $this->draftPlanWithItem();

        $this->actingAs($this->employee(['view_sales_return_plans']))
            ->get(SalesReturnPlanResource::getUrl('items', ['record' => $plan]))
            ->assertForbidden();

        $this->actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->get(SalesReturnPlanResource::getUrl('items', ['record' => $plan]))
            ->assertSuccessful();
    }

    /** @test */
    public function the_items_page_is_closed_once_the_plan_is_received(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();
        $plan->markReceived();

        $this->actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->get(SalesReturnPlanResource::getUrl('items', ['record' => $plan]))
            ->assertForbidden();
    }

    // =========================================================================
    // Jalur end-to-end lewat Livewire
    // =========================================================================

    /** @test */
    public function an_item_can_be_created_and_deleted_through_the_items_page_while_draft(): void
    {
        $plan = SalesReturnPlan::create(['plan_date' => now()->toDateString(), 'customer_id' => $this->customer->id]);

        $component = Livewire::actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->test(ManageSalesReturnPlanItems::class, ['record' => $plan->getKey()])
            ->mountTableAction('create')
            ->setTableActionData(['product_id' => $this->product->id, 'claimed_weight' => 12])
            ->callMountedTableAction();

        $this->assertSame(1, $plan->items()->count());
        $item = $plan->items()->first();

        $component->mountTableAction('delete', $item->getKey())
            ->callMountedTableAction();

        $this->assertSame(0, $plan->items()->count());
    }

    /** @test */
    public function the_create_action_on_the_items_page_is_hidden_once_submitted(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();

        Livewire::actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->test(ManageSalesReturnPlanItems::class, ['record' => $plan->getKey()])
            ->assertTableActionHidden('create');
    }

    // =========================================================================
    // Route cetak
    // =========================================================================

    /** @test */
    public function the_print_route_requires_view_sales_return_plans(): void
    {
        $plan = $this->draftPlanWithItem();

        $this->actingAs($this->employee())
            ->get(route('sales-return-plan.print', $plan))
            ->assertForbidden();

        $this->actingAs($this->employee(['view_sales_return_plans']))
            ->get(route('sales-return-plan.print', $plan))
            ->assertSuccessful();
    }
}
