<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\BeefStockMovement;
use App\Models\Warehouse;
use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;
use App\Filament\Admin\Resources\BeefStockMovementResource\Pages\ListBeefStockMovements;
use App\Filament\Admin\Resources\BeefStockMovementResource\Pages\ViewBeefStockMovement;

class BeefStockMovementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Warehouse $warehouse;
    protected Grade $grade;
    protected Product $product;
    protected ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'programmer',
            'is_active' => true
        ]);

        $this->warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $this->category = ProductCategory::create([
            'name' => 'MEAT',
            'prefix' => 'MT',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'SIRLOIN',
            'code' => 'B001',
            'category_id' => $this->category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_lists_movements_and_applies_silent_date_filter_by_default()
    {
        // 1. Create a movement in current month
        $currentMovement = BeefStockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'condition' => $this->grade->id,
            'barcode' => 'BC_CURRENT',
            'transaction_type' => 'IN_BONING',
            'weight_in' => 12.50,
            'pcs_in' => 3,
            'created_by' => $this->user->id,
            'created_at' => now(), // today
        ]);

        // 2. Create a movement in previous month
        $oldMovement = new BeefStockMovement([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'condition' => $this->grade->id,
            'barcode' => 'BC_OLD',
            'transaction_type' => 'IN_BONING',
            'weight_in' => 15.00,
            'pcs_in' => 4,
            'created_by' => $this->user->id,
        ]);
        $oldMovement->created_at = now()->subMonth()->startOfMonth();
        $oldMovement->save();

        // Access List page - by default old movement should not be visible due to startOfMonth filter
        Livewire::actingAs($this->user)
            ->test(ListBeefStockMovements::class)
            ->assertSee('BC_CURRENT')
            ->assertDontSee('BC_OLD');

        // Turn off or change date filter to view old movement
        Livewire::actingAs($this->user)
            ->test(ListBeefStockMovements::class)
            ->set('tableFilters.created_at.from', now()->subMonths(2)->format('Y-m-d'))
            ->assertSee('BC_CURRENT')
            ->assertSee('BC_OLD');
    }

    /** @test */
    public function it_can_view_individual_movement_details()
    {
        $movement = BeefStockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'condition' => $this->grade->id,
            'barcode' => 'BC_VIEW_TEST',
            'transaction_type' => 'OUT_TO_REPACK',
            'weight_out' => 20.00,
            'pcs_out' => 5,
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ViewBeefStockMovement::class, ['record' => $movement->getKey()])
            ->assertSee('BC_VIEW_TEST')
            ->assertSee('OUT_TO_REPACK');
    }
}
