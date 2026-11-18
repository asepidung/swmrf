<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\DeliveryOrderResource\Pages\ApproveDeliveryOrder;
use App\Filament\Admin\Resources\TallyResource\Pages\ScanTally;
use App\Filament\Admin\Resources\TallyResource\Pages\ViewTally;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TL-01 dan DO-01: aksi pada tally/surat jalan membaca ulang status dengan baris terkunci, jadi tab lama tidak bisa
 * memindahkan dokumen yang sudah berpindah tahap.
 */
class TallyTransitionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->user->permissions()->attach(\App\Models\Permission::firstOrCreate(['name' => 'lock_tallies'], ['module_name' => 'x', 'description' => 'x'])->id);
        $this->actingAs($this->user->fresh());

        $segment = CustomerSegment::create(['name' => 'SEG', 'is_active' => true]);
        $this->customer = Customer::create(['name' => 'TL CUSTOMER', 'customer_segment_id' => $segment->id, 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30]);
    }

    private function tally(string $status, string $soStatus = 'processing'): Tally
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->addDay()->format('Y-m-d'),
            'po_number' => 'PO-'.uniqid(), 'created_by' => $this->user->id, 'status' => $soStatus,
        ]);

        return Tally::create(['sales_order_id' => $so->id, 'tally_number' => 'TS#'.random_int(100000, 999999), 'status' => $status]);
    }

    private function deliveryOrder(Tally $tally, string $status): DeliveryOrder
    {
        return DeliveryOrder::create([
            'tally_id' => $tally->id, 'sales_order_id' => $tally->sales_order_id, 'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDay()->format('Y-m-d'), 'po_number' => 'PO-DO',
            'driver_id' => \App\Models\Driver::firstOrCreate(['name' => 'JOKO'])->id, 'status' => $status,
        ]);
    }

    /** @test */
    public function an_empty_tally_cannot_be_approved(): void
    {
        $tally = $this->tally('processing');

        Livewire::test(ScanTally::class, ['record' => $tally])->callAction('approve');

        $this->assertSame('processing', $tally->fresh()->status);
    }

    /** @test */
    public function an_approve_from_a_stale_scan_tab_does_not_pull_a_delivered_tally_back(): void
    {
        $tally = $this->tally('processing');

        $tab = Livewire::test(ScanTally::class, ['record' => $tally]);

        Tally::whereKey($tally->id)->update(['status' => 'do']);

        $tab->callAction('approve');

        $this->assertSame('do', $tally->fresh()->status);
    }

    /** @test */
    public function approval_cannot_be_undone_once_the_tally_has_a_delivery_order(): void
    {
        $tally = $this->tally('locked', 'ready');
        $this->deliveryOrder($tally, 'Ready');
        // Keadaan balapan: surat jalan sudah ada tetapi tally masih terbaca terkunci.
        Tally::whereKey($tally->id)->update(['status' => 'locked']);

        Livewire::test(ViewTally::class, ['record' => $tally->id])->callAction('unapprove');

        $this->assertSame('locked', $tally->fresh()->status);
    }

    /** @test */
    public function rejecting_barcodes_from_a_stale_approve_tab_is_refused_after_the_delivery_order_moved_on(): void
    {
        $tally = $this->tally('do', 'on_delivery');
        $do = $this->deliveryOrder($tally, 'Ready');

        $kategori = \App\Models\ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $produk = \App\Models\Product::create(['name' => 'SIRLOIN', 'code' => 'MT00100', 'category_id' => $kategori->id, 'structure_type' => 'main', 'is_active' => true]);
        \App\Models\TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => 'BC-1', 'product_id' => $produk->id,
            'warehouse_id' => \App\Models\Warehouse::create(['code' => 'PERUM', 'name' => 'PERUM', 'is_active' => true])->id,
            'grade_id' => \App\Models\Grade::create(['name' => 'CHILL', 'is_active' => true])->id,
            'weight' => 10, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1',
        ]);

        $tab = Livewire::test(ApproveDeliveryOrder::class, ['record' => $do->id]);

        DeliveryOrder::whereKey($do->id)->update(['status' => 'Approved']);

        $tab->callAction('rejections', ['rejected_barcodes' => ['BC-1']]);

        $this->assertSame('Approved', $do->fresh()->status);
        $this->assertDatabaseHas('tally_items', ['barcode' => 'BC-1']);
    }
}
