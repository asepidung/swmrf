<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RepackResource\Pages\InputHasilRepack;
use App\Models\Boning;
use App\Models\BoningItem;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Repack;
use App\Models\RepackResult;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BarcodeUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Penanda R (sudah jadi bahan repack) dan T (sudah masuk tally) di label kertas dan daftar hasil repack. */
class UsedBarcodeMarkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private Warehouse $warehouse;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $kategori = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN BEEF', 'code' => 'B001', 'category_id' => $kategori->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    private function jadikanBahanRepack(string $barcode): void
    {
        $repack = Repack::create(['repack_date' => now()->toDateString(), 'created_by' => $this->user->id]);

        DB::table('repack_materials')->insert([
            'repack_id' => $repack->id, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'barcode' => $barcode, 'weight' => 10, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function masukkanKeTally(string $barcode): void
    {
        $segmen = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $pelanggan = Customer::create([
            'name' => 'BLACK OWL', 'customer_segment_id' => $segmen->id, 'address' => 'Ruko PIK',
            'pic' => 'John', 'phone' => '0812345678', 'top' => 30,
        ]);
        $so = SalesOrder::create([
            'customer_id' => $pelanggan->id, 'delivery_date' => now()->addDays(2)->toDateString(),
            'po_number' => 'PO1', 'created_by' => $this->user->id, 'status' => 'processing',
        ]);
        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'processing']);

        TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => $barcode, 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id, 'weight' => 10,
            'qty_pcs' => 1, 'ph_level' => 5.5, 'pack_date' => now(), 'exp_date' => now()->addMonths(3),
            'origin' => 'BONING',
        ]);
    }

    private function hasilRepack(string $barcode): RepackResult
    {
        $repack = Repack::create(['repack_date' => now()->toDateString(), 'created_by' => $this->user->id]);

        return RepackResult::create([
            'repack_id' => $repack->id, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 10, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'exp_date' => now()->addMonths(3)->toDateString(),
            'barcode' => $barcode,
        ]);
    }

    private function itemBoning(string $barcode): BoningItem
    {
        $boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $this->user->id]);

        return BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 10, 'qty_pcs' => 1, 'ph_level' => 5.5,
            'pack_date' => now(), 'exp_date' => now()->addMonths(3), 'barcode' => $barcode, 'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function the_marks_follow_where_the_barcode_was_used(): void
    {
        $this->assertSame([], BarcodeUsage::marks('BC-BARU'));

        $this->jadikanBahanRepack('BC-R');
        $this->masukkanKeTally('BC-T');
        $this->jadikanBahanRepack('BC-RT');
        DB::table('tally_items')->insert(['origin' => 'BONING', 'tally_id' => Tally::first()->id, 'barcode' => 'BC-RT', 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id, 'weight' => 10, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(['R'], BarcodeUsage::marks('BC-R'));
        $this->assertSame(['T'], BarcodeUsage::marks('BC-T'));
        $this->assertSame(['R', 'T'], BarcodeUsage::marks('BC-RT'));
    }

    /** @test */
    public function the_repack_result_list_shows_r_and_t_for_used_barcodes_only(): void
    {
        $segar = $this->hasilRepack('BC-SEGAR');
        $bahan = $this->hasilRepack('BC-BAHAN');
        $this->jadikanBahanRepack('BC-BAHAN');
        $terkirim = $this->hasilRepack('BC-TALLY');
        $this->masukkanKeTally('BC-TALLY');

        $repack = $segar->repack;

        $test = Livewire::actingAs($this->user)->test(InputHasilRepack::class, ['record' => $repack]);
        $test->assertTableActionHidden('repack_status', $segar)->assertTableActionHidden('tally_status', $segar)->assertTableActionVisible('delete', $segar);

        Livewire::actingAs($this->user)->test(InputHasilRepack::class, ['record' => $bahan->repack])
            ->assertTableActionVisible('repack_status', $bahan)->assertTableActionHidden('tally_status', $bahan)->assertTableActionHidden('delete', $bahan);

        Livewire::actingAs($this->user)->test(InputHasilRepack::class, ['record' => $terkirim->repack])
            ->assertTableActionVisible('tally_status', $terkirim)->assertTableActionHidden('repack_status', $terkirim)->assertTableActionHidden('delete', $terkirim);
    }

    /** @test */
    public function a_used_result_cannot_be_voided(): void
    {
        $bahan = $this->hasilRepack('BC-BAHAN');
        $this->jadikanBahanRepack('BC-BAHAN');

        Livewire::actingAs($this->user)->test(InputHasilRepack::class, ['record' => $bahan->repack])
            ->assertTableActionHidden('delete', $bahan);

        $this->assertNotNull(RepackResult::find($bahan->id));
    }
}
