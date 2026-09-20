<?php

namespace Tests\Feature;

use App\Models\Boning;
use App\Models\Costing;
use App\Models\CostingItem;
use App\Models\CustomerGroup;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Langkah 1 dari issue #480 (Mesin HPP): model `Costing` (siklus
 * Draft/Locked) dan penjagaan `CustomerGroup`/`Product` di lapis model.
 * Rumus HPP itu sendiri diuji tuntas di `CostingCalculatorTest`.
 */
class CostingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($this->user);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    private function boning(): Boning
    {
        // doc_no TIDAK diketik -- Boning::booted() menghasilkannya sendiri
        // (dan helper ini bisa dipanggil berkali-kali di satu test).
        return Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id, 'kunci' => true, 'status' => 'LOCKED']);
    }

    private function costing(array $overrides = []): Costing
    {
        // `boning_id` dibuat MALAS -- memakai `$this->boning()->id` sebagai
        // default array_merge() tetap MENGEVALUASI eager, membuat Boning
        // baru (dengan doc_no yang sama) walau ujung-ujungnya ditimpa
        // overrides. Baris `doc_no` unik lalu bentrok begitu helper ini
        // dipanggil dua kali dengan `boning_id` eksplisit.
        if (! isset($overrides['boning_id'])) {
            $overrides['boning_id'] = $this->boning()->id;
        }

        return Costing::create(array_merge([
            'costing_date' => now()->toDateString(),
            'purchase_cost' => 680500000,
            'total_sales_value' => 717762805.70,
            'ratio_k' => 0.948085,
            'overhead_per_kg' => 3000,
            'total_kg' => 6115.08,
            'profit' => 1000000,
        ], $overrides));
    }

    /** @test */
    public function the_four_permissions_exist_after_migrating(): void
    {
        foreach (['view_costings', 'create_costings', 'edit_costings', 'delete_costings'] as $name) {
            $this->assertTrue(Permission::where('name', $name)->exists(), "Izin $name tidak ada.");
        }
    }

    /** @test */
    public function a_costing_number_is_generated_automatically(): void
    {
        $costing = $this->costing();

        $this->assertStringStartsWith('HPP#'.date('y'), $costing->costing_number);
        $this->assertSame(Costing::STATUS_DRAFT, $costing->status);
    }

    /** @test */
    public function a_boning_can_only_have_one_costing(): void
    {
        $boning = $this->boning();
        $this->costing(['boning_id' => $boning->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->costing(['boning_id' => $boning->id]);
    }

    /** @test */
    public function locking_a_draft_costing_succeeds(): void
    {
        $costing = $this->costing();
        $costing->items()->create([
            'product_id' => $this->product->id, 'weight_kg' => 10, 'gross_price' => 100,
            'net_price' => 100, 'sales_value' => 1000, 'hpp_per_kg' => 90,
        ]);

        $costing->lock();

        $this->assertSame(Costing::STATUS_LOCKED, $costing->fresh()->status);
        $this->assertSame($this->user->id, $costing->fresh()->locked_by);
        $this->assertNotNull($costing->fresh()->locked_at);
    }

    /** @test */
    public function locking_an_already_locked_costing_is_rejected(): void
    {
        $costing = $this->costing(['status' => Costing::STATUS_LOCKED]);

        $this->expectException(\RuntimeException::class);

        $costing->lock();
    }

    /** @test */
    public function locking_a_costing_with_a_no_price_item_is_rejected(): void
    {
        $costing = $this->costing();
        $costing->items()->create([
            'product_id' => $this->product->id, 'weight_kg' => 10, 'gross_price' => 0,
            'net_price' => 0, 'sales_value' => 0, 'hpp_per_kg' => 0, 'flag' => CostingItem::FLAG_NO_PRICE,
        ]);

        $this->expectException(\RuntimeException::class);

        $costing->lock();
    }

    /** @test */
    public function unlocking_reverts_the_status(): void
    {
        $costing = $this->costing(['status' => Costing::STATUS_LOCKED, 'locked_by' => $this->user->id, 'locked_at' => now()]);

        $costing->unlock();

        $this->assertSame(Costing::STATUS_DRAFT, $costing->fresh()->status);
        $this->assertNull($costing->fresh()->locked_by);
    }

    /** @test */
    public function a_locked_costing_cannot_be_changed(): void
    {
        $costing = $this->costing(['status' => Costing::STATUS_LOCKED]);

        $this->expectException(\Exception::class);

        $costing->update(['overhead_per_kg' => 4000]);
    }

    /** @test */
    public function only_a_draft_costing_can_be_deleted(): void
    {
        $costing = $this->costing(['status' => Costing::STATUS_LOCKED]);

        $this->expectException(\Exception::class);

        $costing->delete();
    }

    /** @test */
    public function default_overhead_is_zero_when_no_costing_exists_yet(): void
    {
        $this->assertSame(0.0, Costing::defaultOverheadPerKg());
    }

    /** @test */
    public function default_overhead_follows_the_last_costing(): void
    {
        $this->costing(['overhead_per_kg' => 3500]);

        $this->assertSame(3500.0, Costing::defaultOverheadPerKg());
    }

    /** @test */
    public function margin_percent_is_derived_from_the_ratio_and_is_the_same_for_every_product(): void
    {
        $costing = $this->costing(['ratio_k' => 0.948085]);

        $this->assertSame(5.19, $costing->marginPercent());
    }

    // =========================================================================
    // Grup acuan costing -- hanya yang ditandai boleh dipakai (hpp.md §9/§11.3)
    // =========================================================================

    /** @test */
    public function a_product_can_reference_a_group_marked_as_a_costing_reference(): void
    {
        $group = CustomerGroup::create(['name' => 'LION', 'is_costing_reference' => true]);

        $this->product->update(['costing_customer_group_id' => $group->id]);

        $this->assertSame($group->id, $this->product->fresh()->costing_customer_group_id);
    }

    /** @test */
    public function a_product_cannot_reference_a_group_not_marked_as_a_costing_reference(): void
    {
        // Simulasi KARYAWAN/WARGA -- grup transaksional biasa, bukan acuan costing.
        $group = CustomerGroup::create(['name' => 'KARYAWAN', 'is_costing_reference' => false]);

        $this->expectException(\Exception::class);

        $this->product->update(['costing_customer_group_id' => $group->id]);
    }
}
