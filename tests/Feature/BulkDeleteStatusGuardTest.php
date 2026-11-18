<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MutationResource\Pages\ListMutations;
use App\Filament\Admin\Resources\SalesOrderResource\Pages\ListSalesOrders;
use App\Filament\Admin\Resources\TallyResource\Pages\ListTallies;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Mutation;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BD-01: hapus massal harus mengikuti aturan hapus satuan (status), bukan hanya izin modul.
 */
class BulkDeleteStatusGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);
    }

    private function salesOrder(string $status, string $po): SalesOrder
    {
        $segment = CustomerSegment::first() ?? CustomerSegment::create(['name' => 'SEG', 'is_active' => true]);
        $customer = Customer::firstOrCreate(['name' => 'BULK CUSTOMER'], [
            'customer_segment_id' => $segment->id, 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);

        return SalesOrder::create([
            'customer_id' => $customer->id, 'delivery_date' => now()->addDay()->format('Y-m-d'),
            'po_number' => $po, 'created_by' => $this->user->id, 'status' => $status,
        ]);
    }

    /** @test */
    public function bulk_delete_of_tallies_only_removes_processing_ones(): void
    {
        $dipakai = Tally::create(['sales_order_id' => $this->salesOrder('ready', 'PO-A')->id, 'status' => 'locked']);
        $terkirim = Tally::create(['sales_order_id' => $this->salesOrder('completed', 'PO-B')->id, 'status' => 'do']);
        $bebas = Tally::create(['sales_order_id' => $this->salesOrder('waiting', 'PO-C')->id, 'status' => 'processing']);

        Livewire::test(ListTallies::class)
            ->callTableBulkAction('delete', [$dipakai->getKey(), $terkirim->getKey(), $bebas->getKey()]);

        $this->assertSoftDeleted('tallies', ['id' => $bebas->id]);
        $this->assertDatabaseHas('tallies', ['id' => $dipakai->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('tallies', ['id' => $terkirim->id, 'deleted_at' => null]);
    }

    /** @test */
    public function bulk_delete_of_sales_orders_only_removes_waiting_or_cancelled_ones(): void
    {
        $siap = $this->salesOrder('ready', 'PO-R');
        $jalan = $this->salesOrder('on_delivery', 'PO-J');
        $menunggu = $this->salesOrder('waiting', 'PO-W');

        Livewire::test(ListSalesOrders::class)
            ->callTableBulkAction('delete', [$siap->getKey(), $jalan->getKey(), $menunggu->getKey()]);

        $this->assertSoftDeleted('sales_orders', ['id' => $menunggu->id]);
        $this->assertDatabaseHas('sales_orders', ['id' => $siap->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('sales_orders', ['id' => $jalan->id, 'deleted_at' => null]);
    }

    /** @test */
    public function bulk_delete_of_mutations_only_removes_empty_drafts(): void
    {
        $dari = Warehouse::create(['code' => 'A', 'name' => 'A', 'is_active' => true]);
        $ke = Warehouse::create(['code' => 'B', 'name' => 'B', 'is_active' => true]);
        $buat = fn (string $status) => Mutation::create([
            'mutation_date' => now()->format('Y-m-d'), 'from_warehouse_id' => $dari->id,
            'to_warehouse_id' => $ke->id, 'status' => $status, 'created_by' => $this->user->id,
        ]);

        $terkirim = $buat('SENT');
        $draftKosong = $buat('DRAFT');

        Livewire::test(ListMutations::class)
            ->callTableBulkAction('delete', [$terkirim->getKey(), $draftKosong->getKey()]);

        $this->assertSoftDeleted('mutations', ['id' => $draftKosong->id]);
        $this->assertDatabaseHas('mutations', ['id' => $terkirim->id, 'deleted_at' => null]);
    }
}
