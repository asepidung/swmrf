<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesReturnPlan;
use App\Models\SalesReturnPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Langkah 1 dari issue #451: migrasi + model + policy + izin Plan Sales
 * Return. Belum ada Resource/halaman -- itu langkah 2. Aturan di sini
 * murni model, dan menjadi lapis pertahanan pertama yang tetap berlaku
 * bahkan sebelum form-nya ada.
 */
class SalesReturnPlanTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Customer $otherCustomer;

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
        $this->otherCustomer = Customer::create([
            'name' => 'OTHER CUSTOMER', 'customer_segment_id' => $segment->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    private function deliveryOrderFor(Customer $customer, float $delivered = 0): DeliveryOrder
    {
        $do = DeliveryOrder::create([
            'customer_id' => $customer->id, 'delivery_date' => now()->format('Y-m-d'),
            'po_number' => 'PO-FIXTURE-'.uniqid(),
        ]);

        if ($delivered > 0) {
            $do->items()->create(['product_id' => $this->product->id, 'box' => 1, 'weight' => $delivered]);
        }

        return $do;
    }

    private function plan(array $overrides = []): SalesReturnPlan
    {
        return SalesReturnPlan::create(array_merge([
            'plan_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
        ], $overrides));
    }

    // =========================================================================
    // Migrasi
    // =========================================================================

    /**
     * `view_deleted_sales_return_plans` sengaja belum di sini -- lihat
     * migrasi izinnya (menyusul langkah 2, bersama Resource yang
     * memakainya lewat TrashedFilter; guard `every_permission_the_code_
     * ignores_is_hidden` di UserPermissionFormTest menolak izin yang lahir
     * sebelum ada kode yang membacanya).
     *
     * @test
     */
    public function the_four_permissions_exist_after_migrating(): void
    {
        foreach ([
            'view_sales_return_plans', 'create_sales_return_plans',
            'edit_sales_return_plans', 'delete_sales_return_plans',
        ] as $name) {
            $this->assertTrue(Permission::where('name', $name)->exists(), "Izin $name tidak ada.");
        }
    }

    // =========================================================================
    // Model dasar
    // =========================================================================

    /** @test */
    public function creating_a_plan_autogenerates_a_prefixed_number_and_defaults_to_draft(): void
    {
        $plan = $this->plan();

        $this->assertStringStartsWith('SRP#'.date('y'), $plan->plan_number);
        $this->assertSame(SalesReturnPlan::STATUS_DRAFT, $plan->status);
    }

    /** @test */
    public function a_plan_may_be_created_without_a_delivery_order(): void
    {
        $plan = $this->plan();

        $this->assertNull($plan->delivery_order_id);
    }

    /** @test */
    public function a_delivery_order_belonging_to_another_customer_is_rejected(): void
    {
        $do = $this->deliveryOrderFor($this->otherCustomer);

        $this->expectException(\Exception::class);

        $this->plan(['delivery_order_id' => $do->id]);
    }

    // =========================================================================
    // Item: klaim vs terkirim
    // =========================================================================

    /** @test */
    public function an_item_can_be_added_while_the_plan_is_draft(): void
    {
        $plan = $this->plan();

        $item = $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);

        $this->assertNotNull($item->id);
    }

    /** @test */
    public function a_claim_exceeding_what_was_delivered_on_the_chosen_do_is_rejected(): void
    {
        $do = $this->deliveryOrderFor($this->customer, delivered: 20);
        $plan = $this->plan(['delivery_order_id' => $do->id]);

        $this->expectException(\Exception::class);

        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 25]);
    }

    /** @test */
    public function a_claim_within_what_was_delivered_on_the_chosen_do_is_accepted(): void
    {
        $do = $this->deliveryOrderFor($this->customer, delivered: 20);
        $plan = $this->plan(['delivery_order_id' => $do->id]);

        $item = $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 20]);

        $this->assertNotNull($item->id);
    }

    /** @test */
    public function a_product_never_delivered_on_the_chosen_do_is_rejected(): void
    {
        $do = $this->deliveryOrderFor($this->customer, delivered: 0);
        $plan = $this->plan(['delivery_order_id' => $do->id]);

        $this->expectException(\Exception::class);

        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 5]);
    }

    /** @test */
    public function a_plan_without_a_delivery_order_has_no_claim_ceiling(): void
    {
        $plan = $this->plan();

        $item = $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 999]);

        $this->assertNotNull($item->id);
    }

    // =========================================================================
    // submit()
    // =========================================================================

    /** @test */
    public function a_draft_plan_with_items_can_be_submitted(): void
    {
        $plan = $this->plan();
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);

        $plan->submit();

        $this->assertSame(SalesReturnPlan::STATUS_SUBMITTED, $plan->fresh()->status);
    }

    /** @test */
    public function a_plan_without_items_cannot_be_submitted(): void
    {
        $plan = $this->plan();

        $this->expectException(\RuntimeException::class);

        $plan->submit();
    }

    /** @test */
    public function a_non_draft_plan_cannot_be_submitted_again(): void
    {
        $plan = $this->plan();
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);
        $plan->submit();

        $this->expectException(\RuntimeException::class);

        $plan->submit();
    }

    // =========================================================================
    // Negosiasi klaim setelah Submitted
    // =========================================================================

    /** @test */
    public function a_claimed_weight_can_be_changed_while_submitted_and_the_old_value_is_logged(): void
    {
        $plan = $this->plan();
        $item = $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);
        $plan->submit();

        $item->update(['claimed_weight' => 8]);

        $this->assertSame(8.0, $item->fresh()->claimed_weight);
        $log = Activity::where('subject_type', SalesReturnPlanItem::class)
            ->where('subject_id', $item->id)
            ->where('description', 'updated')
            ->latest('id')
            ->first();
        $this->assertNotNull($log, 'Tidak ada activity log untuk perubahan klaim.');
        $this->assertEquals(10, $log->properties['old']['claimed_weight'] ?? null);
        $this->assertEquals(8, $log->properties['attributes']['claimed_weight'] ?? null);
    }

    /** @test */
    public function no_new_item_can_be_added_once_submitted(): void
    {
        $plan = $this->plan();
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);
        $plan->submit();

        $this->expectException(\Exception::class);

        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 5]);
    }

    /** @test */
    public function an_item_cannot_be_removed_once_submitted(): void
    {
        $plan = $this->plan();
        $item = $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);
        $plan->submit();

        $this->expectException(\Exception::class);

        $item->delete();
    }

    /** @test */
    public function a_claimed_weight_cannot_be_changed_once_received(): void
    {
        $plan = $this->plan();
        $item = $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);
        $plan->submit();
        $plan->markReceived();

        $this->expectException(\Exception::class);

        $item->update(['claimed_weight' => 8]);
    }

    // =========================================================================
    // Kunci header Draft-only
    // =========================================================================

    /** @test */
    public function the_plan_header_cannot_be_edited_once_submitted(): void
    {
        $plan = $this->plan();
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);
        $plan->submit();

        $this->expectException(\Exception::class);

        $plan->update(['note' => 'ubah setelah submit']);
    }

    /** @test */
    public function a_draft_plan_can_still_be_edited(): void
    {
        $plan = $this->plan();

        $plan->update(['note' => 'catatan baru']);

        $this->assertSame('catatan baru', $plan->fresh()->note);
    }

    /** @test */
    public function only_a_draft_plan_can_be_deleted(): void
    {
        $plan = $this->plan();
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);
        $plan->submit();

        $this->expectException(\Exception::class);

        $plan->delete();
    }

    /** @test */
    public function a_draft_plan_can_be_deleted(): void
    {
        $plan = $this->plan();

        $plan->delete();

        $this->assertSoftDeleted('sales_return_plans', ['id' => $plan->id]);
    }

    // =========================================================================
    // Transisi status lanjutan
    // =========================================================================

    /** @test */
    public function only_a_submitted_plan_can_be_marked_received(): void
    {
        $plan = $this->plan();

        $this->expectException(\RuntimeException::class);

        $plan->markReceived();
    }

    /** @test */
    public function a_received_plan_can_revert_to_submitted(): void
    {
        $plan = $this->plan();
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);
        $plan->submit();
        $plan->markReceived();

        $plan->markSubmitted();

        $this->assertSame(SalesReturnPlan::STATUS_SUBMITTED, $plan->fresh()->status);
    }

    /** @test */
    public function a_received_plan_cannot_be_cancelled(): void
    {
        $plan = $this->plan();
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);
        $plan->submit();
        $plan->markReceived();

        $this->expectException(\RuntimeException::class);

        $plan->cancel();
    }

    /** @test */
    public function a_draft_plan_can_be_cancelled(): void
    {
        $plan = $this->plan();

        $plan->cancel();

        $this->assertSame(SalesReturnPlan::STATUS_CANCELLED, $plan->fresh()->status);
    }
}
