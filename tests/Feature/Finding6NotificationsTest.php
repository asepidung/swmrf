<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\MaterialRequisition;
use App\Models\ProductRequisition;
use App\Models\PurchaseMaterial;
use App\Models\PurchaseProduct;
use App\Models\Boning;
use App\Models\Tally;
use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Filament\Admin\Widgets\PendingTaskWidget;
use App\Livewire\GlobalTaskPoller;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Finding6NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $programmer;
    protected User $employee;
    protected Supplier $supplier;
    protected MaterialRequisition $mr;
    protected ProductRequisition $pr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->programmer = User::factory()->create([
            'role' => 'programmer',
            'is_active' => true
        ]);

        $this->employee = User::factory()->create([
            'role' => 'employee',
            'is_active' => true
        ]);

        $this->supplier = Supplier::create([
            'name' => 'SUPPLIER A',
            'address' => 'ADDRESS A',
            'pic' => 'PIC A',
            'phone' => '12345',
            'top_days' => 30,
            'is_tax_11' => false,
            'is_active' => true,
        ]);

        $this->mr = MaterialRequisition::create([
            'document_number' => 'MR-001',
            'user_id' => $this->programmer->id,
            'due_date' => now()->toDateString(),
            'status' => 'Requested',
        ]);

        $this->pr = ProductRequisition::create([
            'document_number' => 'PR-001',
            'user_id' => $this->programmer->id,
            'due_date' => now()->toDateString(),
            'status' => 'Requested',
        ]);
    }

    /** @test */
    public function it_calculates_pending_widget_counts_under_permissions()
    {
        // 1. Goods Receipt Material (GRM)
        PurchaseMaterial::create([
            'po_number' => 'PO-MAT-1',
            'material_requisition_id' => $this->mr->id,
            'supplier_id' => $this->supplier->id,
            'approved_by' => $this->programmer->id,
            'po_date' => now()->toDateString(),
            'status' => 'pending',
        ]);
        PurchaseMaterial::create([
            'po_number' => 'PO-MAT-2',
            'material_requisition_id' => $this->mr->id,
            'supplier_id' => $this->supplier->id,
            'approved_by' => $this->programmer->id,
            'po_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        // 2. Goods Receipt Beef (GRB)
        PurchaseProduct::create([
            'po_number' => 'PO-PRD-1',
            'product_requisition_id' => $this->pr->id,
            'supplier_id' => $this->supplier->id,
            'approved_by' => $this->programmer->id,
            'po_date' => now()->toDateString(),
            'status' => 'partial',
        ]);

        // 3. Boning
        Boning::create([
            'doc_no' => 'BN26001',
            'boning_date' => now()->toDateString(),
            'kunci' => false,
            'status' => 'OPEN',
            'created_by' => $this->programmer->id,
        ]);

        // 4. Delivery Order (DO)
        $segment = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'TEST CUSTOMER',
            'customer_segment_id' => $segment->id,
            'address' => 'Test Address',
            'pic' => 'PIC',
            'phone' => '123',
            'top' => 10,
        ]);
        $so = SalesOrder::create([
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'processing',
            'created_by' => $this->programmer->id,
        ]);
        Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TS#260001',
            'status' => 'locked',
        ]);

        // 5. Delivery Order Receipt
        DeliveryOrder::create([
            'tally_id' => null,
            'sales_order_id' => $so->id,
            'customer_id' => $customer->id,
            'delivery_order_number' => 'SWM-DO#260001',
            'delivery_date' => now()->toDateString(),
            'driver' => 'Driver 1',
            'status' => 'Ready',
        ]);

        $widget = new PendingTaskWidget();

        // Acting as programmer (all permissions granted implicitly)
        $this->actingAs($this->programmer);
        $this->assertEquals(1, $widget->getPendingGrMaterialCount());
        $this->assertEquals(1, $widget->getPendingGrProductCount());
        $this->assertEquals(1, $widget->getPendingBoningLockCount());
        $this->assertEquals(1, $widget->getPendingDeliveryOrderCount());
        $this->assertEquals(1, $widget->getPendingDeliveryReceiptCount());

        // Acting as employee without permissions (all should be 0)
        $this->actingAs($this->employee);
        $this->assertEquals(0, $widget->getPendingGrMaterialCount());
        $this->assertEquals(0, $widget->getPendingGrProductCount());
        $this->assertEquals(0, $widget->getPendingBoningLockCount());
        $this->assertEquals(0, $widget->getPendingDeliveryOrderCount());
        $this->assertEquals(0, $widget->getPendingDeliveryReceiptCount());

        // Grant individual permissions and assert counts increment
        $p1 = Permission::create(['name' => 'create_gr_materials', 'module_name' => 'GR Material']);
        $this->employee->permissions()->attach($p1->id);
        $this->assertEquals(1, $widget->getPendingGrMaterialCount());

        $p2 = Permission::create(['name' => 'create_goods_receipt_products', 'module_name' => 'GR Beef']);
        $this->employee->permissions()->attach($p2->id);
        $this->assertEquals(1, $widget->getPendingGrProductCount());

        $p3 = Permission::create(['name' => 'lock_bonings', 'module_name' => 'Boning']);
        $this->employee->permissions()->attach($p3->id);
        $this->assertEquals(1, $widget->getPendingBoningLockCount());

        $p4 = Permission::create(['name' => 'create_delivery_orders', 'module_name' => 'Delivery Orders']);
        $this->employee->permissions()->attach($p4->id);
        $this->assertEquals(1, $widget->getPendingDeliveryOrderCount());

        $p5 = Permission::create(['name' => 'view_delivery_receipts', 'module_name' => 'Delivery Orders']);
        $this->employee->permissions()->attach($p5->id);
        $this->assertEquals(1, $widget->getPendingDeliveryReceiptCount());
    }

    /** @test */
    public function it_triggers_toast_notifications_via_poller_for_new_records()
    {
        $this->actingAs($this->programmer);

        // Pre-create initial records
        PurchaseMaterial::create([
            'po_number' => 'PO-MAT-INIT',
            'material_requisition_id' => $this->mr->id,
            'supplier_id' => $this->supplier->id,
            'approved_by' => $this->programmer->id,
            'po_date' => now()->toDateString(),
            'status' => 'pending',
        ]);
        PurchaseProduct::create([
            'po_number' => 'PO-PRD-INIT',
            'product_requisition_id' => $this->pr->id,
            'supplier_id' => $this->supplier->id,
            'approved_by' => $this->programmer->id,
            'po_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $segment = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'TEST CUSTOMER',
            'customer_segment_id' => $segment->id,
            'address' => 'Test Address',
            'pic' => 'PIC',
            'phone' => '123',
            'top' => 10,
        ]);
        $so1 = SalesOrder::create([
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'processing',
            'created_by' => $this->programmer->id,
        ]);
        Tally::create([
            'sales_order_id' => $so1->id,
            'tally_number' => 'TS#260000',
            'status' => 'locked',
        ]);

        // Start poller instance (captures current max IDs)
        $poller = Livewire::test(GlobalTaskPoller::class);
        $initialMaterialId = PurchaseMaterial::max('id');
        $initialProductId = PurchaseProduct::max('id');
        $initialTallyId = Tally::where('status', 'locked')->max('id');

        $this->assertEquals($initialMaterialId, $poller->get('lastPurchaseMaterialId'));
        $this->assertEquals($initialProductId, $poller->get('lastPurchaseProductId'));
        $this->assertEquals($initialTallyId, $poller->get('lastLockedTallyId'));

        // Insert new records to trigger poller
        PurchaseMaterial::create([
            'po_number' => 'PO-MAT-NEW',
            'material_requisition_id' => $this->mr->id,
            'supplier_id' => $this->supplier->id,
            'approved_by' => $this->programmer->id,
            'po_date' => now()->toDateString(),
            'status' => 'pending',
        ]);
        PurchaseProduct::create([
            'po_number' => 'PO-PRD-NEW',
            'product_requisition_id' => $this->pr->id,
            'supplier_id' => $this->supplier->id,
            'approved_by' => $this->programmer->id,
            'po_date' => now()->toDateString(),
            'status' => 'pending',
        ]);
        
        $so2 = SalesOrder::create([
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'processing',
            'created_by' => $this->programmer->id,
        ]);
        Tally::create([
            'sales_order_id' => $so2->id,
            'tally_number' => 'TS#260001',
            'status' => 'locked',
        ]);

        // Run checking
        $poller->call('checkTasks');

        // Check that last tracked IDs were updated
        $this->assertEquals(PurchaseMaterial::max('id'), $poller->get('lastPurchaseMaterialId'));
        $this->assertEquals(PurchaseProduct::max('id'), $poller->get('lastPurchaseProductId'));
        $this->assertEquals(Tally::where('status', 'locked')->max('id'), $poller->get('lastLockedTallyId'));
    }
}
