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

    /**
     * GlobalTaskPoller sengaja DIBISUKAN, bukan sekadar tidak dipakai.
     *
     * Seluruh toast lintas-pengguna dihapus atas keputusan Project Owner
     * (27 Agustus 2026) karena tugas itu sudah sepenuhnya diambil alih Web
     * Push PWA. Membiarkan keduanya hidup membuat pemberitahuan yang sama
     * muncul dua kali lewat jalur berbeda.
     *
     * Test lama di sini menyetel properti checkpoint (`lastPurchaseMaterialCheckAt`
     * dan kawan-kawan) yang kini sudah tidak ada, sehingga melempar
     * PublicPropertyNotFoundException. Diganti dengan penjagaan atas
     * keputusannya: poller boleh tetap ada, tapi tidak boleh lagi
     * memancarkan notifikasi apa pun.
     *
     * @test
     */
    public function the_poller_no_longer_broadcasts_cross_user_toasts()
    {
        $this->actingAs($this->programmer);

        Livewire::test(GlobalTaskPoller::class)
            ->call('checkTasks')
            ->assertOk();

        $this->assertSame(
            [],
            session('filament.notifications') ?? [],
            'GlobalTaskPoller masih memancarkan toast, padahal tugasnya sudah diambil alih Web Push.',
        );

        // Properti checkpoint lama tidak boleh hidup lagi -- keberadaannya
        // menandakan logika polling diam-diam dihidupkan kembali.
        $reflection = new \ReflectionClass(GlobalTaskPoller::class);
        $publicProperties = array_map(
            fn (\ReflectionProperty $property) => $property->getName(),
            $reflection->getProperties(\ReflectionProperty::IS_PUBLIC),
        );

        foreach ($publicProperties as $name) {
            $this->assertStringNotContainsString(
                'CheckAt',
                $name,
                "Properti polling '{$name}' hidup lagi di GlobalTaskPoller.",
            );
        }
    }
}
