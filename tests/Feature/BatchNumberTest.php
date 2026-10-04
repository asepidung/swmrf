<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BoningResource\Pages\LabelingBoning;
use App\Filament\Admin\Resources\TallyResource\Pages\ScanTally;
use App\Filament\Clusters\BeefStocks\Pages\FoundItemScanner;
use App\Models\BeefStock;
use App\Models\Boning;
use App\Models\BoningItem;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BatchLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch barang = nomor dokumen induk produksi, disimpan sebagai KOLOM
 * `batch_no` (issue #497, keputusan Owner 5 Oktober 2026) -- bukan segmen
 * barcode, yang tetap 28 digit.
 */
class BatchNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private Warehouse $warehouse;

    private Grade $grade;

    private Boning $boning;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'CHUCK', 'code' => '100100',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true])->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->warehouse = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $this->boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id]);
    }

    private function stock(string $barcode, array $extra = []): BeefStock
    {
        return BeefStock::create(array_merge([
            'barcode' => $barcode, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 11.11, 'qty_pcs' => 5, 'ph_level' => 5.4,
            'pack_date' => '2026-06-14', 'origin' => 'BONING', 'status' => 'IN_STOCK',
        ], $extra));
    }

    private function boningItem(string $barcode, ?string $batch): BoningItem
    {
        return BoningItem::create([
            'boning_id' => $this->boning->id, 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => 11.11, 'qty_pcs' => 5, 'pack_date' => '2026-06-14', 'barcode' => $barcode,
            'batch_no' => $batch, 'created_by' => $this->user->id,
        ]);
    }

    // =====================================================================
    // Skema dan penelusur
    // =====================================================================

    public function test_every_barcode_table_has_a_batch_column(): void
    {
        foreach (['boning_items', 'repack_results', 'goods_receipt_product_items', 'sales_return_items',
            'stock_take_items', 'tally_items', 'beef_stocks', 'mutation_items'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'batch_no'), "$table tidak punya batch_no");
        }
    }

    public function test_the_lookup_finds_a_batch_from_any_table_even_a_soft_deleted_row(): void
    {
        $item = $this->boningItem('1140626100100100111105540001', 'BN26001');
        $item->delete();

        $this->assertSame('BN26001', BatchLookup::forBarcode('1140626100100100111105540001'));
    }

    public function test_the_lookup_returns_null_for_blank_unknown_or_batchless_barcodes(): void
    {
        $this->boningItem('1140626100100100111105540002', null);

        $this->assertNull(BatchLookup::forBarcode(null));
        $this->assertNull(BatchLookup::forBarcode(''));
        $this->assertNull(BatchLookup::forBarcode('3456260928000048196'));
        $this->assertNull(BatchLookup::forBarcode('1140626100100100111105540002'), 'baris ada tapi tanpa batch');
    }

    public function test_every_model_with_a_batch_column_inherits_it_by_barcode(): void
    {
        foreach (BatchLookup::models() as $model) {
            $this->assertContains(
                \App\Models\Concerns\InheritsBatch::class,
                class_uses_recursive($model),
                "$model belum memakai InheritsBatch",
            );
            $this->assertContains('batch_no', (new $model)->getFillable() ?: ['batch_no'], "$model: batch_no tidak fillable");
        }
    }

    // =====================================================================
    // Mewarisi saat berpindah tabel
    // =====================================================================

    public function test_stock_created_for_a_known_barcode_inherits_the_batch(): void
    {
        $this->boningItem('1140626100100100111105540003', 'BN26002');

        $stock = $this->stock('1140626100100100111105540003');

        $this->assertSame('BN26002', $stock->batch_no);
    }

    public function test_an_explicit_batch_is_never_overwritten(): void
    {
        $this->boningItem('1140626100100100111105540004', 'BN26002');

        $stock = $this->stock('1140626100100100111105540004', ['batch_no' => 'RP#26007']);

        $this->assertSame('RP#26007', $stock->batch_no);
    }

    public function test_a_tally_item_keeps_the_batch_when_it_returns_to_stock(): void
    {
        $so = SalesOrder::create([
            'customer_id' => Customer::create([
                'name' => 'BLACK OWL',
                'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
                'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
            ])->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'), 'created_by' => $this->user->id, 'status' => 'processing',
        ]);
        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'processing']);

        $stock = $this->stock('1140626100100100111105540005', ['batch_no' => 'BN26003']);

        $item = TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => $stock->barcode, 'product_id' => $stock->product_id,
            'warehouse_id' => $stock->warehouse_id, 'grade_id' => $stock->grade_id, 'weight' => 11.11,
            'qty_pcs' => 5, 'pack_date' => '2026-06-14', 'origin' => 'BONING',
        ]);
        $stock->delete();

        $this->assertSame('BN26003', $item->batch_no, 'tally item mewarisi batch dari stok');

        // Menghapus baris tally mengembalikan barangnya ke stok (unscan).
        $item->delete();

        $this->assertSame('BN26003', BeefStock::where('barcode', $item->barcode)->value('batch_no'));
    }

    // =====================================================================
    // Penulis dari dokumen induk
    // =====================================================================

    public function test_boning_labels_carry_the_boning_document_number_as_batch(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        foreach (['view_bonings', 'edit_bonings'] as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Boning', 'description' => $name])->id
            );
        }

        Livewire::actingAs($user->fresh())
            ->test(LabelingBoning::class, ['record' => $this->boning])
            ->set('data.warehouse_id', $this->warehouse->id)
            ->set('data.product_id', $this->product->id)
            ->set('data.grade_id', $this->grade->id)
            ->set('data.pack_date', now()->format('Y-m-d'))
            ->set('data.qty_pcs_combined', '22.5/8')
            ->set('data.ph_level', '5.5')
            ->call('create');

        $this->assertMatchesRegularExpression('/^BN\d{5}$/', $this->boning->doc_no);
        $this->assertSame($this->boning->doc_no, BoningItem::firstOrFail()->batch_no);
        $this->assertSame($this->boning->doc_no, BeefStock::firstOrFail()->batch_no);
    }

    public function test_the_other_parent_document_writers_set_the_batch_themselves(): void
    {
        // Repack, dua jalur penerimaan produk, dan retur melahirkan barcode
        // dari dokumen induknya -- mereka WAJIB mengisi batch sendiri, karena
        // pewarisan lewat barcode tidak punya apa-apa untuk dicari di sana.
        $expect = [
            'Filament/Admin/Resources/RepackResource/Pages/InputHasilRepack.php' => '$this->record->doc_no',
            'Filament/Admin/Resources/GoodsReceiptProductResource/Pages/LabelingGoodsReceiptProduct.php' => '$this->record->gr_number',
            'Filament/Admin/Resources/GoodsReceiptProductResource/Pages/ScanGoodsReceiptProduct.php' => '$this->record->gr_number',
            'Filament/Admin/Resources/SalesReturnResource/Pages/InputReturnItems.php' => '$this->record->return_number',
        ];

        foreach ($expect as $path => $expression) {
            $this->assertStringContainsString(
                "'batch_no' => {$expression}",
                file_get_contents(app_path($path)),
                "$path tidak mengisi batch dari dokumen induknya.",
            );
        }
    }

    // =====================================================================
    // Relabel dan Label Rusak
    // =====================================================================

    public function test_relabel_carries_the_batch_and_looks_it_up_when_the_row_has_none(): void
    {
        $customer = Customer::create([
            'name' => 'BLACK OWL',
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
        $so = SalesOrder::create([
            'customer_id' => $customer->id, 'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id, 'status' => 'processing',
        ]);
        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'processing']);

        $withBatch = TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => '1140626100100100111105540006', 'batch_no' => 'BN26004',
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => 11.11, 'qty_pcs' => 5, 'pack_date' => '2026-06-14', 'origin' => 'BONING',
        ]);

        // Baris tally TANPA batch, tetapi barcodenya dikenal sebagai hasil boning.
        $this->boningItem('1140626100100100111105540007', 'BN26005');
        $withoutBatch = TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => '1140626100100100111105540007', 'batch_no' => null,
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => 11.11, 'qty_pcs' => 5, 'pack_date' => '2026-06-14', 'origin' => 'BONING',
        ]);
        $withoutBatch->forceFill(['batch_no' => null])->saveQuietly();

        foreach ([$withBatch, $withoutBatch] as $item) {
            Livewire::actingAs($this->user)
                ->test(ScanTally::class, ['record' => $tally])
                ->set('podLimit', 5)
                ->callTableAction('relabel', $item, ['pack_date' => '2026-10-05', 'show_exp' => false]);
        }

        $this->assertSame('BN26004', $withBatch->refresh()->batch_no);
        $this->assertSame('BN26005', $withoutBatch->refresh()->batch_no);
    }

    public function test_a_damaged_label_find_inherits_the_batch_of_the_original_barcode_but_stays_origin_zero(): void
    {
        $original = '1140626100100100111105540008';
        $this->boningItem($original, 'BN26006');

        Livewire::actingAs($this->user)
            ->test(FoundItemScanner::class)
            ->mountAction('manualInput')
            ->setActionData([
                'warehouse_id' => $this->warehouse->id, 'barcode' => $original,
                'product_id' => $this->product->id, 'grade_id' => $this->grade->id,
                'qty_pcs_combined' => '11.11/5', 'pack_date' => '2026-10-04', 'note' => 'label rusak',
            ])
            ->callMountedAction();

        $found = BeefStock::where('barcode', 'like', '0%')->firstOrFail();

        $this->assertSame('BN26006', $found->batch_no);
        $this->assertSame('0', substr($found->barcode, 0, 1));
    }

    public function test_a_find_without_an_original_barcode_has_no_batch(): void
    {
        Livewire::actingAs($this->user)
            ->test(FoundItemScanner::class)
            ->mountAction('manualInput')
            ->setActionData([
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->product->id, 'grade_id' => $this->grade->id,
                'qty_pcs_combined' => '11.11/5', 'pack_date' => '2026-10-04', 'note' => 'ditemukan',
            ])
            ->callMountedAction();

        $this->assertNull(BeefStock::firstOrFail()->batch_no);
    }
}
