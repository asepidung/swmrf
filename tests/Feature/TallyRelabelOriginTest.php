<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\TallyResource\Pages\ScanTally;
use App\Helpers\BarcodeHelper;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Relabel Tally membawa origin ASLI barang, bukan `6` miliknya sendiri
 * (keputusan Owner 5 Oktober 2026, issue #497): relabel menandai ulang
 * barang yang sama, ia bukan modul produksi baru.
 */
class TallyRelabelOriginTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tally $tally;

    private Product $product;

    private Warehouse $warehouse;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $customer = Customer::create([
            'name' => 'BLACK OWL', 'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
            'address' => 'Ruko PIK', 'pic' => 'John', 'phone' => '0812345678', 'top' => 30,
        ]);
        $this->product = Product::create([
            'name' => 'CHUCK', 'code' => '100100',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true])->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->warehouse = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $so = SalesOrder::create([
            'customer_id' => $customer->id, 'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id, 'status' => 'processing',
        ]);
        $this->tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'processing']);
    }

    private function tallyItem(string $barcode): TallyItem
    {
        return TallyItem::create([
            'tally_id' => $this->tally->id, 'barcode' => $barcode,
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 11.11, 'qty_pcs' => 5, 'ph_level' => 5.4,
            'pack_date' => '2026-06-14', 'origin' => 'BONING',
        ]);
    }

    private function relabel(TallyItem $item, string $newPackDate = '2026-10-05'): void
    {
        Livewire::actingAs($this->user)
            ->test(ScanTally::class, ['record' => $this->tally])
            ->set('podLimit', 5)
            ->callTableAction('relabel', $item, ['pack_date' => $newPackDate, 'show_exp' => false]);
    }

    public function test_the_origin_digit_is_carried_from_a_standard_barcode(): void
    {
        // Barcode standar berawalan 2 (Repack stock), 28 digit.
        $old = '2140626100100100111105540001';
        $item = $this->tallyItem($old);

        $this->relabel($item);

        $item->refresh();

        $this->assertSame(28, strlen($item->barcode));
        $this->assertSame('2', substr($item->barcode, 0, 1));
        $this->assertSame('R-STCK', BarcodeHelper::getOrigin($item->barcode));
        $this->assertSame('051026', substr($item->barcode, 1, 6), 'Tanggalnya POD baru.');
        $this->assertSame($old, $item->original_barcode);
    }

    public function test_a_legacy_barcode_is_mapped_to_the_new_origin(): void
    {
        // Legacy awalan 3 = Repack Stock -> origin baru 2 (R-STCK).
        $item = $this->tallyItem('3456260928000048196');

        $this->relabel($item);

        $item->refresh();

        $this->assertSame('2', substr($item->barcode, 0, 1));
        $this->assertSame('3456260928000048196', $item->original_barcode);
    }

    public function test_an_unknown_barcode_becomes_the_found_origin(): void
    {
        $item = $this->tallyItem('9999999');

        $this->relabel($item);

        $this->assertSame('0', substr($item->refresh()->barcode, 0, 1));
    }

    public function test_a_second_relabel_keeps_the_very_first_barcode(): void
    {
        $first = '1140626100100100111105540001';
        $item = $this->tallyItem($first);

        $this->relabel($item, '2026-10-05');
        $afterFirst = $item->refresh()->barcode;

        // Waktu berlalu: barangnya kembali melewati batas POD, relabel terbuka lagi.
        $item->update(['pack_date' => '2026-06-14']);

        $this->relabel($item, '2026-10-06');

        $this->assertNotSame($afterFirst, $item->refresh()->barcode);
        $this->assertSame($first, $item->original_barcode, 'Yang tersimpan tetap barcode PERTAMA, bukan yang sebelumnya.');
    }

    public function test_the_old_tally_movement_keeps_its_barcode_and_the_relabel_is_logged(): void
    {
        $old = '1140626100100100111105540001';
        $item = $this->tallyItem($old);

        $movement = BeefStockMovement::create([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'condition' => $this->grade->id, 'barcode' => $old, 'transaction_type' => 'TALLY',
            'reference_document' => $this->tally->tally_number, 'weight_in' => 0, 'weight_out' => 11.11,
            'pcs_in' => 0, 'pcs_out' => 5, 'created_by' => $this->user->id,
        ]);

        $this->relabel($item);

        $item->refresh();

        $this->assertSame($old, $movement->refresh()->barcode, 'Riwayat per barcode tidak boleh ditulis ulang.');
        $this->assertDatabaseHas('beef_stock_movements', [
            'transaction_type' => 'TALLY_RELABEL',
            'barcode' => $item->barcode,
        ]);
    }

    public function test_the_new_sequence_never_collides_with_a_label_born_elsewhere_with_the_same_prefix(): void
    {
        $old = '1140626100100100111105540001';
        $item = $this->tallyItem($old);

        // Barang lain, lahir di tempat lain (beef_stocks), awalan origin+tanggal sama
        // dengan yang akan dihasilkan relabel -- seluruh segmen lain juga sama.
        $clash = '1051026100100100111105540001';
        BeefStock::create([
            'barcode' => $clash, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 11.11, 'qty_pcs' => 5, 'ph_level' => 5.4,
            'pack_date' => '2026-10-05', 'origin' => 'BONING', 'status' => 'IN_STOCK',
        ]);

        $this->relabel($item, '2026-10-05');

        $this->assertSame('1051026100100100111105540002', $item->refresh()->barcode);
    }
}
