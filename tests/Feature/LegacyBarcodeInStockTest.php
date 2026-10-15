<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MutationResource\Pages\ScanMutation;
use App\Filament\Admin\Resources\RepackResource\Pages\InputBahanRepack;
use App\Filament\Admin\Resources\StockTakeResource\Pages\CreateStockTake;
use App\Filament\Admin\Resources\StockTakeResource\Pages\ScanStockTake;
use App\Filament\Admin\Resources\TallyResource\Pages\ScanTally;
use App\Models\BeefStock;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Grade;
use App\Models\Mutation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Repack;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Models\Tally;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BarcodeSegments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stok awal go-live masuk ke `beef_stocks` dengan BARCODE LEGACY apa adanya
 * (issue #535, A.3): angka 18-20 digit dari aplikasi lama, bukan barcode standar
 * 28 digit. Barang itu harus dikenali sebagai barang yang SAH di Tally, Opname,
 * Mutasi, dan Repack -- bukan barang asing yang ditolak atau diperlakukan temuan.
 *
 * Hanya yang BERADA di stok yang dikenali; barcode legacy yang tidak ada di stok
 * tetap ditolak seperti biasa.
 */
class LegacyBarcodeInStockTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private Warehouse $warehouse;

    private Warehouse $other;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        $this->product = Product::create(['name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->other = Warehouse::create(['code' => 'PRM', 'name' => 'PERUM', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
    }

    /** @return array<string, array{string}> barcode legacy 18, 19, dan 20 digit */
    public static function legacyBarcodes(): array
    {
        return [
            '18 digit' => ['100123456789012345'],
            '19 digit' => ['2001234567890123456'],
            '20 digit' => ['30012345678901234567'],
        ];
    }

    private function legacyStock(string $barcode): BeefStock
    {
        return BeefStock::create([
            'barcode' => $barcode, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 12.5, 'qty_pcs' => 2, 'pack_date' => '2026-10-01',
            'exp_date' => '2027-10-01', 'origin' => 'LEGACY', 'status' => 'IN_STOCK',
        ]);
    }

    /** @test */
    public function the_fixture_barcodes_really_are_not_standard_ones(): void
    {
        foreach (self::legacyBarcodes() as [$barcode]) {
            $this->assertMatchesRegularExpression('/^\d{18,20}$/', $barcode);
            $this->assertFalse(BarcodeSegments::isStandard($barcode), 'Barcode standar SWM 28 digit; ini barcode legacy.');
        }
    }

    /**
     * @test
     *
     * @dataProvider legacyBarcodes
     */
    public function a_legacy_barcode_in_stock_is_scanned_into_a_tally(string $barcode): void
    {
        $customer = Customer::create(['name' => 'PELANGGAN UJI', 'address' => 'Bogor', 'top_days' => 30, 'customer_segment_id' => CustomerSegment::firstOrCreate(['name' => 'RETAIL'])->id]);
        $so = SalesOrder::create(['customer_id' => $customer->id, 'delivery_date' => now()->addDays(2)->toDateString(), 'po_number' => 'PO-UJI', 'created_by' => $this->user->id, 'status' => 'waiting']);
        SalesOrderItem::create(['sales_order_id' => $so->id, 'product_id' => $this->product->id, 'weight' => 20.0, 'price' => 150000]);
        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'processing']);
        $so->update(['status' => 'processing']);
        $this->legacyStock($barcode);

        Livewire::actingAs($this->user)->test(ScanTally::class, ['record' => $tally])->set('barcode', $barcode)->call('scan');

        $this->assertDatabaseMissing('beef_stocks', ['barcode' => $barcode]);
        $this->assertDatabaseHas('tally_items', ['tally_id' => $tally->id, 'barcode' => $barcode, 'product_id' => $this->product->id]);
    }

    /** @test */
    public function a_legacy_barcode_that_is_not_in_stock_is_still_refused_by_the_tally(): void
    {
        $customer = Customer::create(['name' => 'PELANGGAN UJI', 'address' => 'Bogor', 'top_days' => 30, 'customer_segment_id' => CustomerSegment::firstOrCreate(['name' => 'RETAIL'])->id]);
        $so = SalesOrder::create(['customer_id' => $customer->id, 'delivery_date' => now()->addDays(2)->toDateString(), 'po_number' => 'PO-UJI', 'created_by' => $this->user->id, 'status' => 'waiting']);
        SalesOrderItem::create(['sales_order_id' => $so->id, 'product_id' => $this->product->id, 'weight' => 20.0, 'price' => 150000]);
        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'processing']);
        $so->update(['status' => 'processing']);

        Livewire::actingAs($this->user)->test(ScanTally::class, ['record' => $tally])->set('barcode', '100999999999999999')->call('scan');

        $this->assertDatabaseMissing('tally_items', ['barcode' => '100999999999999999']);
    }

    /**
     * @test
     *
     * @dataProvider legacyBarcodes
     */
    public function a_legacy_barcode_in_stock_is_matched_in_an_opname_not_treated_as_a_stray_find(string $barcode): void
    {
        $this->legacyStock($barcode);

        Livewire::actingAs($this->user)->test(CreateStockTake::class)
            ->fillForm(['period' => now()->format('Y-m'), 'date' => now()->toDateString()])
            ->call('create')
            ->assertHasNoFormErrors();
        $opname = StockTake::latest('id')->firstOrFail();

        $snapshot = StockTakeItem::where('stock_take_id', $opname->id)->where('barcode', $barcode)->first();
        $this->assertNotNull($snapshot, 'Barcode legacy di stok masuk ke daftar opname.');
        $this->assertSame('MISSING', $snapshot->status);

        Livewire::actingAs($this->user)->test(ScanStockTake::class, ['record' => $opname])->set('barcode', $barcode)->call('scan');

        $this->assertSame('MATCHED', $snapshot->fresh()->status, 'Dikenali sebagai barang yang ada, bukan temuan.');
        $this->assertSame(1, StockTakeItem::where('stock_take_id', $opname->id)->where('barcode', $barcode)->count(), 'Tidak ada baris temuan kedua.');
        $this->assertSame(0, StockTakeItem::where('stock_take_id', $opname->id)->where('status', 'UNEXPECTED')->count());
    }

    /**
     * @test
     *
     * @dataProvider legacyBarcodes
     */
    public function a_legacy_barcode_in_stock_is_moved_by_a_mutation(string $barcode): void
    {
        $this->legacyStock($barcode);
        $mutation = Mutation::create([
            'mutation_date' => now()->toDateString(), 'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => $this->other->id, 'status' => 'DRAFT', 'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)->test(ScanMutation::class, ['record' => $mutation])->set('barcode', $barcode)->call('addBarcode');

        $this->assertDatabaseMissing('beef_stocks', ['barcode' => $barcode]);
        $this->assertDatabaseHas('mutation_items', ['mutation_id' => $mutation->id, 'barcode' => $barcode]);
    }

    /**
     * @test
     *
     * @dataProvider legacyBarcodes
     */
    public function a_legacy_barcode_in_stock_is_taken_as_repack_input(string $barcode): void
    {
        $this->legacyStock($barcode);
        $repack = Repack::create(['repack_date' => now()->toDateString(), 'note' => 'Uji', 'created_by' => $this->user->id]);

        Livewire::actingAs($this->user)->test(InputBahanRepack::class, ['record' => $repack])
            ->set('data.barcode', $barcode)
            ->call('submitBarcode')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('beef_stocks', ['barcode' => $barcode]);
        $this->assertDatabaseHas('repack_materials', ['repack_id' => $repack->id, 'barcode' => $barcode, 'product_id' => $this->product->id]);
    }
}
