<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CostingResource\Pages\DraftCosting;
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

        \App\Models\CostingPriceDefault::updateOrCreate(
            ['product_id' => $this->product->id],
            ['gross_price' => 149000, 'net_price' => 149000, 'source' => 'import'],
        );

        BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => Grade::create(['name' => 'CHILL'])->id,
            'weight' => 100, 'qty_pcs' => 39, 'pack_date' => '2026-06-16', 'barcode' => 'BC-TOPSIDE-001',
            'created_by' => $this->user->id,
        ]);

        $this->lockWithDrylog($boning);

        // Mengunci boning kini langsung membuat Draft costing-nya; tes-tes di bawah menyiapkan costing
        // sendiri, jadi Draft otomatis itu dibuang dulu (perilakunya diuji di bagian "Draft otomatis").
        Costing::where('boning_id', $boning->id)->forceDelete();

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
    public function the_draft_costing_page_requires_create_costings(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();

        Livewire::actingAs($this->employee(['view_costings']))
            ->test(ListCostings::class)
            ->assertActionHidden('draft');

        Livewire::actingAs($this->employee(['view_costings']))
            ->test(DraftCosting::class)
            ->assertForbidden();

        Livewire::actingAs($this->employee(['view_costings', 'create_costings']))
            ->test(ListCostings::class)
            ->assertActionVisible('draft');

        Livewire::actingAs($this->employee(['view_costings', 'create_costings']))
            ->test(DraftCosting::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$boning])
            ->callTableAction('process', $boning);

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
            ->test(DraftCosting::class)
            ->callTableAction('process', $boning);

        $costing = Costing::where('boning_id', $boning->id)->firstOrFail();

        $this->assertSame(Costing::STATUS_DRAFT, $costing->status);
        $this->assertSame(680500000.0, $costing->purchase_cost);
        $this->assertCount(1, $costing->items);
    }

    /** @test */
    public function an_already_costed_boning_is_not_offered_again_in_the_draft_list(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning)->calculate());

        // Boning yang sudah punya costing TERSIMPAN tidak muncul lagi di Draft Costing; costing yang
        // belum disimpan tidak menutupnya.
        Livewire::actingAs($this->employee(['view_costings', 'create_costings']))
            ->test(DraftCosting::class)
            ->assertCanSeeTableRecords([$boning]);

        Costing::where('boning_id', $boning->id)->update(['saved_at' => now()]);

        Livewire::actingAs($this->employee(['view_costings', 'create_costings']))
            ->test(DraftCosting::class)
            ->assertCanNotSeeTableRecords([$boning]);

        $this->assertSame(1, Costing::where('boning_id', $boning->id)->count());
    }

    /** @test */
    public function a_boning_can_be_costed_again_after_its_draft_costing_was_deleted(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $first = Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning)->calculate());
        $first->delete();   // hapus lunak; barisnya masih memegang boning_id yang unik

        Livewire::actingAs($this->employee(['view_costings', 'create_costings']))
            ->test(DraftCosting::class)
            ->callTableAction('process', $boning);

        $this->assertSame(1, Costing::where('boning_id', $boning->id)->count(), 'Costing baru dibuat.');
        $this->assertSame(0, Costing::onlyTrashed()->where('boning_id', $boning->id)->count(), 'Yang terhapus dibuang sungguhan, tidak menghalangi.');
    }

    // =========================================================================
    // Draft Costing: boning terkunci yang belum di-costing
    // =========================================================================

    /** @test */
    public function a_locked_boning_waits_in_draft_costing_and_unlocking_discards_its_draft(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();

        $this->assertNull(Costing::where('boning_id', $boning->id)->first(), 'Mengunci boning tidak membuat costing sendiri.');

        Livewire::actingAs($this->employee(['view_costings', 'create_costings']))
            ->test(DraftCosting::class)
            ->assertCanSeeTableRecords([$boning]);

        $costing = Costing::createFromBoning($boning);
        $this->assertSame(Costing::STATUS_DRAFT, $costing->status);

        // Boning di-unlock: Draft costing dibuang; dikunci lagi, boning muncul lagi di daftar.
        $boning->fresh()->unlock();
        $this->assertSame(0, Costing::withTrashed()->where('boning_id', $boning->id)->count());

        $boning->fresh()->lock();
        Livewire::actingAs($this->employee(['view_costings', 'create_costings']))
            ->test(DraftCosting::class)
            ->assertCanSeeTableRecords([$boning]);
    }

    /** @test */
    public function a_costing_shows_in_the_index_only_after_it_is_saved_and_can_be_deleted_until_locked(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $costing = Costing::createFromBoning($boning);
        $user = $this->employee(['view_costings', 'edit_costings', 'delete_costings']);

        Livewire::actingAs($user)->test(ListCostings::class)->assertCanNotSeeTableRecords([$costing]);

        Livewire::actingAs($user)
            ->test(EditCosting::class, ['record' => $costing->getRouteKey()])
            ->call('save');

        $this->assertNotNull($costing->fresh()->saved_at);
        Livewire::actingAs($user)->test(ListCostings::class)->assertCanSeeTableRecords([$costing]);

        Livewire::actingAs($user)->test(ListCostings::class)->callTableAction('delete', $costing);
        $this->assertNull(Costing::find($costing->id), 'Costing yang belum terkunci bisa dihapus.');

        $locked = Costing::createFromBoning($boning->fresh());
        $locked->items()->update(['gross_price' => 100000, 'net_price' => 100000]);
        $locked->lock();
        $this->assertNotNull($locked->fresh()->saved_at, 'Mengunci sekaligus menyimpan.');

        Livewire::actingAs($user)
            ->test(ListCostings::class)
            ->assertCanSeeTableRecords([$locked])
            ->assertTableActionHidden('delete', $locked);
    }

    /** @test */
    public function a_boning_with_a_locked_costing_cannot_be_unlocked_until_the_costing_is(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $costing = Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning, 0)->calculate());
        $costing->items()->update(['gross_price' => 100000, 'net_price' => 100000]);
        $costing->lock();

        try {
            $boning->fresh()->unlock();
            $this->fail('Boning dengan costing terkunci tidak boleh di-unlock.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked costing', $e->getMessage());
        }

        $costing->fresh()->unlock();
        $boning->fresh()->unlock();

        $this->assertFalse((bool) $boning->fresh()->kunci);
    }

    /** @test */
    public function changing_the_overhead_recalculates_the_draft_and_the_recalculate_button_is_gone(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $costing = Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning, 0)->calculate());

        Livewire::actingAs($this->employee(['view_costings', 'edit_costings']))
            ->test(EditCosting::class, ['record' => $costing->getRouteKey()])
            ->assertActionDoesNotExist('recalculate')
            ->assertActionExists('delete')
            ->fillForm(['overhead_per_kg' => '5.000'])
            ->assertHasNoFormErrors();

        $this->assertSame(5000.0, (float) $costing->fresh()->overhead_per_kg);
        $this->assertSame(500000.0, (float) $costing->fresh()->overhead_total, '100 kg x 5.000');
    }

    // =========================================================================
    // Harga costing (#541)
    // =========================================================================

    /** @test */
    public function typing_the_gross_in_a_draft_fills_the_net_with_the_last_discount_and_recalculates(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        // Potongan terakhir produk ini 6,25%: 160.000 -> 150.000.
        \App\Models\CostingPriceDefault::where('product_id', $this->product->id)->update(['gross_price' => 160000, 'net_price' => 150000]);
        $costing = Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning, 0)->calculate());
        $item = $costing->items()->firstOrFail();

        Livewire::actingAs($this->employee(['view_costings', 'edit_costings']))
            ->test(\App\Filament\Admin\Resources\CostingResource\RelationManagers\ItemsRelationManager::class, [
                'ownerRecord' => $costing, 'pageClass' => EditCosting::class,
            ])
            ->call('updateTableColumnState', 'gross_price', $item->getKey(), 200000);

        $item = $costing->items()->firstOrFail();
        $this->assertSame(200000.0, (float) $item->gross_price);
        $this->assertSame(187500.0, (float) $item->net_price);
        $this->assertSame(6.25, (float) $item->trading_terms_percent);
        $this->assertSame(187500.0 * 100, (float) $costing->fresh()->total_sales_value);
    }

    /** @test */
    public function typing_the_terms_percent_sets_the_net_and_the_net_is_not_editable_when_there_is_a_gross(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $costing = Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning, 0)->calculate());
        $item = $costing->items()->firstOrFail();
        $user = $this->employee(['view_costings', 'edit_costings']);

        $table = Livewire::actingAs($user)
            ->test(\App\Filament\Admin\Resources\CostingResource\RelationManagers\ItemsRelationManager::class, [
                'ownerRecord' => $costing, 'pageClass' => EditCosting::class,
            ]);

        $table->call('updateTableColumnState', 'trading_terms_percent', $item->getKey(), '10');

        $item = $costing->items()->firstOrFail();
        $this->assertSame(149000.0, (float) $item->gross_price);
        $this->assertSame(134100.0, (float) $item->net_price, '149.000 x (1 - 10%)');
        $this->assertSame(10.0, (float) $item->trading_terms_percent);
    }

    /** @test */
    public function the_costing_shows_a_margin_on_net_and_on_gross_sales_value(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $costing = Costing::createFromBoning($boning);
        $costing->items()->update(['gross_price' => 120, 'net_price' => 100, 'weight_kg' => 10]);
        $costing->forceFill(['purchase_cost' => 600, 'overhead_total' => 0, 'ratio_k' => 0.6])->save();

        $this->assertSame(1200.0, $costing->grossSalesValue());
        $this->assertSame(50.0, $costing->marginGrossPercent(), '(1 - 600/1200)');
        $this->assertSame(40.0, $costing->marginPercent(), '(1 - k) dengan k = 600/1000');
    }

    /** @test */
    public function typed_prices_survive_recalculate_and_locking_makes_them_the_next_costings_price(): void
    {
        $boning = $this->lockedBoningWithCostableProduct();
        $costing = Costing::createFromCalculation($boning, \App\Services\CostingCalculator::forBoning($boning, 0)->calculate());
        $costing->items()->firstOrFail()->update(['gross_price' => 170000, 'net_price' => 165000]);

        Livewire::actingAs($this->employee(['view_costings', 'edit_costings']))
            ->test(EditCosting::class, ['record' => $costing->getRouteKey()])
            ->fillForm(['overhead_per_kg' => '1.000']);

        $this->assertSame(165000.0, (float) $costing->items()->firstOrFail()->net_price, 'Yang diketik tidak tertimpa.');

        $costing->fresh()->lock();

        $default = \App\Models\CostingPriceDefault::where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame(165000.0, (float) $default->net_price);
        $this->assertSame('costing', $default->source);
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
            ->fillForm(['overhead_per_kg' => '5.000']);

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
