<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BoningResource\Pages\MaterialUsageBoning;
use App\Filament\Admin\Resources\BoningResource\Pages\ViewBoning;
use App\Models\Boning;
use App\Models\BoningCarcass;
use App\Models\BoningItem;
use App\Models\Carcass;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;
use App\Models\Grade;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMaterial;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductionMaterialSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Issue #509, langkah 5: ringkasan pemakaian bahan sebuah dokumen -- di halaman
 * dokumen (terkunci = hanya baca), halaman View, cetakan, dan Excel.
 */
class ProductionMaterialSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Material $karton;

    private Material $plastik;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer', 'username' => 'prog_'.uniqid(), 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'programmer', 'is_active' => true,
        ]);
        $this->actingAs($this->user);

        $category = MaterialCategory::firstOrCreate(['name' => 'PACKAGING']);
        $unit = MaterialUnit::firstOrCreate(['name' => 'PCS']);

        $this->karton = Material::create(['name' => 'KARTON TOP', 'material_category_id' => $category->id, 'material_unit_id' => $unit->id, 'min_stock' => 0, 'is_active' => true]);
        $this->plastik = Material::create(['name' => 'PLASTIK VAKUM', 'material_category_id' => $category->id, 'material_unit_id' => $unit->id, 'min_stock' => 0, 'is_active' => true, 'content_per_unit' => 1000]);

        $this->supplier = Supplier::create(['name' => 'PEMASOK '.uniqid(), 'address' => 'Bogor', 'pic' => 'A', 'top_days' => 30]);
    }

    /** Boning dengan 2 label (BOM: karton 1 per box) siap dikunci. */
    private function boning(bool $withWaste = true): Boning
    {
        $category = ProductCategory::firstOrCreate(['name' => 'DAGING'], ['prefix' => 1]);
        $product = Product::create(['code' => (string) random_int(100000, 999999), 'name' => 'TENDERLOIN', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        ProductMaterial::create(['product_id' => $product->id, 'material_id' => $this->karton->id, 'quantity' => 1, 'basis' => 'box']);

        $warehouse = Warehouse::firstOrCreate(['code' => 'JGL'], ['name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::firstOrCreate(['name' => 'CHILL'], ['is_active' => true]);

        $boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id]);

        foreach ([5, 4] as $i => $pcs) {
            BoningItem::create([
                'boning_id' => $boning->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'grade_id' => $grade->id,
                'weight' => 10, 'qty_pcs' => $pcs, 'pack_date' => now()->toDateString(), 'barcode' => 'BC-'.uniqid().$i, 'created_by' => $this->user->id,
            ]);
        }

        $class = CattleClass::firstOrCreate(['name' => 'STEER'], ['is_active' => true]);
        $po = PurchaseCattle::create(['supplier_id' => $this->supplier->id, 'shipping_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        $po->items()->create(['cattle_class_id' => $class->id, 'qty' => 1, 'price' => 55000, 'created_by' => $this->user->id]);
        $receiving = CattleReceiving::create(['purchase_cattle_id' => $po->id, 'supplier_id' => $this->supplier->id, 'receive_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        $weighing = CattleWeighing::create(['cattle_receiving_id' => $receiving->id, 'weighing_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        $carcass = Carcass::create(['cattle_weighing_id' => $weighing->id, 'kill_date' => now()->toDateString(), 'created_by' => $this->user->id]);
        BoningCarcass::create(['boning_id' => $boning->id, 'carcass_id' => $carcass->id]);

        $boning->forceFill(['drylog_qty' => 2])->save();

        if ($withWaste) {
            $boning->materialWastes()->create(['material_id' => $this->plastik->id, 'qty' => 3, 'reason' => 'gagal vakum']);
        }

        return $boning->fresh();
    }

    /** Harga plastik: 1 box @ Rp 1.000.000, isi 1.000 -> Rp 1.000 per pcs. */
    private function priceThePlastic(): void
    {
        $this->priceMaterial($this->plastik, 1, 1000000);
    }

    private function priceMaterial(Material $material, int $qty, float $price): void
    {
        $requisition = DB::table('material_requisitions')->insertGetId([
            'document_number' => 'MR-'.uniqid(), 'user_id' => $this->user->id, 'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $po = DB::table('purchase_materials')->insertGetId([
            'po_number' => 'PO-'.uniqid(), 'material_requisition_id' => $requisition, 'supplier_id' => $this->supplier->id,
            'po_date' => now()->toDateString(), 'total_amount' => 0, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $gr = DB::table('goods_receipt_materials')->insertGetId([
            'gr_number' => 'GR-'.uniqid(), 'purchase_material_id' => $po, 'supplier_id' => $this->supplier->id,
            'receive_date' => now()->toDateString(), 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('goods_receipt_material_items')->insert([
            'goods_receipt_material_id' => $gr, 'material_id' => $material->id, 'qty_received' => $qty, 'price' => $price,
            'subtotal' => $qty * $price, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------------
    // Layanan ringkasan
    // ---------------------------------------------------------------------

    /** @test */
    public function an_unlocked_document_is_summarised_live_and_marked_not_final(): void
    {
        $summary = ProductionMaterialSummary::for($this->boning());

        $this->assertFalse($summary['final']);
        $this->assertSame('KARTON TOP', $summary['bom'][0]['material']);
        $this->assertSame(2, (int) $summary['bom'][0]['qty']);
        $this->assertSame(2, $summary['drylog']);
        $this->assertCount(1, $summary['wastes']);
        $this->assertNull($summary['wastes'][0]['amount'], 'Belum dikunci: belum bernilai.');
        $this->assertNull($summary['waste_total']);
    }

    /** @test */
    public function a_locked_document_is_summarised_from_the_frozen_figures(): void
    {
        $this->priceThePlastic();
        $boning = $this->boning();
        $boning->lock();

        // BOM diubah dan label ditambah sesudah dikunci: ringkasannya tidak bergeser.
        ProductMaterial::where('material_id', $this->karton->id)->update(['quantity' => 50]);

        $summary = ProductionMaterialSummary::for($boning->fresh());

        $this->assertTrue($summary['final']);
        $this->assertSame(2, (int) $summary['bom'][0]['qty']);
        $this->assertSame(3000.0, $summary['wastes'][0]['amount']);
        $this->assertSame(3000.0, $summary['waste_total']);
        $this->assertSame(0, $summary['unpriced']);
    }

    /** @test */
    public function a_locked_waste_without_any_price_is_counted_as_unpriced(): void
    {
        $boning = $this->boning();
        $boning->lock();

        $summary = ProductionMaterialSummary::for($boning->fresh());

        $this->assertSame(0.0, $summary['waste_total']);
        $this->assertSame(1, $summary['unpriced']);
    }

    /** @test */
    public function the_value_is_stored_on_the_waste_row_at_lock_and_cleared_at_unlock(): void
    {
        $this->priceThePlastic();
        $boning = $this->boning();

        $this->assertNull($boning->materialWastes()->first()->amount);

        $boning->lock();
        $row = $boning->materialWastes()->first();
        $this->assertSame('3000.00', (string) $row->amount);
        $this->assertSame('1000.0000', (string) $row->unit_price);

        $boning->fresh()->unlock();
        $row = $boning->materialWastes()->first();
        $this->assertNull($row->amount);
        $this->assertNull($row->unit_price);
    }

    // ---------------------------------------------------------------------
    // Halaman dokumen
    // ---------------------------------------------------------------------

    /** @test */
    public function a_locked_document_opens_the_page_read_only_with_the_frozen_figures(): void
    {
        $this->priceThePlastic();
        $boning = $this->boning();
        $boning->lock();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('PLASTIK VAKUM')
            ->assertSee('gagal vakum')
            ->assertSee('Rp 3.000')
            ->assertFormFieldDoesNotExist('drylog_qty');
    }

    /** @test */
    public function a_locked_document_cannot_be_changed_through_the_page(): void
    {
        $boning = $this->boning();
        $boning->lock();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->set('data.drylog_qty', 99)
            ->call('save')
            ->assertForbidden();

        $this->assertSame(2, $boning->fresh()->drylog_qty);
    }

    // ---------------------------------------------------------------------
    // Halaman View, Excel, cetak
    // ---------------------------------------------------------------------

    /** @test */
    public function the_view_page_shows_the_material_usage_section(): void
    {
        $this->priceThePlastic();
        $boning = $this->boning();
        $boning->lock();

        Livewire::test(ViewBoning::class, ['record' => $boning->getRouteKey()])
            ->assertSee('KARTON TOP')
            ->assertSee('PLASTIK VAKUM')
            ->assertSee('Rp 3.000');
    }

    /** @test */
    public function the_excel_download_carries_the_material_usage_blocks(): void
    {
        $this->priceThePlastic();
        $boning = $this->boning();
        $boning->lock();

        $csv = Livewire::test(ViewBoning::class, ['record' => $boning->getRouteKey()])
            ->instance()
            ->materialUsageCsv();

        $this->assertStringContainsString('"KARTON TOP",2,"pcs"', $csv);
        $this->assertStringContainsString('"Drylog / Pad Absorber",2', $csv);
        $this->assertStringContainsString('"PLASTIK VAKUM",3,"gagal vakum",3000', $csv);
        $this->assertStringContainsString('"Total wasted",,,3000', $csv);
    }

    private function permission(string $name): void
    {
        $this->user->forceFill(['role' => 'employee'])->save();
        $this->user->permissions()->attach(Permission::firstOrCreate(['name' => $name], ['module_name' => 'Test', 'description' => $name])->id);
        $this->actingAs($this->user->fresh());
    }

    /** @test */
    public function the_print_page_renders_the_summary_for_someone_who_may_view_bonings(): void
    {
        $this->priceThePlastic();
        $boning = $this->boning();
        $boning->lock();

        $this->permission('view_bonings');

        $this->get(route('production-material.print', ['kind' => 'boning', 'id' => $boning->id]))
            ->assertOk()
            ->assertSee($boning->doc_no)
            ->assertSee('PLASTIK VAKUM')
            ->assertSee('Rp 3.000');
    }

    /** @test */
    public function the_print_page_refuses_someone_who_may_not_view_bonings_and_an_unknown_kind(): void
    {
        $boning = $this->boning();

        $this->permission('view_products');

        $this->get(route('production-material.print', ['kind' => 'boning', 'id' => $boning->id]))->assertForbidden();
        $this->get(route('production-material.print', ['kind' => 'cow', 'id' => $boning->id]))->assertNotFound();
    }

    /** @test */
    public function a_document_locked_before_row_values_existed_falls_back_to_its_financial_loss_rows(): void
    {
        $this->priceThePlastic();
        $boning = $this->boning();
        $boning->lock();

        // Dokumen lama: nilai tidak tersimpan di barisnya, hanya di Financial Loss.
        $boning->materialWastes()->update(['amount' => null, 'unit_price' => null]);

        $summary = ProductionMaterialSummary::for($boning->fresh());

        $this->assertSame(3000.0, $summary['wastes'][0]['amount']);
        $this->assertSame(3000.0, $summary['waste_total']);
        $this->assertSame(0, $summary['unpriced']);
    }

    /** @test */
    public function the_locked_page_has_one_card_for_usage_with_the_drylog_and_one_for_waste(): void
    {
        $this->priceThePlastic();
        $boning = $this->boning();
        $boning->lock();

        $page = Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()]);

        // Kartu pertama memuat BOM dan drylog; kartu kedua memuat bahan terbuang.
        $page->assertSeeInOrder(['Material Usage', 'KARTON TOP', 'Drylog / Pad Absorber', 'Material Waste', 'PLASTIK VAKUM', 'gagal vakum']);
    }

    // ---------------------------------------------------------------------
    // Drylog dinilai rupiah (Owner, 8 Oktober 2026)
    // ---------------------------------------------------------------------

    private function drylogMaterial(string $name = 'DRYLOG'): Material
    {
        return Material::create([
            'name' => $name, 'material_category_id' => $this->karton->material_category_id,
            'material_unit_id' => $this->karton->material_unit_id, 'min_stock' => 0, 'is_active' => true,
        ]);
    }

    /** @test */
    public function the_drylog_is_valued_from_the_master_material_price_and_frozen_at_lock(): void
    {
        $drylog = $this->drylogMaterial();
        $this->priceMaterial($drylog, 10, 25000);

        $boning = $this->boning();   // drylog 2 pcs
        $this->assertNull($boning->drylog_amount, 'Belum dikunci: belum bernilai.');

        $boning->lock();
        $fresh = $boning->fresh();
        $this->assertSame('50000.00', (string) $fresh->drylog_amount, '2 pcs x Rp 25.000.');
        $this->assertSame('25000.0000', (string) $fresh->drylog_unit_price);

        // Harga naik sesudah dikunci: nilai tidak bergeser.
        $this->priceMaterial($drylog, 1, 900000);
        $this->assertSame(50000.0, ProductionMaterialSummary::for($boning->fresh())['drylog_amount']);
    }

    /** @test */
    public function the_drylog_material_is_recognised_by_name_among_the_accepted_names(): void
    {
        $this->assertNull(\App\Services\DrylogMaterial::find());

        $this->drylogMaterial('PAD ABSORBER');

        $this->assertSame('PAD ABSORBER', \App\Services\DrylogMaterial::find()->name);
    }

    /** @test */
    public function spelling_variants_of_the_drylog_name_are_all_recognised(): void
    {
        foreach (['DRY LOG', 'dry-lock', 'Dri-Loc', 'DRYLOG', 'DRY LOG - ABSORBENT PAD 3000', 'Absorbent Pad (Food Grade)', 'SOAKER PAD', 'PAD ABSORBER'] as $name) {
            Material::query()->whereIn('name', ['DRY LOG', 'dry-lock', 'Dri-Loc', 'DRYLOG', 'DRY LOG - ABSORBENT PAD 3000', 'Absorbent Pad (Food Grade)', 'SOAKER PAD', 'PAD ABSORBER'])->delete();
            $this->drylogMaterial($name);

            $this->assertNotNull(\App\Services\DrylogMaterial::find(), "'{$name}' seharusnya dikenali.");
        }
    }

    /** @test */
    public function unrelated_names_are_not_taken_for_drylog(): void
    {
        $this->drylogMaterial('DRYING RACK');
        $this->drylogMaterial('PLASTIK ABSORBENT');

        $this->assertNull(\App\Services\DrylogMaterial::find());
    }

    /** @test */
    public function without_a_drylog_material_or_price_the_drylog_is_zero_and_the_lock_still_works(): void
    {
        $boning = $this->boning();
        $boning->lock();

        $this->assertTrue($boning->fresh()->kunci);
        $this->assertSame(0.0, ProductionMaterialSummary::for($boning->fresh())['drylog_amount']);
    }

    /** @test */
    public function unlocking_clears_the_drylog_value(): void
    {
        $drylog = $this->drylogMaterial();
        $this->priceMaterial($drylog, 1, 1000);
        $boning = $this->boning();

        $boning->lock();
        $this->assertNotNull($boning->fresh()->drylog_amount);

        $boning->fresh()->unlock();
        $this->assertNull($boning->fresh()->drylog_amount);
        $this->assertNull($boning->fresh()->drylog_unit_price);
    }

    /** @test */
    public function the_locked_page_shows_the_drylog_name_and_value(): void
    {
        $drylog = $this->drylogMaterial();
        $this->priceMaterial($drylog, 10, 25000);
        $boning = $this->boning();
        $boning->lock();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->assertSee('DRYLOG')
            ->assertSee('Rp 50.000');
    }

    // ---------------------------------------------------------------------
    // Nilai rupiah pemakaian material (BOM + drylog), dibekukan saat Lock
    // ---------------------------------------------------------------------

    /** @test */
    public function bom_usage_is_valued_at_lock_and_the_value_stays_frozen(): void
    {
        $this->priceMaterial($this->karton, 10, 500);              // Rp 500 per pcs
        $drylog = $this->drylogMaterial();
        $this->priceMaterial($drylog, 10, 25000);                  // Rp 25.000 per pcs

        $boning = $this->boning();
        $this->assertNull(ProductionMaterialSummary::for($boning)['usage_total'], 'Belum dikunci: belum bernilai.');
        $this->assertNull(ProductionMaterialSummary::for($boning)['bom'][0]['amount']);

        $boning->lock();

        $row = $boning->bomSnapshots()->where('material_id', $this->karton->id)->first();
        $this->assertSame(500.0, $row->unit_price);
        $this->assertSame(1000.0, $row->amount, '2 pcs x Rp 500.');

        // Harga naik sesudah dikunci: nilai tidak bergeser.
        $this->priceMaterial($this->karton, 1, 900000);

        $summary = ProductionMaterialSummary::for($boning->fresh());
        $this->assertSame(1000.0, $summary['bom'][0]['amount']);
        $this->assertSame(51000.0, $summary['usage_total'], 'BOM Rp 1.000 + drylog 2 x Rp 25.000.');
        $this->assertSame(0, $summary['usage_unpriced']);
    }

    /** @test */
    public function a_bom_row_without_any_price_is_zero_and_counted_as_unpriced(): void
    {
        $boning = $this->boning();
        $boning->lock();

        $summary = ProductionMaterialSummary::for($boning->fresh());

        $this->assertSame(0.0, $summary['bom'][0]['amount']);
        $this->assertSame(0.0, $summary['usage_total']);
        $this->assertGreaterThanOrEqual(1, $summary['usage_unpriced']);
        $this->assertNotNull($boning->bomSnapshots()->first()->amount, 'Tanpa harga tetap disimpan 0, bukan NULL.');
    }

    /** @test */
    public function a_snapshot_locked_before_values_existed_shows_no_value_instead_of_a_guess(): void
    {
        $this->priceMaterial($this->karton, 10, 500);
        $boning = $this->boning();
        $boning->lock();
        $boning->bomSnapshots()->update(['unit_price' => null, 'amount' => null]);

        $line = ProductionMaterialSummary::for($boning->fresh())['bom'][0];

        $this->assertNull($line['amount']);
    }

    /** @test */
    public function unlocking_releases_the_frozen_usage_value(): void
    {
        $this->priceMaterial($this->karton, 10, 500);
        $boning = $this->boning();
        $boning->lock();
        $boning->fresh()->unlock();

        $this->assertSame(0, $boning->bomSnapshots()->count());
    }

    /** @test */
    public function the_locked_page_and_the_print_page_show_the_usage_value_and_total(): void
    {
        $this->priceMaterial($this->karton, 10, 500);
        $boning = $this->boning();
        $boning->lock();

        Livewire::test(MaterialUsageBoning::class, ['record' => $boning->getRouteKey()])
            ->assertSee('Rp 1.000')
            ->assertSee(__('Total usage value'));

        $this->get(route('production-material.print', ['kind' => 'boning', 'id' => $boning->id]))
            ->assertOk()
            ->assertSee('Rp 1.000');
    }
}
