<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\TallyResource\Pages\DraftTally;
use App\Filament\Admin\Resources\TallyResource\Pages\ScanTally;
use App\Models\BeefStock;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran Tally, 15 September 2026.
 *
 * Tiga celah sekaligus, semuanya di halaman CUSTOM (`extends Page` polos):
 * `scan()` dan `DraftTally::process()` adalah method/aksi yang cuma
 * menumpang gerbang `view_tallies` halamannya, tanpa satu pun pemeriksaan
 * izin sendiri. Tombol unscan (`Tables\Actions\DeleteAction`) malah tidak
 * punya penjagaan APA PUN -- termasuk tidak ada penjaga status, sehingga
 * tab yang masih terbuka di Tally yang sudah diapprove/jadi DO tetap bisa
 * mengembalikan barangnya ke stok.
 */
class TallySusulanTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Product $product;
    private Warehouse $warehouse;
    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        // Tally::booted() jatuh ke User::first()?->id ?? 1 untuk
        // created_by saat tidak ada yang login (fixture dibuat sebelum
        // actingAs()). Tanpa satu user pun ada, itu jatuh ke id 1 yang
        // tidak pernah ada di basis data test -- FK violation.
        User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $segment = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $group = CustomerGroup::create(['name' => 'FIXTURE GROUP']);
        $this->customer = Customer::create([
            'name' => 'FIXTURE CUSTOMER', 'customer_segment_id' => $segment->id,
            'customer_group_id' => $group->id, 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->warehouse = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (array_unique([...$permissionNames, 'view_tallies']) as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Tallies', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function waitingSalesOrder(): SalesOrder
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->addDay()->format('Y-m-d'),
            'po_number' => 'PO-FIXTURE-'.uniqid(), 'status' => SalesOrder::STATUS_WAITING,
        ]);
        SalesOrderItem::create([
            'sales_order_id' => $so->id, 'product_id' => $this->product->id, 'weight' => 20.0, 'price' => 150000,
        ]);

        return $so;
    }

    private function processingTally(SalesOrder $so): Tally
    {
        return Tally::create(['sales_order_id' => $so->id, 'status' => Tally::STATUS_PROCESSING]);
    }

    private function stock(string $barcode = 'FX-BARCODE-1'): BeefStock
    {
        return BeefStock::create([
            'barcode' => $barcode, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1,
            'pack_date' => now(), 'origin' => 'BONING', 'status' => 'IN_STOCK',
        ]);
    }

    // =========================================================================
    // scan() butuh edit_tallies
    // =========================================================================

    /** @test */
    public function scanning_without_the_permission_does_not_move_stock(): void
    {
        $so = $this->waitingSalesOrder();
        $tally = $this->processingTally($so);
        $this->stock('FX-NOPERM');

        Livewire::actingAs($this->employee()) // tanpa edit_tallies
            ->test(ScanTally::class, ['record' => $tally])
            ->set('barcode', 'FX-NOPERM')
            ->call('scan');

        $this->assertDatabaseHas('beef_stocks', ['barcode' => 'FX-NOPERM']);
        $this->assertSame(0, TallyItem::count());
    }

    /** @test */
    public function scanning_with_the_permission_moves_stock(): void
    {
        $so = $this->waitingSalesOrder();
        $tally = $this->processingTally($so);
        $this->stock('FX-WITHPERM');

        Livewire::actingAs($this->employee(['edit_tallies']))
            ->test(ScanTally::class, ['record' => $tally])
            ->set('barcode', 'FX-WITHPERM')
            ->call('scan');

        $this->assertDatabaseMissing('beef_stocks', ['barcode' => 'FX-WITHPERM']);
        $this->assertDatabaseHas('tally_items', ['tally_id' => $tally->id, 'barcode' => 'FX-WITHPERM']);
    }

    // =========================================================================
    // Unscan: izin DAN status -- tab basi tidak boleh mengembalikan stok
    // =========================================================================

    /** @test */
    public function unscanning_without_the_permission_leaves_the_item_in_place(): void
    {
        $so = $this->waitingSalesOrder();
        $tally = $this->processingTally($so);
        $item = TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => 'FX-UNSCAN-1', 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1, 'pack_date' => now(), 'origin' => 'BONING',
        ]);

        Livewire::actingAs($this->employee()) // tanpa edit_tallies
            ->test(ScanTally::class, ['record' => $tally])
            ->mountTableAction('delete', $item->id)
            ->callMountedTableAction();

        $this->assertDatabaseHas('tally_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('beef_stocks', ['barcode' => 'FX-UNSCAN-1']);
    }

    /**
     * Skenario tab basi: halaman dimuat saat Tally masih PROCESSING, lalu
     * Tally-nya di-approve (jadi LOCKED) di sesi/tab lain. Tab lama masih
     * mencoba unscan -- harus ditolak, dan barangnya TIDAK boleh kembali
     * ke stok (fisiknya sudah "dikirim" begitu status berubah).
     */
    /** @test */
    public function unscanning_after_the_tally_has_moved_past_processing_returns_nothing_to_stock(): void
    {
        $so = $this->waitingSalesOrder();
        $tally = $this->processingTally($so);
        $item = TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => 'FX-STALE-TAB', 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1, 'pack_date' => now(), 'origin' => 'BONING',
        ]);

        // Halaman "dibuka" SELAGI status masih PROCESSING -- mount()
        // sendiri menolak begitu status sudah bukan itu, jadi mount-nya
        // wajib terjadi lebih dulu, sebelum baris di bawah mengubah
        // statusnya lewat "sesi lain".
        $test = Livewire::actingAs($this->employee(['edit_tallies']))
            ->test(ScanTally::class, ['record' => $tally]);

        // Tally disetujui dari sesi lain -- statusnya berubah di basis
        // data, tapi instance Livewire yang "masih terbuka" di atas tidak
        // tahu (persis tab lama yang tidak di-refresh).
        Tally::whereKey($tally->id)->update(['status' => Tally::STATUS_LOCKED]);

        $test->mountTableAction('delete', $item->id)
            ->callMountedTableAction();

        $this->assertDatabaseHas('tally_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('beef_stocks', ['barcode' => 'FX-STALE-TAB']);
    }

    /** @test */
    public function unscanning_while_still_processing_returns_the_item_to_stock(): void
    {
        $so = $this->waitingSalesOrder();
        $tally = $this->processingTally($so);
        $item = TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => 'FX-NORMAL-UNSCAN', 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1, 'pack_date' => now(), 'origin' => 'BONING',
        ]);

        Livewire::actingAs($this->employee(['edit_tallies']))
            ->test(ScanTally::class, ['record' => $tally])
            ->mountTableAction('delete', $item->id)
            ->callMountedTableAction();

        $this->assertDatabaseMissing('tally_items', ['id' => $item->id]);
        $this->assertDatabaseHas('beef_stocks', ['barcode' => 'FX-NORMAL-UNSCAN', 'status' => 'IN_STOCK']);
    }

    // =========================================================================
    // DraftTally::process() butuh create_tallies, dan tidak boleh dobel
    // =========================================================================

    /** @test */
    public function creating_a_tally_without_the_permission_does_nothing(): void
    {
        $so = $this->waitingSalesOrder();

        Livewire::actingAs($this->employee()) // tanpa create_tallies
            ->test(DraftTally::class)
            ->mountTableAction('process', $so->id)
            ->set('mountedTableActionsData.0.pod_limit', 30)
            ->callMountedTableAction();

        $this->assertSame(0, Tally::count());
        $this->assertSame(SalesOrder::STATUS_WAITING, $so->fresh()->status);
    }

    /** @test */
    public function double_clicking_create_tally_makes_only_one_tally(): void
    {
        $so = $this->waitingSalesOrder();
        $user = $this->employee(['create_tallies']);

        foreach ([1, 2] as $percobaan) {
            Livewire::actingAs($user)
                ->test(DraftTally::class)
                ->mountTableAction('process', $so->fresh()->id)
                ->set('mountedTableActionsData.0.pod_limit', 30)
                ->callMountedTableAction();
        }

        $this->assertSame(1, Tally::where('sales_order_id', $so->id)->count());
        $this->assertSame(Tally::STATUS_PROCESSING, $so->fresh()->status);
    }
}
