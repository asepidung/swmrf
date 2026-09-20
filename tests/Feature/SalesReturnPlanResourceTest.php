<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SalesReturnPlanResource;
use App\Filament\Admin\Resources\SalesReturnPlanResource\Pages\CreateSalesReturnPlan;
use App\Filament\Admin\Resources\SalesReturnPlanResource\Pages\EditSalesReturnPlan;
use App\Filament\Admin\Resources\SalesReturnPlanResource\Pages\ListSalesReturnPlans;
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
 *
 * Susulan issue #478 (20 September 2026): halaman item terpisah
 * (`ManageSalesReturnPlanItems`) dihapus -- Create dan Edit sekarang
 * SATU halaman dengan Repeater item, samakan dengan Sales Order.
 */
class SalesReturnPlanResourceTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Product $product;

    private Product $productB;

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
        $this->productB = Product::create([
            'name' => 'RIBEYE', 'code' => 'MT002', 'category_id' => $category->id,
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

    /** @test */
    public function editing_a_plan_requires_edit_sales_return_plans_not_just_view(): void
    {
        $plan = $this->draftPlanWithItem();

        $this->actingAs($this->employee(['view_sales_return_plans']))
            ->get(SalesReturnPlanResource::getUrl('edit', ['record' => $plan]))
            ->assertForbidden();

        $this->actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->get(SalesReturnPlanResource::getUrl('edit', ['record' => $plan]))
            ->assertSuccessful();
    }

    // =========================================================================
    // Navigasi status
    // =========================================================================

    /** @test */
    public function editing_a_received_plan_redirects_to_the_view_page(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();
        $plan->markReceived();

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

    /**
     * Issue #478: Submitted sekarang JUGA bisa dibuka lewat Edit
     * (sebelumnya cuma lewat halaman item terpisah).
     *
     * @test
     */
    public function a_submitted_plan_can_also_be_opened_for_editing(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();

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

    /** @test */
    public function the_submit_action_is_hidden_once_the_plan_is_no_longer_draft(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();

        Livewire::actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->test(EditSalesReturnPlan::class, ['record' => $plan->getKey()])
            ->assertActionHidden('submit_plan');
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
            ->assertActionVisible('edit_plan')
            ->assertActionVisible('cancel_plan');

        $plan->markReceived();

        Livewire::actingAs($user)
            ->test(ViewSalesReturnPlan::class, ['record' => $plan->getKey()])
            ->assertActionHidden('edit_plan')
            ->assertActionHidden('cancel_plan');
    }

    // =========================================================================
    // Jalur end-to-end: header + item dalam satu halaman (issue #478)
    // =========================================================================

    /** @test */
    public function creating_a_plan_saves_the_header_and_its_items_in_one_step(): void
    {
        Livewire::actingAs($this->employee(['view_sales_return_plans', 'create_sales_return_plans']))
            ->test(CreateSalesReturnPlan::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'plan_date' => now()->toDateString(),
                'items' => [
                    ['product_id' => $this->product->id, 'claimed_weight' => 10, 'claimed_qty_pcs' => 2],
                    ['product_id' => $this->productB->id, 'claimed_weight' => 5],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = SalesReturnPlan::firstOrFail();
        $this->assertSame(2, $plan->items()->count());
        $this->assertSame(10.0, $plan->claimedWeightFor($this->product->id));
        $this->assertSame(5.0, $plan->claimedWeightFor($this->productB->id));
    }

    /** @test */
    public function a_plan_cannot_be_created_without_any_items(): void
    {
        Livewire::actingAs($this->employee(['view_sales_return_plans', 'create_sales_return_plans']))
            ->test(CreateSalesReturnPlan::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'plan_date' => now()->toDateString(),
                'items' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['items']);

        $this->assertSame(0, SalesReturnPlan::count());
    }

    /**
     * Server tetap menolak klaim yang melebihi terkirim di DO -- bukan
     * cuma validasi form (issue #478 eksplisit meminta ini tetap ada).
     * Seluruh plan batal, bukan header tersimpan sendirian.
     *
     * @test
     */
    public function creating_a_plan_with_a_claim_exceeding_the_delivery_order_fails_the_whole_save(): void
    {
        $user = $this->employee(['view_sales_return_plans', 'create_sales_return_plans']);
        $this->actingAs($user);

        $do = \App\Models\DeliveryOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->toDateString(),
            'driver_id' => \App\Models\Driver::firstOrCreate(['name' => 'Joko'])->id,
            'status' => 'Delivered',
        ]);
        \App\Models\DeliveryOrderItem::create([
            'delivery_order_id' => $do->id, 'product_id' => $this->product->id, 'box' => 1, 'weight' => 10,
        ]);

        Livewire::actingAs($user)
            ->test(CreateSalesReturnPlan::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'delivery_order_id' => $do->id,
                'plan_date' => now()->toDateString(),
                'items' => [
                    ['product_id' => $this->product->id, 'claimed_weight' => 999],
                ],
            ])
            ->call('create');

        $this->assertSame(0, SalesReturnPlan::count());
    }

    /** @test */
    public function editing_a_draft_plan_can_add_and_remove_items(): void
    {
        $plan = $this->draftPlanWithItem();
        $originalItem = $plan->items()->first();

        Livewire::actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->test(EditSalesReturnPlan::class, ['record' => $plan->getKey()])
            ->fillForm([
                'items' => [
                    ['id' => $originalItem->id, 'product_id' => $this->product->id, 'claimed_weight' => 20],
                    ['product_id' => $this->productB->id, 'claimed_weight' => 7],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $plan->refresh();
        $this->assertSame(2, $plan->items()->count());
        $this->assertSame(20.0, $plan->claimedWeightFor($this->product->id));
        $this->assertSame(7.0, $plan->claimedWeightFor($this->productB->id));
    }

    /**
     * Submitted: qty klaim baris yang sudah ada boleh dinego, tapi
     * produk baru tidak bisa ditambahkan lewat form ini (server yang
     * menegakkannya -- `SalesReturnPlanItem::booted()` `creating()`
     * menolak baris baru begitu plan bukan Draft lagi, ditest langsung
     * di sini karena form-nya sendiri sudah menyembunyikan tombol
     * tambah, jadi ini membuktikan lapis KEDUA-nya).
     *
     * @test
     */
    public function editing_a_submitted_plan_can_negotiate_the_claimed_weight(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();
        $originalItem = $plan->items()->first();

        Livewire::actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->test(EditSalesReturnPlan::class, ['record' => $plan->getKey()])
            ->fillForm([
                'items' => [
                    ['id' => $originalItem->id, 'product_id' => $this->product->id, 'claimed_weight' => 15],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(15.0, $plan->fresh()->claimedWeightFor($this->product->id));
    }

    /** @test */
    public function editing_a_submitted_plan_cannot_add_a_new_product(): void
    {
        $plan = $this->draftPlanWithItem();
        $plan->submit();
        $originalItem = $plan->items()->first();

        Livewire::actingAs($this->employee(['view_sales_return_plans', 'edit_sales_return_plans']))
            ->test(EditSalesReturnPlan::class, ['record' => $plan->getKey()])
            ->fillForm([
                'items' => [
                    ['id' => $originalItem->id, 'product_id' => $this->product->id, 'claimed_weight' => 10],
                    ['product_id' => $this->productB->id, 'claimed_weight' => 3],
                ],
            ])
            ->call('save');

        // Ditolak model (creating() guard) -- baris baru tidak pernah
        // benar-benar tersimpan, dan baris lamanya pun tidak berubah
        // karena semuanya dalam satu transaksi.
        $this->assertSame(1, $plan->fresh()->items()->count());
        $this->assertSame(10.0, $plan->fresh()->claimedWeightFor($this->product->id));
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
