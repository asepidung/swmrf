<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CostingResource\Pages\EditCosting;
use App\Filament\Admin\Resources\CostingResource\Pages\ListCostings;
use App\Filament\Admin\Resources\CostingResource\Pages\ViewCosting;
use App\Models\Boning;
use App\Models\BoningCarcass;
use App\Models\BoningItem;
use App\Models\Carcass;
use App\Models\CarcassItem;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\CattleReceivingItem;
use App\Models\CattleWeighing;
use App\Models\CattleWeighingItem;
use App\Models\Costing;
use App\Models\CustomerGroup;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseCattle;
use App\Models\PurchaseCattleItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Langkah 2 dari issue #480 (Mesin HPP): Resource + halaman (Create via
 * "Create Costing", Recalculate, Lock/Unlock, View). Rumusnya sendiri
 * sudah diuji tuntas di `CostingCalculatorTest` -- di sini fokus ke
 * OTORISASI dan pengkabelan halaman lewat Livewire.
 */
class CostingResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($this->user);

        $this->warehouse = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'TOPSIDE', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Costings', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    /** Satu carcass/satu kelas sapi/satu produk dengan harga umum -- cukup untuk mengetes pengkabelan halaman. */
    private function lockedBoningWithCostableProduct(float $pricePerKg = 62500, float $receivedWeight = 10888): Boning
    {
        $cattleClass = CattleClass::create(['name' => 'STEER']);
        $supplier = Supplier::create([
            'name' => 'PT LEMBU JANTAN PERKASA', 'address' => 'X', 'pic' => 'X',
            'phone' => '08', 'top_days' => 30,
        ]);

        $po = PurchaseCattle::create([
            'supplier_id' => $supplier->id, 'shipping_date' => '2026-06-13', 'created_by' => $this->user->id,
        ]);
        PurchaseCattleItem::create([
            'purchase_cattle_id' => $po->id, 'cattle_class_id' => $cattleClass->id,
            'qty' => 20, 'price' => $pricePerKg, 'created_by' => $this->user->id,
        ]);

        $receiving = CattleReceiving::create([
            'purchase_cattle_id' => $po->id, 'supplier_id' => $supplier->id,
            'receive_date' => '2026-06-14', 'created_by' => $this->user->id,
        ]);
        $receivingItem = CattleReceivingItem::create([
            'cattle_receiving_id' => $receiving->id, 'cattle_class_id' => $cattleClass->id,
            'eartag' => 'EARTAG-001', 'initial_weight' => $receivedWeight,
        ]);

        $weighing = CattleWeighing::create([
            'cattle_receiving_id' => $receiving->id, 'weighing_date' => '2026-06-14', 'created_by' => $this->user->id,
        ]);
        $weighingItem = CattleWeighingItem::create([
            'cattle_weighing_id' => $weighing->id, 'cattle_receiving_item_id' => $receivingItem->id,
            'actual_weight' => $receivedWeight,
        ]);

        $carcass = Carcass::create([
            'cattle_weighing_id' => $weighing->id, 'kill_date' => '2026-06-15', 'created_by' => $this->user->id,
        ]);
        CarcassItem::create([
            'carcass_id' => $carcass->id, 'cattle_weighing_item_id' => $weighingItem->id,
            'carcass_1' => $receivedWeight * 0.3, 'carcass_2' => $receivedWeight * 0.26,
            'hides' => $receivedWeight * 0.07, 'tail' => 0,
        ]);

        $boning = Boning::create([
            'boning_date' => '2026-06-16', 'created_by' => $this->user->id,
        ]);
        BoningCarcass::create(['boning_id' => $boning->id, 'carcass_id' => $carcass->id]);

        $general = CustomerGroup::firstOrCreate(['name' => 'UMUM'], ['is_general_price_reference' => true]);
        $priceList = PriceList::firstOrCreate(['customer_group_id' => $general->id], ['created_by' => $this->user->id]);
        PriceListItem::create(['price_list_id' => $priceList->id, 'product_id' => $this->product->id, 'price' => 149000]);

        BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => Grade::create(['name' => 'CHILL'])->id,
            'weight' => 100, 'qty_pcs' => 39, 'pack_date' => '2026-06-16', 'barcode' => 'BC-TOPSIDE-001',
            'created_by' => $this->user->id,
        ]);

        $boning->lock();

        return $boning->fresh();
    }

    // =========================================================================
    // Otorisasi
    // =========================================================================

    /** @test */
    public function the_list_page_requires_view_costings(): void
    {
        Livewire::actingAs($this->employee())
            ->test(ListCostings::class)
            ->assertForbidden();

        Livewire::actingAs($this->employee(['view_costings']))
            ->test(ListCostings::class)
            ->assertSuccessful();
    }

    /** @test */
    public function the_create_costing_action_requires_create_costings(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();

        Livewire::actingAs($this->employee(['view_costings']))
            ->test(ListCostings::class)
            ->assertActionHidden('create_costing');

        Livewire::actingAs($this->employee(['view_costings', 'create_costings']))
            ->test(ListCostings::class)
            ->assertActionVisible('create_costing')
            ->mountAction('create_costing')
            ->setActionData(['boning_id' => $boning->id])
            ->callMountedAction();

        $this->assertTrue(Costing::where('boning_id', $boning->id)->exists());
    }

    /** @test */
    public function the_edit_page_requires_edit_costings(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $costing = Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning)->calculate());

        Livewire::actingAs($this->employee(['view_costings']))
            ->test(EditCosting::class, ['record' => $costing->getRouteKey()])
            ->assertForbidden();

        Livewire::actingAs($this->employee(['view_costings', 'edit_costings']))
            ->test(EditCosting::class, ['record' => $costing->getRouteKey()])
            ->assertSuccessful();
    }

    // =========================================================================
    // Create Costing
    // =========================================================================

    /** @test */
    public function creating_a_costing_persists_the_calculator_result_and_redirects_to_edit(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();

        Livewire::actingAs($this->employee(['view_costings', 'create_costings']))
            ->test(ListCostings::class)
            ->mountAction('create_costing')
            ->setActionData(['boning_id' => $boning->id])
            ->callMountedAction();

        $costing = Costing::where('boning_id', $boning->id)->firstOrFail();

        $this->assertSame(Costing::STATUS_DRAFT, $costing->status);
        $this->assertSame(680500000.0, $costing->purchase_cost);
        $this->assertCount(1, $costing->items);
    }

    /** @test */
    public function creating_a_second_costing_for_an_already_costed_boning_fails_without_a_duplicate(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning)->calculate());

        Livewire::actingAs($this->employee(['view_costings', 'create_costings']))
            ->test(ListCostings::class)
            ->mountAction('create_costing')
            ->setActionData(['boning_id' => $boning->id])
            ->callMountedAction();

        $this->assertSame(1, Costing::where('boning_id', $boning->id)->count());
    }

    // =========================================================================
    // Recalculate
    // =========================================================================

    /** @test */
    public function recalculating_a_draft_costing_updates_its_numbers(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $costing = Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning, 0)->calculate());

        Livewire::actingAs($this->employee(['view_costings', 'edit_costings']))
            ->test(EditCosting::class, ['record' => $costing->getRouteKey()])
            ->fillForm(['overhead_per_kg' => 5000])
            ->callAction('recalculate');

        $costing->refresh();
        $this->assertSame(5000.0, $costing->overhead_per_kg);
    }

    /** @test */
    public function recalculating_a_locked_costing_is_impossible_because_edit_is_no_longer_reachable(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $costing = Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning)->calculate());
        $costing->lock();

        Livewire::actingAs($this->employee(['view_costings', 'edit_costings']))
            ->test(EditCosting::class, ['record' => $costing->getRouteKey()])
            ->assertRedirect(\App\Filament\Admin\Resources\CostingResource::getUrl('view', ['record' => $costing]));
    }

    // =========================================================================
    // Lock / Unlock
    // =========================================================================

    /** @test */
    public function locking_requires_the_lock_costings_permission(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $costing = Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning)->calculate());

        Livewire::actingAs($this->employee(['view_costings', 'edit_costings']))
            ->test(EditCosting::class, ['record' => $costing->getRouteKey()])
            ->assertActionHidden('lock');

        Livewire::actingAs($this->employee(['view_costings', 'edit_costings', 'lock_costings']))
            ->test(EditCosting::class, ['record' => $costing->getRouteKey()])
            ->assertActionVisible('lock')
            ->callAction('lock');

        $this->assertSame(Costing::STATUS_LOCKED, $costing->fresh()->status);
    }

    /** @test */
    public function a_costing_with_a_no_price_item_cannot_be_locked(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $noPriceProduct = Product::create([
            'name' => 'NO PRICE PRODUCT', 'code' => 'MT999', 'category_id' => $this->product->category_id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $noPriceProduct->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => Grade::first()->id,
            'weight' => 1, 'qty_pcs' => 1, 'pack_date' => '2026-06-16', 'barcode' => 'BC-NOPRICE-001',
            'created_by' => $this->user->id,
        ]);
        $costing = Costing::createFromCalculation($boning->fresh(), \App\Services\CostingCalculator::forBoning($boning->fresh())->calculate());

        Livewire::actingAs($this->employee(['view_costings', 'edit_costings', 'lock_costings']))
            ->test(EditCosting::class, ['record' => $costing->getRouteKey()])
            ->callAction('lock');

        $this->assertSame(Costing::STATUS_DRAFT, $costing->fresh()->status);
    }

    /** @test */
    public function unlocking_requires_the_lock_costings_permission_and_reverts_to_draft(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $costing = Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning)->calculate());
        $costing->lock();

        Livewire::actingAs($this->employee(['view_costings']))
            ->test(ViewCosting::class, ['record' => $costing->getRouteKey()])
            ->assertActionHidden('unlock');

        Livewire::actingAs($this->employee(['view_costings', 'lock_costings']))
            ->test(ViewCosting::class, ['record' => $costing->getRouteKey()])
            ->assertActionVisible('unlock')
            ->callAction('unlock');

        $this->assertSame(Costing::STATUS_DRAFT, $costing->fresh()->status);
    }
}
