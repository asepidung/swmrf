<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BoningResource\Pages\LabelingBoning;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\Boning;
use App\Models\BoningItem;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BarcodeSegments;
use App\Support\LabelWeightLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * #486 langkah 2 dan 3: peringatan salah ketik per produk, dan label yang
 * otomatis dipecah bila beratnya tidak muat di satu barcode.
 */
class LabelWeightWarningAndSplitTest extends TestCase
{
    use RefreshDatabase;

    private Product $meat;

    private Product $offal;

    private Warehouse $warehouse;

    private Grade $grade;

    private Boning $boning;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->meat = Product::create([
            'name' => 'SIRLOIN BEEF', 'code' => 'B001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->offal = Product::create([
            'name' => 'OFFAL', 'code' => 'OF0001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true, 'max_label_weight' => 9999.99,
        ]);
        $this->warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $this->boning = Boning::create([
            'boning_date' => now()->format('Y-m-d'),
            'created_by' => User::factory()->create(['role' => 'programmer', 'is_active' => true])->id,
        ]);

        $this->user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        foreach (['view_bonings', 'edit_bonings'] as $name) {
            $this->user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Boning', 'description' => $name])->id
            );
        }
        $this->user = $this->user->fresh();
    }

    private function label(Product $product, string $input)
    {
        return Livewire::actingAs($this->user)
            ->test(LabelingBoning::class, ['record' => $this->boning])
            ->set('data.warehouse_id', $this->warehouse->id)
            ->set('data.product_id', $product->id)
            ->set('data.grade_id', $this->grade->id)
            ->set('data.pack_date', now()->format('Y-m-d'))
            ->set('data.qty_pcs_combined', $input)
            ->set('data.ph_level', '5.5')
            ->call('create');
    }

    // =====================================================================
    // Batas per produk
    // =====================================================================

    public function test_the_default_limit_is_100_kg_and_a_product_can_raise_it(): void
    {
        $this->assertSame(100.0, LabelWeightLimit::forProduct($this->meat));
        $this->assertSame(9999.99, LabelWeightLimit::forProduct($this->offal));
        $this->assertSame(100.0, LabelWeightLimit::forProduct(null));

        $this->assertFalse(LabelWeightLimit::exceeds($this->meat, 100.0), 'tepat di batas tidak diperingatkan');
        $this->assertTrue(LabelWeightLimit::exceeds($this->meat, 100.01));
        $this->assertFalse(LabelWeightLimit::exceeds($this->offal, 5747.66));
    }

    // =====================================================================
    // Langkah 2: konfirmasi, bukan penolakan
    // =====================================================================

    public function test_a_normal_weight_is_saved_without_asking(): void
    {
        $this->label($this->meat, '22.5/8')->assertNotified();

        $this->assertSame(1, BoningItem::count());
    }

    public function test_an_unusual_weight_asks_first_and_saves_nothing_until_confirmed(): void
    {
        $page = $this->label($this->meat, '2250/8');

        $page->assertActionMounted('confirmAbnormalLabelWeight');
        $this->assertSame(0, BoningItem::count(), 'belum dikonfirmasi: tidak boleh ada stok lahir');
        $this->assertSame(0, BeefStock::count());

        $page->callMountedAction();

        $this->assertSame(1, BoningItem::count());
        $this->assertEqualsWithDelta(2250.0, (float) BoningItem::first()->weight, 0.001);
    }

    public function test_a_product_with_a_high_limit_is_not_asked(): void
    {
        $page = $this->label($this->offal, '5747.66/1');

        $page->assertActionNotMounted('confirmAbnormalLabelWeight');
        $this->assertSame(1, BoningItem::count());
    }

    // =====================================================================
    // Langkah 3: pecah
    // =====================================================================

    public function test_split_keeps_the_exact_total_in_cents_and_pcs(): void
    {
        foreach ([[16000.0, 4], [10000.0, 1], [20000.01, 7], [29999.97, 100 - 1], [9999.99, 5]] as [$kg, $pcs]) {
            $labels = BarcodeSegments::split($kg, $pcs);

            $this->assertEqualsWithDelta($kg, array_sum(array_column($labels, 'weight')), 0.0001, "berat $kg");
            $this->assertSame($pcs, array_sum(array_column($labels, 'pcs')), "pcs $pcs");

            foreach ($labels as $label) {
                $this->assertLessThanOrEqual(BarcodeSegments::BERAT_MAKS, $label['weight']);
            }
        }
    }

    public function test_a_weight_that_fits_is_one_label_and_a_big_one_is_split_evenly(): void
    {
        $this->assertCount(1, BarcodeSegments::split(9999.99, 3));
        $this->assertCount(2, BarcodeSegments::split(10000.0, 3));
        $this->assertSame(
            [['weight' => 8000.0, 'pcs' => 1], ['weight' => 8000.0, 'pcs' => 0]],
            BarcodeSegments::split(16000.0, 1),
        );
    }

    public function test_a_boning_label_over_the_barcode_limit_becomes_several_labels(): void
    {
        $this->label($this->offal, '16000/1');

        $items = BoningItem::orderBy('id')->get();

        $this->assertCount(2, $items);
        $this->assertEqualsWithDelta(16000.0, (float) $items->sum('weight'), 0.001);
        $this->assertSame(2, BeefStock::count());
        $this->assertSame(2, BeefStockMovement::count());
        $this->assertEqualsWithDelta(16000.0, (float) BeefStock::sum('weight'), 0.001);

        foreach ($items as $item) {
            $this->assertSame(28, strlen($item->barcode));
            $this->assertSame('BONING', BeefStock::where('barcode', $item->barcode)->value('origin'));
            $this->assertEqualsWithDelta((float) $item->weight, BarcodeSegments::parse($item->barcode)['weight'], 0.001);
        }

        $this->assertCount(2, $items->pluck('barcode')->unique(), 'barcode harus berbeda');
    }

    public function test_a_failure_halfway_through_a_split_leaves_no_partial_labels(): void
    {
        // 100 pcs tidak muat di segmen pcs -> ditolak SEBELUM atau di tengah
        // pemecahan. Satu transaksi: tidak boleh ada label separuh jadi.
        $this->label($this->offal, '16000/250');

        $this->assertSame(0, BoningItem::count());
        $this->assertSame(0, BeefStock::count());
        $this->assertSame(0, BeefStockMovement::count());
    }
}
