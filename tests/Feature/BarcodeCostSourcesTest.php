<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BarcodeCostResource\Pages\ListBarcodeCosts;
use App\Models\BarcodeCost;
use App\Models\BeefStock;
use App\Models\Boning;
use App\Models\Costing;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BarcodeCosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HPP tahap 2, PR 2 (issue #522): dari mana nilai sebuah barcode datang selain
 * boning -- GR Beef, retur jual, temuan opname, stok awal -- dan layar kecil
 * tempat accounting mengisi yang belum bernilai.
 */
class BarcodeCostSourcesTest extends TestCase
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
        $this->product = Product::create(['name' => 'TENDERLOIN', 'code' => 'MT001', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach ($permissions as $permission) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $permission], ['module_name' => 'Test', 'description' => $permission])->id);
        }

        return $user->fresh();
    }

    /** Stok di tangan; $cost null = barcode belum bernilai, false = barcode belum punya baris sama sekali. */
    private function stock(string $barcode, float $kg, float|false|null $cost, ?Product $product = null, string $status = 'IN_STOCK'): void
    {
        $product ??= $this->product;
        $warehouse = Warehouse::firstOrCreate(['code' => 'JGL'], ['name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::firstOrCreate(['name' => 'CHILL'], ['is_active' => true]);

        BeefStock::create([
            'barcode' => $barcode, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'grade_id' => $grade->id,
            'weight' => $kg, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'exp_date' => now()->addYear()->toDateString(),
            'origin' => '1', 'status' => $status,
        ]);

        if ($cost !== false) {
            BarcodeCost::create(['barcode' => $barcode, 'product_id' => $product->id, 'cost_per_kg' => $cost, 'source_type' => 'boning']);
        }
    }

    // ---------------------------------------------------------------------
    // GR Beef
    // ---------------------------------------------------------------------

    /** @test */
    public function a_goods_receipt_barcode_is_valued_at_the_purchase_price_per_kg(): void
    {
        BarcodeCosts::fromGoodsReceipt('GR-1', $this->product->id, 72500.0, 9);

        $row = BarcodeCost::where('barcode', 'GR-1')->first();

        $this->assertSame(72500.0, $row->cost_per_kg);
        $this->assertSame(BarcodeCost::SOURCE_GOODS_RECEIPT, $row->source_type);
        $this->assertSame(9, (int) $row->source_id);
    }

    /** @test */
    public function a_goods_receipt_without_a_price_stays_unvalued_instead_of_zero(): void
    {
        BarcodeCosts::fromGoodsReceipt('GR-2', $this->product->id, 0.0);

        $this->assertNull(BarcodeCost::where('barcode', 'GR-2')->first()->cost_per_kg);
    }

    // ---------------------------------------------------------------------
    // Retur dan temuan: barcode lama membawa nilainya, yang baru dirata-rata
    // ---------------------------------------------------------------------

    /** @test */
    public function a_returned_barcode_that_is_already_known_keeps_its_own_value(): void
    {
        $this->stock('STOCK-A', 10, 100000.0);
        BarcodeCost::create(['barcode' => 'SOLD-1', 'product_id' => $this->product->id, 'cost_per_kg' => 80000.0, 'source_type' => 'boning']);

        $row = BarcodeCosts::fromAverage('SOLD-1', $this->product->id, BarcodeCost::SOURCE_SALES_RETURN, 5);

        $this->assertSame(80000.0, $row->cost_per_kg, 'Barcode lama kembali dengan nilainya sendiri, bukan rata-rata.');
        $this->assertSame(BarcodeCost::SOURCE_BONING, $row->source_type);
    }

    /** @test */
    public function a_new_barcode_gets_the_weighted_average_of_the_product_in_stock(): void
    {
        $this->stock('S-1', 10, 100000.0);
        $this->stock('S-2', 30, 80000.0);

        // (10 x 100.000 + 30 x 80.000) / 40 = 85.000
        $this->assertSame(85000.0, BarcodeCosts::averageInStock($this->product->id));

        $row = BarcodeCosts::fromAverage('MANUAL-1', $this->product->id, BarcodeCost::SOURCE_SALES_RETURN, 5);

        $this->assertSame(85000.0, $row->cost_per_kg);
        $this->assertSame(BarcodeCost::SOURCE_SALES_RETURN, $row->source_type);
    }

    /** @test */
    public function the_average_ignores_unvalued_barcodes_and_stock_that_is_gone(): void
    {
        $this->stock('S-1', 10, 100000.0);
        $this->stock('S-2', 50, null);                             // belum bernilai: tidak ikut
        $this->stock('S-3', 20, 40000.0, null, 'SHIPPED');         // sudah keluar: tidak ikut

        $this->assertSame(100000.0, BarcodeCosts::averageInStock($this->product->id));
    }

    /** @test */
    public function with_no_valued_stock_a_new_barcode_is_unvalued_and_flagged(): void
    {
        $this->stock('S-1', 10, null);

        $row = BarcodeCosts::fromAverage('MANUAL-1', $this->product->id, BarcodeCost::SOURCE_STOCK_TAKE);

        $this->assertNull($row->cost_per_kg, 'Tidak ada stok bernilai: NULL, bukan 0.');
        $this->assertNotNull($row->note, 'Ditandai supaya kelihatan kenapa kosong.');
        $this->assertNull(BarcodeCosts::averageInStock(999999));
    }

    // ---------------------------------------------------------------------
    // Stok awal
    // ---------------------------------------------------------------------

    private function lockedCosting(float $hpp, string $lockedAt, ?Product $product = null): Costing
    {
        $product ??= $this->product;
        $boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id, 'kunci' => true, 'status' => 'LOCKED']);
        $costing = Costing::create([
            'costing_date' => now()->toDateString(), 'boning_id' => $boning->id, 'purchase_cost' => 1000, 'total_sales_value' => 1000,
            'ratio_k' => 1, 'overhead_per_kg' => 0, 'total_kg' => 10, 'profit' => 0,
        ]);
        $costing->items()->create(['product_id' => $product->id, 'weight_kg' => 10, 'gross_price' => $hpp, 'net_price' => $hpp, 'sales_value' => $hpp * 10, 'hpp_per_kg' => $hpp]);
        $costing->lock();
        $costing->forceFill(['locked_at' => $lockedAt])->saveQuietly();

        return $costing;
    }

    /** @test */
    public function opening_stock_is_valued_with_the_last_locked_costing_of_its_product(): void
    {
        $this->lockedCosting(90000.0, '2026-09-01 10:00:00');
        $this->lockedCosting(120000.0, '2026-10-01 10:00:00');          // yang terakhir
        $this->stock('OPEN-1', 10, false);
        $this->stock('OPEN-2', 5, false);

        $result = BarcodeCosts::valueOpeningStock();

        $this->assertSame(['valued' => 2, 'unvalued' => 0], $result);
        $this->assertSame(120000.0, BarcodeCosts::for('OPEN-1'));
        $this->assertSame(BarcodeCost::SOURCE_OPENING, BarcodeCost::where('barcode', 'OPEN-2')->value('source_type'));
    }

    /** @test */
    public function a_product_never_costed_stays_unvalued_for_manual_entry(): void
    {
        $other = Product::create(['name' => 'CUBE ROLL', 'code' => 'MT002', 'category_id' => $this->product->category_id, 'structure_type' => 'main', 'is_active' => true]);
        $this->lockedCosting(90000.0, '2026-10-01 10:00:00');
        $this->stock('OPEN-1', 10, false);
        $this->stock('OPEN-2', 10, false, $other);

        $result = BarcodeCosts::valueOpeningStock();

        $this->assertSame(['valued' => 1, 'unvalued' => 1], $result);
        $this->assertNull(BarcodeCosts::for('OPEN-2'));
        $this->assertNotNull(BarcodeCost::where('barcode', 'OPEN-2')->first(), 'Barisnya ada, menunggu diisi manual.');
    }

    /** @test */
    public function an_unlocked_costing_is_not_a_basis_for_opening_stock(): void
    {
        $boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id, 'kunci' => true, 'status' => 'LOCKED']);
        $costing = Costing::create([
            'costing_date' => now()->toDateString(), 'boning_id' => $boning->id, 'purchase_cost' => 1, 'total_sales_value' => 1,
            'ratio_k' => 1, 'overhead_per_kg' => 0, 'total_kg' => 1, 'profit' => 0,
        ]);
        $costing->items()->create(['product_id' => $this->product->id, 'weight_kg' => 1, 'gross_price' => 1, 'net_price' => 1, 'sales_value' => 1, 'hpp_per_kg' => 55000]);
        $this->stock('OPEN-1', 10, false);

        BarcodeCosts::valueOpeningStock();

        $this->assertNull(BarcodeCosts::for('OPEN-1'), 'Costing Draft bukan dasar nilai.');
    }

    /** @test */
    public function opening_valuation_never_touches_a_barcode_that_already_has_a_value(): void
    {
        $this->lockedCosting(90000.0, '2026-10-01 10:00:00');
        $this->stock('KNOWN', 10, 70000.0);
        $this->stock('OPEN-1', 10, false);

        BarcodeCosts::valueOpeningStock();

        $this->assertSame(70000.0, BarcodeCosts::for('KNOWN'));
        $this->assertSame(['valued' => 0, 'unvalued' => 0], BarcodeCosts::valueOpeningStock(), 'Idempoten.');
    }

    // ---------------------------------------------------------------------
    // Layar Nilai Barcode
    // ---------------------------------------------------------------------

    /** @test */
    public function the_permission_exists_after_migrating(): void
    {
        $this->assertTrue(Permission::where('name', 'manage_barcode_costs')->exists());
    }

    /** @test */
    public function the_screen_needs_the_permission(): void
    {
        $this->actingAs($this->userWith());
        $this->get('/admin/barcode-costs')->assertForbidden();

        $this->actingAs($this->userWith('manage_barcode_costs'));
        $this->get('/admin/barcode-costs')->assertOk();
    }

    /** @test */
    public function accounting_can_fill_an_unvalued_barcode_and_it_spreads_to_its_relabels(): void
    {
        $this->actingAs($this->userWith('manage_barcode_costs'));
        BarcodeCost::create(['barcode' => 'OPEN-1', 'product_id' => $this->product->id, 'source_type' => 'opening']);
        BarcodeCosts::relabel('OPEN-1', 'OPEN-1-R', $this->product->id);

        $row = BarcodeCost::where('barcode', 'OPEN-1')->first();

        Livewire::test(ListBarcodeCosts::class)
            ->call('updateTableColumnState', 'cost_per_kg', (string) $row->getKey(), '95000');

        $this->assertSame(95000.0, BarcodeCosts::for('OPEN-1'));
        $this->assertSame(95000.0, BarcodeCosts::for('OPEN-1-R'));
    }

    /** @test */
    public function the_opening_action_runs_from_the_screen(): void
    {
        $this->actingAs($this->userWith('manage_barcode_costs'));
        $this->lockedCosting(90000.0, '2026-10-01 10:00:00');
        $this->stock('OPEN-1', 10, false);

        Livewire::test(ListBarcodeCosts::class)->callAction('value_opening_stock');

        $this->assertSame(90000.0, BarcodeCosts::for('OPEN-1'));
    }

    /** @test */
    public function the_opening_action_is_refused_without_the_permission(): void
    {
        $this->actingAs($this->userWith());
        $this->stock('OPEN-1', 10, false);

        try {
            Livewire::test(ListBarcodeCosts::class)->callAction('value_opening_stock');
        } catch (\Throwable) {
        }

        $this->assertNull(BarcodeCosts::for('OPEN-1'));
        $this->assertNull(BarcodeCost::where('barcode', 'OPEN-1')->first());
    }
}
