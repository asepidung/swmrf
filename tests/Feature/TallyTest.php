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
        $this->assertStringStartsWith('TS#', $tally->tally_number);
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
    public function it_approves_tally_and_sets_so_status_to_ready()
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
            ->callAction('approve', [
                'seal_number' => 'SEAL-777',
            ]);

        $tally->refresh();
        $so->refresh();

        $this->assertEquals('locked', $tally->status);
        $this->assertEquals('SEAL-777', $tally->seal_number);
        $this->assertEquals('ready', $so->status);
    }

    /** @test */
    public function it_allows_approving_tally_from_view_page_and_sets_so_status_to_ready()
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
            ->test(\App\Filament\Admin\Resources\TallyResource\Pages\ViewTally::class, ['record' => $tally->id])
            ->callAction('approve', [
                'seal_number' => 'SEAL-VIEW-999',
            ]);

        $tally->refresh();
        $so->refresh();

        $this->assertEquals('locked', $tally->status);
        $this->assertEquals('SEAL-VIEW-999', $tally->seal_number);
        $this->assertEquals('ready', $so->status);
    }

    /** @test */
    public function it_allows_unapproving_tally_and_resets_so_status_to_processing()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'po_number' => 'PO12345',
            'shipping_address' => 'Jakarta Barat',
            'note' => 'Deliver early morning',
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'locked',
            'seal_number' => 'SEAL-777',
        ]);

        // Unapprove the tally from ViewTally page
        Livewire::actingAs($this->user)
            ->test(\App\Filament\Admin\Resources\TallyResource\Pages\ViewTally::class, ['record' => $tally->id])
            ->callAction('unapprove');

        $tally->refresh();
        $so->refresh();

        $this->assertEquals('processing', $tally->status);
        $this->assertEquals('processing', $so->status);
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
    public function it_restricts_approve_tally_action_by_permission()
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
            ->assertActionHidden('approve');

        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => 'lock_tallies'],
            ['module_name' => 'Tallies', 'description' => 'Lock tallies']
        );
        $userWithoutPermission->permissions()->attach($permission->id);

        Livewire::actingAs($userWithoutPermission)
            ->test(ScanTally::class, ['record' => $tally])
            ->assertActionVisible('approve');
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

    /**
     * Pemberitahuan "ada Sales Order baru" tidak lagi lewat toast poller.
     *
     * Seluruh toast lintas-pengguna di GlobalTaskPoller dihapus atas
     * keputusan Project Owner (27 Agustus 2026); tugas itu diambil alih Web
     * Push PWA. Test lama di sini menyetel `lastSalesOrderCheckAt` yang kini
     * sudah tidak ada, sehingga melempar PublicPropertyNotFoundException.
     *
     * Yang tersisa untuk dijaga: membuat Sales Order baru TIDAK boleh lagi
     * memunculkan toast ke layar orang lain.
     *
     * @test
     */
    public function a_new_sales_order_no_longer_raises_a_poller_toast()
    {
        $this->actingAs($this->user);

        SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(3)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'waiting',
        ]);

        Livewire::test(\App\Livewire\GlobalTaskPoller::class)
            ->call('checkTasks')
            ->assertOk();

        $this->assertSame(
            [],
            session('filament.notifications') ?? [],
            'Poller masih memancarkan toast Sales Order, padahal sudah diambil alih Web Push.',
        );
    }

    /** @test */
    public function it_highlights_scanned_items_exceeding_pod_limit()
    {
        $this->actingAs($this->user);

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

        // Scanned item packed 15 days ago
        $itemExceeding = TallyItem::create([
            'tally_id' => $tally->id,
            'barcode' => 'BC_POD_EXCEED',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 12.00,
            'qty_pcs' => 1,
            'pack_date' => now()->subDays(15),
            'origin' => 'BONING',
        ]);

        // Scanned item packed 5 days ago
        $itemOk = TallyItem::create([
            'tally_id' => $tally->id,
            'barcode' => 'BC_POD_OK',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 10.00,
            'qty_pcs' => 1,
            'pack_date' => now()->subDays(5),
            'origin' => 'BONING',
        ]);

        // Set podLimit to 10 days in session
        session(['tally_pod_limit' => 10]);

        $component = Livewire::test(ScanTally::class, ['record' => $tally]);

        // The component's podLimit should be loaded from session
        $this->assertEquals(10, $component->get('podLimit'));

        // Verify pack_date column color logic via the component
        $tableInstance = new \Filament\Tables\Table($component->instance());
        $table = $component->instance()->table($tableInstance);
        $column = $table->getColumn('pack_date');

        $column->record($itemExceeding);
        $colorForExceeding = $column->getColor($itemExceeding->pack_date);

        $column->record($itemOk);
        $colorForOk = $column->getColor($itemOk->pack_date);

        // Change podLimit via component to 20
        $component->set('podLimit', 20);
        $this->assertEquals(20, session('tally_pod_limit'));

        // Now both should be ok (not highlighted in red) - refresh table instance
        $tableInstanceAfter = new \Filament\Tables\Table($component->instance());
        $tableAfter = $component->instance()->table($tableInstanceAfter);
        $columnAfter = $tableAfter->getColumn('pack_date');
        $colorForExceedingAfter = $columnAfter->getColor($itemExceeding);
        $this->assertNotEquals('danger', $colorForExceedingAfter);
    }

    /** @test */
    public function it_requires_pod_limit_when_creating_tally_via_draft_page()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'waiting',
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Filament\Admin\Resources\TallyResource\Pages\DraftTally::class)
            ->callTableAction('process', $so, [
                'pod_limit' => 15,
            ])
            ->assertHasNoTableActionErrors();

        $tally = Tally::where('sales_order_id', $so->id)->first();
        $this->assertNotNull($tally);
        $this->assertEquals('processing', $tally->status);
        $this->assertEquals(15, session('tally_pod_limit'));
    }

    /** @test */
    public function it_updates_pod_limit_in_session()
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

        $component = Livewire::actingAs($this->user)
            ->test(ScanTally::class, ['record' => $tally])
            ->set('podLimit', 5)
            ->assertSet('podLimit', 5);

        $this->assertEquals(5, session('tally_pod_limit'));

        $component->set('podLimit', '')
            ->assertSet('podLimit', '');

        $this->assertNull(session('tally_pod_limit'));
    }

    /** @test */
    public function it_restores_stock_on_bulk_delete_action()
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

        TallyItem::create([
            'tally_id' => $tally->id,
            'barcode' => 'BC_BULK_DEL',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 15.00,
            'qty_pcs' => 1,
            'pack_date' => now(),
            'origin' => 'BONING',
        ]);

        $records = collect([$tally]);
        
        $this->actingAs($this->user);
        
        Livewire::test(\App\Filament\Admin\Resources\TallyResource\Pages\ListTallies::class)
            ->callTableBulkAction('delete', $records);

        $this->assertSoftDeleted('tallies', ['id' => $tally->id]);
        $this->assertDatabaseMissing('tally_items', ['barcode' => 'BC_BULK_DEL']);
        $this->assertDatabaseHas('beef_stocks', [
            'barcode' => 'BC_BULK_DEL',
            'weight' => 15.00,
        ]);
    }

    /** @test */
    public function it_allows_viewing_and_deleting_tally_when_sales_order_is_canceled()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'canceled',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'processing',
        ]);

        TallyItem::create([
            'tally_id' => $tally->id,
            'barcode' => 'BC_CANCEL_TEST',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 10.0,
            'qty_pcs' => 1,
            'pack_date' => now(),
            'origin' => 'BONING',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(\App\Filament\Admin\Resources\TallyResource\Pages\ViewTally::class, ['record' => $tally->id])
            ->assertActionVisible('back')
            ->assertActionHidden('scan')
            ->assertActionVisible('print')
            ->assertActionVisible('delete');

        $component->callAction('delete');

        $this->assertSoftDeleted('tallies', ['id' => $tally->id]);

        $this->assertDatabaseHas('beef_stocks', [
            'barcode' => 'BC_CANCEL_TEST',
            'weight' => 10.0,
        ]);
    }

    /** @test */
    public function it_redirects_to_view_page_from_scan_page_if_tally_is_locked_or_sales_order_is_canceled()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'cancelled',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'processing',
        ]);

        Livewire::actingAs($this->user)
            ->test(ScanTally::class, ['record' => $tally])
            ->assertRedirect(\App\Filament\Admin\Resources\TallyResource::getUrl('view', ['record' => $tally->id]));
    }

    /** @test */
    public function it_can_render_the_print_tally_page()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'po_number' => 'PO_PRINT_TEST',
            'so_number' => 'SO_PRINT_TEST',
            'created_by' => $this->user->id,
            'status' => 'processing',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'processing',
        ]);

        TallyItem::create([
            'tally_id' => $tally->id,
            'barcode' => 'BC_PRINT_TEST',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 15.5,
            'qty_pcs' => 1,
            'pack_date' => now(),
            'origin' => 'BONING',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('print.tally', ['record' => $tally->id]));

        $response->assertStatus(200);
        $response->assertSee('TALY SHEET');
        $response->assertSee($tally->tally_number);
        $response->assertSee('BLACK OWL');
        $response->assertSee('TENDERLOIN BEEF');
        $response->assertSee('15.50');
    }

    /** @test */
    public function it_disables_sales_order_form_when_cancelled()
    {
        // Test with status 'cancelled'
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'cancelled',
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Filament\Admin\Resources\SalesOrderResource\Pages\EditSalesOrder::class, ['record' => $so->id])
            ->assertOk()
            ->assertActionHidden('print')
            ->assertActionHidden('delete')
            ->assertActionVisible('cancel');

        // Test with status 'canceled'
        $so2 = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'canceled',
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Filament\Admin\Resources\SalesOrderResource\Pages\EditSalesOrder::class, ['record' => $so2->id])
            ->assertOk()
            ->assertActionHidden('print')
            ->assertActionHidden('delete')
            ->assertActionVisible('cancel');
    }

    /** @test */
    public function it_allows_relabeling_tally_item_and_updates_barcode()
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

        $item = TallyItem::create([
            'tally_id' => $tally->id,
            'barcode' => '1140626MT00100122500800001',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 22.50,
            'qty_pcs' => 8,
            'ph_level' => null,
            'pack_date' => '2026-06-14',
            'origin' => 'BONING',
        ]);

        // Create the corresponding stock movement
        $movement = \App\Models\BeefStockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'condition' => $this->grade->id,
            'barcode' => '1140626MT00100122500800001',
            'transaction_type' => 'TALLY',
            'reference_document' => $tally->tally_number,
            'weight_in' => 0,
            'weight_out' => 22.50,
            'pcs_in' => 0,
            'pcs_out' => 8,
            'created_by' => $this->user->id,
        ]);

        $newPackDate = '2026-06-15'; // one day later
        
        Livewire::actingAs($this->user)
            ->test(ScanTally::class, ['record' => $tally])
            ->callTableAction('relabel', $item, [
                'pack_date' => $newPackDate,
                'show_exp' => true,
            ]);

        $item->refresh();
        $movement->refresh();

        // Barcode relabel disusun ulang dari data item, bukan disalin dari
        // barcode lama. Susun ekspektasinya dari komponen yang sama supaya
        // strukturnya terbaca dan tidak bisa salah ketik:
        // origin(1) + tanggal(6) + kode produk(6) + grade(1) + berat(4) +
        // pcs(2) + pH(2) + counter(4) = 26 karakter.
        $expectedNewBarcode = '6'                                              // origin: Relabel Tally
            . '150626'                                                          // pack date baru
            . substr($this->product->code, 0, 6)                                // MT00100 -> MT0010
            . $this->grade->id
            . str_pad((string) round(22.50 * 100), 4, '0', STR_PAD_LEFT)        // 2250
            . str_pad('8', 2, '0', STR_PAD_LEFT)                                // 08
            . '00'                                                              // ph_level null
            . str_pad('1', 4, '0', STR_PAD_LEFT);                               // counter pertama

        $this->assertSame(26, strlen($expectedNewBarcode));
        
        $this->assertEquals($expectedNewBarcode, $item->barcode);
        $this->assertEquals($newPackDate, $item->pack_date->format('Y-m-d'));
        
        // Expiry date should be calculated (Chill / A = +3 months)
        $expectedExpDate = \Carbon\Carbon::parse($newPackDate)->addMonths(3)->format('Y-m-d');
        $this->assertEquals($expectedExpDate, $item->exp_date->format('Y-m-d'));

        // Movement should be updated with new barcode
        $this->assertEquals($expectedNewBarcode, $movement->barcode);

        // Verify activity log was NOT created
        $this->assertDatabaseMissing('activity_log', [
            'log_name' => 'tally_item',
        ]);

        // Verify custom TALLY_RELABEL movement was created in beef_stock_movements
        $this->assertDatabaseHas('beef_stock_movements', [
            'transaction_type' => 'TALLY_RELABEL',
            'barcode' => $expectedNewBarcode,
            'note' => "Relabel: 1140626MT00100122500800001 -> {$expectedNewBarcode} (POD: 2026-06-14 -> {$newPackDate})",
        ]);
    }

    /** @test */
    public function it_allows_deleting_tally_from_scan_page_when_processing_and_restores_stock()
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

        $item = TallyItem::create([
            'tally_id' => $tally->id,
            'barcode' => 'BC_SCAN_DEL_1',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 10.0,
            'qty_pcs' => 1,
            'pack_date' => now(),
            'origin' => 'BONING',
        ]);

        // Access ScanTally page and trigger delete
        Livewire::actingAs($this->user)
            ->test(ScanTally::class, ['record' => $tally])
            ->assertActionExists('delete')
            ->callAction('delete');

        // Assert Tally is soft deleted
        $this->assertTrue($tally->fresh()->trashed());

        // Assert TallyItem is deleted
        $this->assertDatabaseMissing('tally_items', ['id' => $item->id]);

        // Assert stock is restored
        $this->assertDatabaseHas('beef_stocks', [
            'barcode' => 'BC_SCAN_DEL_1',
            'weight' => 10.0,
        ]);

        // Assert SO status reverted to waiting
        $so->refresh();
        $this->assertEquals('waiting', $so->status);
    }
}
