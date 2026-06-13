<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\BeefStock;
use App\Models\Warehouse;
use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;
use App\Filament\Admin\Resources\TallyResource\Pages\ScanTally;

class TallyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected Product $product;
    protected Warehouse $warehouse;
    protected Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'programmer',
            'is_active' => true
        ]);

        $segment = CustomerSegment::create([
            'name' => 'RETAIL',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'BLACK OWL',
            'customer_segment_id' => $segment->id,
            'address' => 'Ruko PIK',
            'pic' => 'John Doe',
            'phone' => '0812345678',
            'top' => 30,
        ]);

        $category = ProductCategory::create([
            'name' => 'MEAT',
            'prefix' => 'MT',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'TENDERLOIN BEEF',
            'code' => 'MT00100',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'JONGGOL',
            'name' => 'JONGGOL',
            'is_active' => true,
        ]);

        $this->grade = Grade::create([
            'name' => 'CHILL',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_creates_tally_and_changes_so_status_to_processing()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'po_number' => 'PO12345',
            'shipping_address' => 'Jakarta Barat',
            'note' => 'Deliver early morning',
            'created_by' => $this->user->id,
            'status' => 'waiting',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'processing',
        ]);

        $this->assertDatabaseHas('tallies', [
            'sales_order_id' => $so->id,
            'status' => 'processing',
        ]);

        $this->assertNotNull($tally->tally_number);
        $this->assertStringStartsWith('TS-', $tally->tally_number);
    }

    /** @test */
    public function it_scans_barcode_moves_stock_to_tally_items_and_logs_movement()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'po_number' => 'PO12345',
            'shipping_address' => 'Jakarta Barat',
            'note' => 'Deliver early morning',
            'created_by' => $this->user->id,
            'status' => 'waiting',
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $so->id,
            'product_id' => $this->product->id,
            'weight' => 20.0,
            'price' => 150000,
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'processing',
        ]);
        $so->update(['status' => 'processing']);

        // Set up beef stock
        $stock = BeefStock::create([
            'barcode' => 'BC_SCAN_TEST',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 10.50,
            'qty_pcs' => 1,
            'pack_date' => now(),
            'origin' => 'BONING',
            'status' => 'IN_STOCK',
        ]);

        // Scan barcode using Livewire component
        Livewire::actingAs($this->user)
            ->test(ScanTally::class, ['record' => $tally])
            ->set('barcode', 'BC_SCAN_TEST')
            ->call('scan');

        // Assert stock was removed from beef_stocks
        $this->assertDatabaseMissing('beef_stocks', ['barcode' => 'BC_SCAN_TEST']);

        // Assert tally item was created
        $this->assertDatabaseHas('tally_items', [
            'tally_id' => $tally->id,
            'barcode' => 'BC_SCAN_TEST',
            'weight' => 10.50,
        ]);

        // Assert movement log was created
        $this->assertDatabaseHas('beef_stock_movements', [
            'barcode' => 'BC_SCAN_TEST',
            'transaction_type' => 'TALLY',
            'weight_out' => 10.50,
            'reference_document' => $tally->tally_number,
        ]);
    }

    /** @test */
    public function it_restores_stock_and_logs_movement_when_tally_item_is_deleted()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'po_number' => 'PO12345',
            'shipping_address' => 'Jakarta Barat',
            'note' => 'Deliver early morning',
            'created_by' => $this->user->id,
            'status' => 'processing',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'processing',
        ]);

        $tallyItem = TallyItem::create([
            'tally_id' => $tally->id,
            'barcode' => 'BC_UNSCAN_TEST',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 12.00,
            'qty_pcs' => 1,
            'pack_date' => now(),
            'origin' => 'BONING',
        ]);

        // Delete the item (un-scan)
        $tallyItem->delete();

        // Assert stock is restored
        $this->assertDatabaseHas('beef_stocks', [
            'barcode' => 'BC_UNSCAN_TEST',
            'weight' => 12.00,
            'status' => 'IN_STOCK',
        ]);

        // Assert revert movement log exists
        $this->assertDatabaseHas('beef_stock_movements', [
            'barcode' => 'BC_UNSCAN_TEST',
            'transaction_type' => 'TALLY_REVERT',
            'weight_in' => 12.00,
        ]);
    }

    /** @test */
    public function it_restores_all_items_and_reverts_so_status_when_tally_is_deleted()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'po_number' => 'PO12345',
            'shipping_address' => 'Jakarta Barat',
            'note' => 'Deliver early morning',
            'created_by' => $this->user->id,
            'status' => 'processing',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'processing',
        ]);

        TallyItem::create([
            'tally_id' => $tally->id,
            'barcode' => 'BC_TALLY_DEL1',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 15.00,
            'qty_pcs' => 1,
            'pack_date' => now(),
            'origin' => 'BONING',
        ]);

        TallyItem::create([
            'tally_id' => $tally->id,
            'barcode' => 'BC_TALLY_DEL2',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 18.00,
            'qty_pcs' => 2,
            'pack_date' => now(),
            'origin' => 'BONING',
        ]);

        // Delete the tally sheet
        $tally->delete();

        // Assert SO status is waiting again
        $so->refresh();
        $this->assertEquals('waiting', $so->status);

        // Assert both stock items are restored
        $this->assertDatabaseHas('beef_stocks', [
            'barcode' => 'BC_TALLY_DEL1',
            'weight' => 15.00,
        ]);
        $this->assertDatabaseHas('beef_stocks', [
            'barcode' => 'BC_TALLY_DEL2',
            'weight' => 18.00,
        ]);

        // Assert tally items are deleted
        $this->assertDatabaseMissing('tally_items', ['barcode' => 'BC_TALLY_DEL1']);
        $this->assertDatabaseMissing('tally_items', ['barcode' => 'BC_TALLY_DEL2']);
    }

    /** @test */
    public function it_locks_tally_and_sets_so_status_to_prepared()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'po_number' => 'PO12345',
            'shipping_address' => 'Jakarta Barat',
            'note' => 'Deliver early morning',
            'created_by' => $this->user->id,
            'status' => 'processing',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'processing',
        ]);

        Livewire::actingAs($this->user)
            ->test(ScanTally::class, ['record' => $tally])
            ->callAction('lock', [
                'seal_number' => 'SEAL-777',
            ]);

        $tally->refresh();
        $so->refresh();

        $this->assertEquals('locked', $tally->status);
        $this->assertEquals('SEAL-777', $tally->seal_number);
        $this->assertEquals('prepared', $so->status);
    }

    /** @test */
    public function it_restricts_sales_order_deletion_when_processing()
    {
        $soWaiting = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'waiting',
        ]);

        $soProcessing = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'processing',
        ]);

        $policy = new \App\Policies\SalesOrderPolicy();

        // Admin/programmer user has delete_sales_orders permission
        $this->assertTrue($policy->delete($this->user, $soWaiting));
        $this->assertFalse($policy->delete($this->user, $soProcessing));
    }

    /** @test */
    public function it_restricts_lock_tally_action_by_permission()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'processing',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'processing',
        ]);

        $userWithoutPermission = User::factory()->create([
            'role' => 'employee',
            'is_active' => true,
        ]);

        // Give view_tallies and edit_tallies permissions so they can access the ScanTally page
        $viewPermission = \App\Models\Permission::firstOrCreate(
            ['name' => 'view_tallies'],
            ['module_name' => 'Tallies', 'description' => 'View tallies']
        );
        $editPermission = \App\Models\Permission::firstOrCreate(
            ['name' => 'edit_tallies'],
            ['module_name' => 'Tallies', 'description' => 'Edit tallies']
        );
        $userWithoutPermission->permissions()->attach([$viewPermission->id, $editPermission->id]);

        Livewire::actingAs($userWithoutPermission)
            ->test(ScanTally::class, ['record' => $tally])
            ->assertActionHidden('lock');

        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => 'lock_tallies'],
            ['module_name' => 'Tallies', 'description' => 'Lock tallies']
        );
        $userWithoutPermission->permissions()->attach($permission->id);

        Livewire::actingAs($userWithoutPermission)
            ->test(ScanTally::class, ['record' => $tally])
            ->assertActionVisible('lock');
    }

    /** @test */
    public function it_shows_pending_tally_count_on_widget_based_on_permission()
    {
        SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'waiting',
        ]);

        $widget = new \App\Filament\Admin\Widgets\PendingTaskWidget();

        $this->actingAs($this->user);
        $this->assertEquals(1, $widget->getPendingTallyCount());

        $employee = User::factory()->create([
            'role' => 'employee',
            'is_active' => true,
        ]);
        $this->actingAs($employee);
        $this->assertEquals(0, $widget->getPendingTallyCount());

        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => 'create_tallies'],
            ['module_name' => 'Tallies', 'description' => 'Create tallies']
        );
        $employee->permissions()->attach($permission->id);
        $this->assertEquals(1, $widget->getPendingTallyCount());
    }

    /** @test */
    public function it_alerts_user_about_new_sales_orders_via_poller()
    {
        $this->actingAs($this->user);

        SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'waiting',
        ]);

        $poller = Livewire::test(\App\Livewire\GlobalTaskPoller::class);
        
        $latestId = SalesOrder::max('id');
        $this->assertEquals($latestId, $poller->get('lastSalesOrderId'));

        SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(3)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'waiting',
        ]);

        $poller->call('checkTasks');
        $this->assertEquals(SalesOrder::max('id'), $poller->get('lastSalesOrderId'));
    }
}
