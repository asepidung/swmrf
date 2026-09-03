<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\Warehouse;
use App\Models\Grade;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Filament\Admin\Resources\DeliveryOrderResource;
use App\Filament\Admin\Resources\DeliveryOrderResource\Pages\DraftDeliveryOrders;
use App\Filament\Admin\Resources\DeliveryOrderResource\Pages\CreateDeliveryOrder;
use App\Filament\Admin\Resources\DeliveryOrderResource\Pages\ListDeliveryOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Models\DeliveryOrderReceipt;
use App\Models\DeliveryOrderReceiptItem;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;

class DeliveryOrderTest extends TestCase
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
            'name' => 'SATE KHAS SENAYAN',
            'customer_segment_id' => $segment->id,
            'address' => 'Sudirman Jakarta',
            'pic' => 'Budi',
            'phone' => '081299999',
            'top' => 30,
        ]);

        $category = ProductCategory::create([
            'name' => 'MEAT',
            'prefix' => 'MT',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'WAGYU RIBEYE',
            'code' => 'MT00200',
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
    public function it_updates_tally_and_so_status_upon_delivery_order_creation()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'locked',
        ]);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'driver' => 'Joko',
            'police_number' => 'B 1234 CD',
            'status' => 'Ready',
        ]);

        $this->assertDatabaseHas('delivery_orders', [
            'id' => $do->id,
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'driver' => 'Joko',
        ]);

        // Assert auto number is SWM-DO#26xxxx or similar
        $this->assertStringStartsWith('SWM-DO#', $do->delivery_order_number);

        // Assert status changes
        $this->assertEquals('do', $tally->fresh()->status);
        $this->assertEquals('on_delivery', $so->fresh()->status);
    }

    /** @test */
    public function it_can_render_delivery_orders_index_page()
    {
        $this->actingAs($this->user);

        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TS#260001',
            'status' => 'locked',
        ]);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
        ]);

        $response = $this->get(DeliveryOrderResource::getUrl('index'));
        $response->assertStatus(200);

        // Check if row click URL points to edit page
        $expectedUrl = DeliveryOrderResource::getUrl('edit', ['record' => $do]);
        
        $tableInstance = \Filament\Tables\Table::make(new ListDeliveryOrders());
        $actualUrl = DeliveryOrderResource::table($tableInstance)->getRecordUrl($do);
        $this->assertEquals($expectedUrl, $actualUrl);
    }

    /** @test */
    public function it_can_render_draft_delivery_orders_page()
    {
        $this->actingAs($this->user);

        // This checks if /admin/delivery-orders/draft page resolves correctly (prevents 404 route collision)
        $response = $this->get(DeliveryOrderResource::getUrl('draft'));
        $response->assertStatus(200);
    }

    /** @test */
    public function it_renders_draft_delivery_orders_list_via_livewire()
    {
        $this->actingAs($this->user);

        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        // Locked tally, doesn't have DO yet
        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TALLY-XYZ-111',
            'status' => 'locked',
        ]);

        Livewire::test(DraftDeliveryOrders::class)
            ->assertCanSeeTableRecords([$tally])
            ->assertTableActionExists('proses_do');
    }

    /** @test */
    public function it_prefills_create_delivery_order_form_from_tally_and_aggregates_items()
    {
        $this->actingAs($this->user);

        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TALLY-XYZ-111',
            'status' => 'locked',
            'seal_number' => 'SEAL-12345',
        ]);

        TallyItem::create([
            'tally_id' => $tally->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'barcode' => 'BC101',
            'weight' => 12.5,
            'pack_date' => now()->toDateString(),
            'origin' => 'LOCAL',
        ]);

        TallyItem::create([
            'tally_id' => $tally->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'barcode' => 'BC102',
            'weight' => 15.0,
            'pack_date' => now()->toDateString(),
            'origin' => 'LOCAL',
        ]);

        // Access the Create page with tally_id query parameter
        $test = Livewire::withQueryParams(['tally_id' => $tally->id])
            ->test(CreateDeliveryOrder::class);

        $formData = $test->get('data');
        $this->assertEquals($tally->id, $formData['tally_id']);
        $this->assertEquals($so->id, $formData['sales_order_id']);
        $this->assertEquals($this->customer->id, $formData['customer_id']);
        $this->assertEquals('PO-XYZ-789', $formData['po_number']);
        $this->assertEquals('SEAL-12345', $formData['seal_number']);

        // Assert items list is pre-filled and aggregated correctly
        $this->assertCount(1, $formData['items']);
        $firstItem = reset($formData['items']);
        $this->assertEquals($this->product->id, $firstItem['product_id']);
        $this->assertEquals(2, $firstItem['box']);
        $this->assertEquals(27.5, (float)$firstItem['weight']);
    }

    /** @test */
    public function it_redirects_create_delivery_order_page_to_draft_when_no_tally_id_is_provided()
    {
        $this->actingAs($this->user);

        // Access the Create page directly without query params
        Livewire::test(CreateDeliveryOrder::class)
            ->assertRedirect(DeliveryOrderResource::getUrl('draft'));
    }

    /** @test */
    public function it_allows_approving_delivery_order_and_creates_receipt()
    {
        $this->actingAs($this->user);

        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TS#260001',
            'status' => 'locked',
        ]);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'driver' => 'Joko',
            'status' => 'Ready',
        ]);

        $do_item = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'box' => 2,
            'weight' => 20.0,
        ]);

        // Lewat HTTP dulu, karena di sanalah Filament menyerahkan MODEL dan
        // bukan angka id. Livewire::test di bawah menyerahkan angka -- jalur
        // yang tidak pernah dilalui peramban -- dan pernah menutupi 404 yang
        // dialami setiap orang yang membuka halaman ini.
        $this->get('/admin/delivery-orders/'.$do->id.'/approve')->assertSuccessful();

        Livewire::test(\App\Filament\Admin\Resources\DeliveryOrderResource\Pages\ApproveDeliveryOrder::class, [
            'record' => $do,
        ])
            ->call('submit');

        $this->assertEquals('Approved', $do->fresh()->status);
        $this->assertEquals('completed', $so->fresh()->status);

        $receipt = DeliveryOrderReceipt::where('delivery_order_id', $do->id)->first();
        $this->assertNotNull($receipt);
        $this->assertEquals('SWM-REC#260001', $receipt->receipt_number);
        $this->assertEquals(2, $receipt->total_box);
        $this->assertEquals(20.0, $receipt->total_weight);

        $receipt_item = $receipt->items()->first();
        $this->assertNotNull($receipt_item);
        $this->assertEquals($this->product->id, $receipt_item->product_id);
        $this->assertEquals(2, $receipt_item->box);
        $this->assertEquals(20.0, $receipt_item->weight);
    }

    /** @test */
    public function it_allows_unapproving_delivery_order_and_deletes_receipt()
    {
        $this->actingAs($this->user);

        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'processing',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TS#260001',
            'status' => 'do',
        ]);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'driver' => 'Joko',
            'status' => 'Approved',
        ]);

        $so->update(['status' => 'delivered']);

        $receipt = DeliveryOrderReceipt::create([
            'delivery_order_id' => $do->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'receipt_number' => 'SWM-REC#260001',
            'delivery_date' => $do->delivery_date,
            'total_box' => 2,
            'total_weight' => 20.0,
            'status' => 'Approved',
            'created_by' => $this->user->id,
        ]);

        Livewire::test(\App\Filament\Admin\Resources\DeliveryOrderResource\Pages\ViewDeliveryOrder::class, [
            'record' => $do->id,
        ])
            ->callAction('unapprove');

        $this->assertEquals('Ready', $do->fresh()->status);
        $this->assertEquals('on_delivery', $so->fresh()->status);
        $this->assertSoftDeleted('delivery_order_receipts', [
            'id' => $receipt->id,
        ]);
    }



    /** @test */
    public function it_allows_rejections_from_approve_page_restores_stock_and_syncs_do_items()
    {
        $this->actingAs($this->user);

        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'on_delivery',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TS#260001',
            'status' => 'do',
        ]);

        $tallyItem = TallyItem::create([
            'tally_id' => $tally->id,
            'barcode' => '6150626B001112550355001',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 12.5,
            'qty_pcs' => 1,
            'pack_date' => now(),
            'exp_date' => now()->addMonths(3),
            'origin' => 'BONING',
        ]);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'driver' => 'Joko',
            'status' => 'Ready',
            'delivery_order_number' => 'SWM-DO#260001',
        ]);

        $do_item = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'box' => 1,
            'weight' => 12.5,
        ]);

        Livewire::test(\App\Filament\Admin\Resources\DeliveryOrderResource\Pages\ApproveDeliveryOrder::class, [
            'record' => $do,
        ])
            ->callAction('rejections', data: [
                'rejected_barcodes' => [$tallyItem->barcode],
            ]);

        $this->assertDatabaseMissing('tally_items', [
            'id' => $tallyItem->id,
        ]);

        $this->assertDatabaseHas('beef_stocks', [
            'barcode' => $tallyItem->barcode,
            'status' => 'IN_STOCK',
            'note' => 'Tolakan dari DO#SWM-DO#260001',
        ]);

        $this->assertDatabaseHas('beef_stock_movements', [
            'barcode' => $tallyItem->barcode,
            'transaction_type' => 'DO_REJECT',
            'reference_document' => 'SWM-DO#260001',
            'note' => 'Tolakan dari DO#SWM-DO#260001',
        ]);

        $this->assertDatabaseMissing('delivery_order_items', [
            'delivery_order_id' => $do->id,
        ]);
    }

    /** @test */
    public function it_reverts_tally_and_so_status_when_delivery_order_is_deleted()
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'status' => 'locked',
        ]);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'driver' => 'Joko',
            'status' => 'Ready',
        ]);

        // Assert status changed upon creation
        $this->assertEquals('do', $tally->fresh()->status);
        $this->assertEquals('on_delivery', $so->fresh()->status);

        // Delete the DO
        $do->delete();

        // Assert status reverted
        $this->assertEquals('locked', $tally->fresh()->status);
        $this->assertEquals('processing', $so->fresh()->status);
    }

    /** @test */
    public function it_redirects_edit_page_to_view_page_if_delivery_order_is_already_approved()
    {
        $this->actingAs($this->user);

        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TS#260001',
            'status' => 'locked',
        ]);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'driver' => 'Joko',
            'status' => 'Approved',
        ]);

        Livewire::test(\App\Filament\Admin\Resources\DeliveryOrderResource\Pages\EditDeliveryOrder::class, [
            'record' => $do->id,
        ])
            ->assertRedirect(DeliveryOrderResource::getUrl('view', ['record' => $do->id]));
    }

    /** @test */
    public function it_restores_rejected_stock_items_to_tally_on_unapprove()
    {
        $this->actingAs($this->user);

        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'processing',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TS#260001',
            'status' => 'do',
        ]);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'driver' => 'Joko',
            'status' => 'Approved',
            'delivery_order_number' => 'SWM-DO#260001',
        ]);

        // Create a rejected stock item that was returned
        $stock = BeefStock::create([
            'barcode' => '6150626B001112550355001',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id,
            'weight' => 12.5,
            'qty_pcs' => 1,
            'ph_level' => 5.5,
            'pack_date' => now(),
            'exp_date' => now()->addMonths(3),
            'origin' => 'BONING',
            'status' => 'IN_STOCK',
            'note' => 'Tolakan dari DO#SWM-DO#260001',
        ]);

        $receipt = DeliveryOrderReceipt::create([
            'delivery_order_id' => $do->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'receipt_number' => 'SWM-REC#260001',
            'delivery_date' => $do->delivery_date,
            'total_box' => 0,
            'total_weight' => 0.0,
            'status' => 'Approved',
            'created_by' => $this->user->id,
        ]);

        Livewire::test(\App\Filament\Admin\Resources\DeliveryOrderResource\Pages\ViewDeliveryOrder::class, [
            'record' => $do->id,
        ])
            ->callAction('unapprove');

        // Assert stock deleted
        $this->assertDatabaseMissing('beef_stocks', [
            'id' => $stock->id,
        ]);

        // Assert tally item restored
        $this->assertDatabaseHas('tally_items', [
            'tally_id' => $tally->id,
            'barcode' => '6150626B001112550355001',
        ]);

        // Assert DO items synced
        $this->assertDatabaseHas('delivery_order_items', [
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'box' => 1,
            'weight' => 12.5,
        ]);

        // Assert status reverted
        $this->assertEquals('Ready', $do->fresh()->status);
        $this->assertEquals('on_delivery', $so->fresh()->status);
    }

    /** @test */
    public function it_records_financial_loss_on_quantity_adjustment_during_approve()
    {
        $this->actingAs($this->user);

        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TS#260001',
            'status' => 'locked',
        ]);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'driver' => 'Joko',
            'status' => 'Ready',
        ]);

        $do_item = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'box' => 2,
            'weight' => 20.0,
        ]);

        // Mock form submission where received weight is 18.5 (loss of 1.5 Kg)
        Livewire::test(\App\Filament\Admin\Resources\DeliveryOrderResource\Pages\ApproveDeliveryOrder::class, [
            'record' => $do->id,
        ])
            ->set('data.receipt_items', [
                'item-1' => [
                    'product_id' => $this->product->id,
                    'weight' => 18.5,
                    'box' => 2,
                    'notes' => null,
                ]
            ])
            ->call('submit');

        $this->assertEquals('Approved', $do->fresh()->status);

        // Assert financial loss is created
        $this->assertDatabaseHas('financial_losses', [
            'lossable_type' => DeliveryOrder::class,
            'lossable_id' => $do->id,
            'transaction_type' => 'Delivery Order',
            'reference_number' => $do->delivery_order_number,
            'amount' => 0.00,
            'note' => 'Susut Kirim DO: ' . $do->delivery_order_number . ' sebesar 1.50 Kg',
        ]);

        // Now, let's test that unapproving deletes the financial loss record
        Livewire::test(\App\Filament\Admin\Resources\DeliveryOrderResource\Pages\ViewDeliveryOrder::class, [
            'record' => $do->id,
        ])
            ->callAction('unapprove');

        // Assert financial loss is soft deleted
        $this->assertSoftDeleted('financial_losses', [
            'lossable_type' => DeliveryOrder::class,
            'lossable_id' => $do->id,
        ]);
    }

    /** @test */
    public function it_can_render_delivery_orders_detail_list_page()
    {
        $this->actingAs($this->user);

        $so = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        $tally = Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TS#260001',
            'status' => 'locked',
        ]);

        $do = DeliveryOrder::create([
            'tally_id' => $tally->id,
            'sales_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'delivery_date' => now()->format('Y-m-d'),
            'po_number' => 'PO-XYZ-789',
            'driver' => 'Joko',
            'status' => 'Ready',
        ]);

        $do_item = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'box' => 2,
            'weight' => 20.0,
        ]);

        $response = $this->get(DeliveryOrderResource::getUrl('detail-list'));
        $response->assertStatus(200);

        Livewire::test(\App\Filament\Admin\Resources\DeliveryOrderResource\Pages\DeliveryOrderDetailList::class)
            ->assertCanSeeTableRecords([$do_item]);
    }
}
