<?php

namespace Tests\Feature;

use App\Models\BeefStock;
use App\Models\Boning;
use App\Models\BoningItem;
use App\Models\CattleClass;
use App\Models\Costing;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Grade;
use App\Models\GoodsReceiptMaterial;
use App\Models\GoodsReceiptProduct;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialRequisition;
use App\Models\MaterialStock;
use App\Models\MaterialUnit;
use App\Models\MaterialUsage;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\PurchaseCattle;
use App\Models\PurchaseMaterial;
use App\Models\PurchaseMaterialItem;
use App\Models\PurchaseProduct;
use App\Models\PurchaseProductItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Susulan penyisiran batch 7, 17 September 2026.
 *
 * 20 dari 30 route cetak/ekspor di `routes/web.php` SEBELUMNYA tidak punya
 * pemeriksaan izin sama sekali -- siapa pun yang login bisa membuka cetakan
 * apa pun lewat tebak ID, lubang yang sama dengan qc-reports.print/
 * stock-take.print/dkk sebelum diperbaiki di batch-batch sebelumnya. Satu tes
 * per route: pegawai tanpa izin ditolak, yang punya izin lolos.
 *
 * sales-return.label dan sales-return.pdf ditutup susulan di issue #451
 * langkah 5, begitu keputusan Owner soal tampilan uang Sales Return ada;
 * sebelum itu keduanya SENGAJA dikecualikan (tertunda).
 */
class PrintRoutePermissionGuardsTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private MaterialCategory $materialCategory;

    private MaterialUnit $materialUnit;

    private Material $material;

    private ProductCategory $productCategory;

    private Product $product;

    private Grade $grade;

    private Warehouse $warehouse;

    private CustomerSegment $segment;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create(['name' => 'FIXTURE SUPPLIER', 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top_days' => 30]);

        $this->materialCategory = MaterialCategory::create(['name' => 'FIXTURE CATEGORY']);
        $this->materialUnit = MaterialUnit::create(['name' => 'KG']);
        $this->material = Material::create([
            'code' => 'MAT001', 'name' => 'GARAM', 'material_category_id' => $this->materialCategory->id,
            'material_unit_id' => $this->materialUnit->id, 'min_stock' => 0, 'is_active' => true,
        ]);

        $this->productCategory = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $this->productCategory->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);

        $this->segment = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $this->customer = Customer::create([
            'name' => 'FIXTURE CUSTOMER', 'customer_segment_id' => $this->segment->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
    }

    private function employee(): User
    {
        return User::factory()->create(['role' => 'employee', 'is_active' => true]);
    }

    private function grant(User $user, string $permission): User
    {
        $user->permissions()->attach(
            Permission::firstOrCreate(['name' => $permission], ['module_name' => 'Batch7 Fixture', 'description' => $permission])->id
        );

        return $user->fresh();
    }

    /** Menguji satu route: tanpa izin ditolak, dengan izin lolos, memakai record yang sama. */
    private function assertRouteRequiresPermission(string $routeName, array $params, string $permission): void
    {
        $orangLuar = $this->employee();

        $this->actingAs($orangLuar)->get(route($routeName, $params))->assertForbidden();

        $orangLuar = $this->grant($orangLuar, $permission);
        $this->actingAs($orangLuar)->get(route($routeName, $params))->assertSuccessful();
    }

    // =========================================================================
    // Fixture builder per modul
    // =========================================================================

    private function materialRequisition(): MaterialRequisition
    {
        $requisition = MaterialRequisition::create([
            'document_number' => 'MR-FIXTURE-'.uniqid(), 'user_id' => User::factory()->create()->id,
            'due_date' => now()->addWeek()->toDateString(),
        ]);
        $requisition->items()->create([
            'material_id' => $this->material->id, 'qty' => 10, 'price' => 1000, 'subtotal' => 10000,
        ]);

        return $requisition;
    }

    private function purchaseMaterial(): PurchaseMaterial
    {
        $approver = User::factory()->create();
        $requisition = $this->materialRequisition();

        $po = PurchaseMaterial::create([
            'po_number' => 'PO-FIXTURE-'.uniqid(), 'material_requisition_id' => $requisition->id,
            'supplier_id' => $this->supplier->id, 'approved_by' => $approver->id,
            'po_date' => now()->toDateString(), 'total_amount' => 1000000, 'status' => 'pending',
        ]);
        PurchaseMaterialItem::create([
            'purchase_material_id' => $po->id, 'material_id' => $this->material->id,
            'qty' => 100, 'price' => 10000, 'subtotal' => 1000000,
        ]);

        return $po;
    }

    private function goodsReceiptMaterial(): GoodsReceiptMaterial
    {
        $po = $this->purchaseMaterial();

        $gr = GoodsReceiptMaterial::create([
            'purchase_material_id' => $po->id, 'supplier_id' => $this->supplier->id,
            'receive_date' => now()->toDateString(), 'is_locked' => false,
            'created_by' => User::factory()->create()->id,
        ]);
        $gr->items()->create([
            'material_id' => $this->material->id, 'qty_received' => 100, 'price' => 10000, 'subtotal' => 1000000,
        ]);

        return $gr;
    }

    private function productRequisition(): ProductRequisition
    {
        $requisition = ProductRequisition::create([
            'document_number' => 'PR-FIXTURE-'.uniqid(), 'user_id' => User::factory()->create()->id,
            'due_date' => now()->addWeek()->toDateString(),
        ]);
        $requisition->items()->create([
            'product_id' => $this->product->id, 'qty' => 10, 'price' => 100000, 'subtotal' => 1000000,
        ]);

        return $requisition;
    }

    private function purchaseProduct(): PurchaseProduct
    {
        $approver = User::factory()->create();
        $requisition = $this->productRequisition();

        $po = PurchaseProduct::create([
            'product_requisition_id' => $requisition->id, 'po_number' => 'PO-FIXTURE-'.uniqid(),
            'po_date' => now()->toDateString(), 'supplier_id' => $this->supplier->id,
            'approved_by' => $approver->id, 'total_amount' => 2150000, 'status' => 'pending',
        ]);
        PurchaseProductItem::create([
            'purchase_product_id' => $po->id, 'product_id' => $this->product->id,
            'qty' => 10, 'price' => 215000, 'subtotal' => 2150000,
        ]);

        return $po;
    }

    private function goodsReceiptProduct(): GoodsReceiptProduct
    {
        $po = $this->purchaseProduct();

        $gr = GoodsReceiptProduct::create([
            'purchase_product_id' => $po->id, 'supplier_id' => $this->supplier->id,
            'receive_date' => now()->toDateString(), 'is_locked' => false,
            'created_by' => User::factory()->create()->id,
        ]);
        $gr->items()->create([
            'product_id' => $this->product->id,
            'barcode' => '7'.now()->format('dmy').'1001001'.'2250'.'0800'.'0001',
            'grade_id' => $this->grade->id, 'weight' => 1, 'qty_pcs' => 1,
            'pack_date' => now()->toDateString(), 'origin' => 'GR-BEEF',
            'price' => 215000, 'subtotal' => 215000,
        ]);

        return $gr;
    }

    private function purchaseCattle(): PurchaseCattle
    {
        return PurchaseCattle::create([
            'supplier_id' => $this->supplier->id, 'shipping_date' => now()->toDateString(),
            'created_by' => User::factory()->create()->id,
        ]);
    }

    private function cattleReceiving(): CattleReceiving
    {
        $po = $this->purchaseCattle();
        $class = CattleClass::create(['name' => 'BALI', 'is_active' => true]);

        $receiving = CattleReceiving::create([
            'purchase_cattle_id' => $po->id, 'supplier_id' => $this->supplier->id,
            'receive_date' => now()->toDateString(), 'created_by' => User::factory()->create()->id,
        ]);
        $receiving->items()->create([
            'cattle_class_id' => $class->id, 'eartag' => 'TAG-001', 'initial_weight' => 500,
        ]);

        return $receiving;
    }

    private function cattleWeighing(): CattleWeighing
    {
        $receiving = $this->cattleReceiving();
        $receivingItem = $receiving->items()->first();

        $weighing = CattleWeighing::create([
            'cattle_receiving_id' => $receiving->id, 'weighing_date' => now()->toDateString(),
            'created_by' => User::factory()->create()->id,
        ]);
        $weighing->items()->create([
            'cattle_receiving_item_id' => $receivingItem->id, 'cattle_class_id' => $receivingItem->cattle_class_id,
            'eartag' => $receivingItem->eartag, 'initial_weight' => 500, 'actual_weight' => 480,
        ]);

        return $weighing;
    }

    private function boningItem(): BoningItem
    {
        $boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => User::factory()->create()->id]);

        return BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => 12.55, 'qty_pcs' => 3, 'ph_level' => 5.5,
            'pack_date' => now(), 'exp_date' => now()->addMonths(3),
            'barcode' => '1'.now()->format('dmy').'B0011255',
            'created_by' => $boning->created_by,
        ]);
    }

    /** Issue #480 langkah 3: cetakan costing (Mesin HPP). */
    private function costing(): Costing
    {
        $boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => User::factory()->create()->id]);

        return Costing::create([
            'costing_date' => now()->toDateString(), 'boning_id' => $boning->id,
            'purchase_cost' => 1000000, 'total_sales_value' => 1200000,
            'ratio_k' => 0.833333, 'overhead_per_kg' => 3000, 'total_kg' => 100, 'profit' => 50000,
        ]);
    }

    private function beefStock(): BeefStock
    {
        return BeefStock::create([
            'barcode' => '7'.now()->format('dmy').'9999000000', 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => 10.5, 'qty_pcs' => 2, 'pack_date' => now(), 'origin' => 'BONING', 'status' => 'IN_STOCK',
        ]);
    }

    private function priceList(): PriceList
    {
        $group = CustomerGroup::create(['name' => 'BIDADARI', 'top' => 30]);
        $priceList = PriceList::create(['customer_group_id' => $group->id, 'created_by' => User::factory()->create()->id]);
        PriceListItem::create(['price_list_id' => $priceList->id, 'product_id' => $this->product->id, 'price' => 95000]);

        return $priceList;
    }

    private function salesOrder(): SalesOrder
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'po_number' => 'PO-FIXTURE-'.uniqid(), 'shipping_address' => 'Jakarta Barat',
            'created_by' => User::factory()->create()->id,
        ]);
        SalesOrderItem::create([
            'sales_order_id' => $so->id, 'product_id' => $this->product->id,
            'weight' => 15.5, 'price' => 150000, 'discount' => 0,
        ]);

        return $so;
    }

    private function tally(): Tally
    {
        $so = $this->salesOrder();
        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => Tally::STATUS_PROCESSING]);
        $tally->items()->create([
            'barcode' => 'FIXTURE-'.uniqid(), 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => 12.0, 'qty_pcs' => 1, 'pack_date' => now(), 'origin' => 'BONING',
        ]);

        return $tally;
    }

    private function deliveryOrder(): DeliveryOrder
    {
        $tally = $this->tally();

        return DeliveryOrder::create([
            'tally_id' => $tally->id, 'sales_order_id' => $tally->sales_order_id,
            'customer_id' => $this->customer->id, 'delivery_date' => now()->addDay()->format('Y-m-d'),
            'po_number' => 'PO-FIXTURE-'.uniqid(),
        ]);
    }

    private function invoice(): Invoice
    {
        $so = $this->salesOrder();

        $invoice = Invoice::create([
            'customer_id' => $this->customer->id, 'sales_order_id' => $so->id,
            'invoice_date' => now()->toDateString(), 'term_of_payment' => 30, 'status' => '-',
            'subtotal' => 1000000.0, 'total_discount' => 0, 'tax' => 0, 'balance' => 1000000.0,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'product_id' => $this->product->id,
            'weight' => 10.0, 'price' => 100000, 'discount_percent' => 0, 'discount_rp' => 0, 'amount' => 1000000,
        ]);

        return $invoice;
    }

    /** Retur dengan satu item -- issue #451: harus menarik plan Submitted, tidak bisa langsung `SalesReturn::create()`. */
    private function salesReturnItem(): \App\Models\SalesReturnItem
    {
        $plan = \App\Models\SalesReturnPlan::create([
            'plan_date' => now()->toDateString(), 'customer_id' => $this->customer->id,
        ]);
        $plan->items()->create(['product_id' => $this->product->id, 'claimed_weight' => 10]);
        $plan->submit();

        $return = SalesReturn::create([
            'return_date' => now()->toDateString(), 'sales_return_plan_id' => $plan->id,
            'customer_id' => $this->customer->id,
        ]);

        return $return->items()->create([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'barcode' => 'FIXTURE-'.uniqid(),
            'weight' => 10, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'origin' => '1',
        ]);
    }

    private function expenseWithReceiptPhoto(): Expense
    {
        $path = 'expense-receipts/fixture-'.uniqid().'.jpg';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, 'fixture-image-content');

        $category = ExpenseCategory::create(['name' => 'FIXTURE '.uniqid()]);

        return Expense::createReimburse([
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $category->id,
            'recipient_name' => 'fixture',
            'receipt_amount' => 10000,
            'receipt_photo' => $path,
        ]);
    }

    /** @return string ID gabungan yang dipakai view `material_usage_headers` (usageable_type_usageable_id). */
    private function materialUsageHeaderId(): string
    {
        $boning = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => User::factory()->create()->id]);
        MaterialStock::create(['material_id' => $this->material->id, 'qty' => 100]);
        MaterialUsage::create([
            'usageable_type' => Boning::class, 'usageable_id' => $boning->id,
            'material_id' => $this->material->id, 'qty' => 5, 'note' => 'fixture usage',
        ]);

        return Boning::class.'_'.$boning->id;
    }

    // =========================================================================
    // Satu tes per route
    // =========================================================================

    /** @test */
    public function print_material_request_requires_view_material_requisitions(): void
    {
        $record = $this->materialRequisition();
        $this->assertRouteRequiresPermission('print.material-request', ['id' => $record->id], 'view_material_requisitions');
    }

    /** @test */
    public function print_po_material_requires_view_purchase_materials(): void
    {
        $record = $this->purchaseMaterial();
        $this->assertRouteRequiresPermission('print.po-material', ['id' => $record->id], 'view_purchase_materials');
    }

    /** @test */
    public function goods_receipt_material_print_requires_view_gr_materials(): void
    {
        $record = $this->goodsReceiptMaterial();
        $this->assertRouteRequiresPermission('goods-receipt-material.print', ['id' => $record->id], 'view_gr_materials');
    }

    /** @test */
    public function print_product_request_requires_view_product_requisitions(): void
    {
        $record = $this->productRequisition();
        $this->assertRouteRequiresPermission('print.product-request', ['id' => $record->id], 'view_product_requisitions');
    }

    /** @test */
    public function print_po_product_requires_view_purchase_products(): void
    {
        $record = $this->purchaseProduct();
        $this->assertRouteRequiresPermission('print.po-product', ['id' => $record->id], 'view_purchase_products');
    }

    /** @test */
    public function goods_receipt_product_print_requires_view_goods_receipt_products(): void
    {
        $record = $this->goodsReceiptProduct();
        $this->assertRouteRequiresPermission('goods-receipt-product.print', ['id' => $record->id], 'view_goods_receipt_products');
    }

    /** @test */
    public function goods_receipt_product_label_requires_view_goods_receipt_products(): void
    {
        $gr = $this->goodsReceiptProduct();
        $item = $gr->items()->first();
        $this->assertRouteRequiresPermission('goods-receipt-product.label', ['id' => $item->id], 'view_goods_receipt_products');
    }

    /** @test */
    public function po_cattle_print_requires_view_purchase_cattles(): void
    {
        $record = $this->purchaseCattle();
        $this->assertRouteRequiresPermission('po-cattle.print', ['record' => $record->id], 'view_purchase_cattles');
    }

    /** @test */
    public function cattle_receiving_print_requires_view_cattle_receivings(): void
    {
        $record = $this->cattleReceiving();
        $this->assertRouteRequiresPermission('cattle-receiving.print', ['record' => $record->id], 'view_cattle_receivings');
    }

    /** @test */
    public function cattle_weighing_print_requires_view_cattle_weighings(): void
    {
        $record = $this->cattleWeighing();
        $this->assertRouteRequiresPermission('cattle-weighing.print', ['record' => $record->id], 'view_cattle_weighings');
    }

    /** @test */
    public function boning_label_requires_view_bonings(): void
    {
        $item = $this->boningItem();
        $this->assertRouteRequiresPermission('boning.label', ['id' => $item->id], 'view_bonings');
    }

    /** Issue #480 langkah 3: cetakan costing (Mesin HPP). */
    /** @test */
    public function print_costing_requires_view_costings(): void
    {
        $record = $this->costing();
        $this->assertRouteRequiresPermission('print.costing', ['record' => $record->id], 'view_costings');
    }

    /** @test */
    public function beef_stock_label_requires_view_beef_stocks(): void
    {
        $item = $this->beefStock();
        $this->assertRouteRequiresPermission('beef-stock.label', ['id' => $item->id], 'view_beef_stocks');
    }

    /** @test */
    public function print_pricelist_requires_view_price_lists(): void
    {
        $record = $this->priceList();
        $this->assertRouteRequiresPermission('print.pricelist', ['record' => $record->id], 'view_price_lists');
    }

    /** @test */
    public function print_salesorder_requires_view_sales_orders(): void
    {
        $record = $this->salesOrder();
        $this->assertRouteRequiresPermission('print.salesorder', ['record' => $record->id], 'view_sales_orders');
    }

    /** @test */
    public function print_tally_requires_view_tallies(): void
    {
        $record = $this->tally();
        $this->assertRouteRequiresPermission('print.tally', ['record' => $record->id], 'view_tallies');
    }

    /** @test */
    public function print_delivery_order_requires_view_delivery_orders(): void
    {
        $record = $this->deliveryOrder();
        $this->assertRouteRequiresPermission('print.delivery-order', ['record' => $record->id], 'view_delivery_orders');
    }

    /** @test */
    public function tally_item_label_requires_view_tallies(): void
    {
        $tally = $this->tally();
        $item = $tally->items()->first();
        $this->assertRouteRequiresPermission('tally-item.label', ['id' => $item->id], 'view_tallies');
    }

    /** @test */
    public function print_delivery_plan_preview_requires_view_delivery_plans(): void
    {
        // Route ini tidak mengikat record ({record}) -- ia cukup daftar untuk
        // satu tanggal lewat query string, jadi bahkan tanpa DeliveryPlan
        // apa pun yang cocok, gerbangnya harus tetap berjalan lebih dulu.
        $this->assertRouteRequiresPermission(
            'print.delivery-plan.preview',
            ['date' => now()->addDay()->toDateString()],
            'view_delivery_plans'
        );
    }

    /** @test */
    public function print_invoice_requires_view_invoices(): void
    {
        $record = $this->invoice();
        $this->assertRouteRequiresPermission('print.invoice', ['id' => $record->id], 'view_invoices');
    }

    /** @test */
    public function material_usage_print_requires_view_material_usages(): void
    {
        $id = $this->materialUsageHeaderId();
        $this->assertRouteRequiresPermission('material-usage.print', ['id' => $id], 'view_material_usages');
    }

    /** Issue #451 langkah 5: sales-return.label & .pdf ditutup susulan, sebelumnya tertunda. */
    /** @test */
    public function sales_return_label_requires_view_sales_returns(): void
    {
        $item = $this->salesReturnItem();
        $this->assertRouteRequiresPermission('sales-return.label', ['id' => $item->id], 'view_sales_returns');
    }

    /** @test */
    public function sales_return_pdf_requires_view_sales_returns(): void
    {
        $item = $this->salesReturnItem();
        $this->assertRouteRequiresPermission('sales-return.pdf', ['record' => $item->sales_return_id], 'view_sales_returns');
    }

    /** Issue #453 langkah 2: rute privat untuk nota expense yang diunggah. */
    /** @test */
    public function expense_receipt_photo_requires_view_expenses(): void
    {
        $expense = $this->expenseWithReceiptPhoto();
        $this->assertRouteRequiresPermission('expense.receipt-photo', ['expense' => $expense->id], 'view_expenses');
    }

    /** Issue #453 langkah 3: bukti kas keluar. */
    /** @test */
    public function expense_print_requires_view_expenses(): void
    {
        $category = ExpenseCategory::create(['name' => 'FIXTURE '.uniqid()]);
        $expense = Expense::createAdvance([
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $category->id,
            'recipient_name' => 'fixture',
            'advance_amount' => 10000,
        ]);
        $this->assertRouteRequiresPermission('expense.print', ['expense' => $expense->id], 'view_expenses');
    }

    // =========================================================================
    // Penjaga: route cetak/label/pdf/ekspor baru wajib memeriksa izin
    // =========================================================================

    /**
     * Menyapu SELURUH route di dalam grup `web`+`auth` pada `routes/web.php`
     * (satu-satunya grup yang isinya cetakan/ekspor) dan menuntut setiap
     * route memanggil `hasPermission(` -- baik langsung di closure-nya
     * (mayoritas route) maupun di berkas controller yang dirujuknya (tiga
     * route Purchase/Receiving/Weighing Cattle memakai controller
     * single-action, bukan closure).
     *
     * Ini bukan sekadar mendaftar ulang 20 route yang diperbaiki hari ini --
     * kalau ada yang menambah route cetak BARU ke grup ini nanti tanpa
     * mengingat pola ini, tes ini yang menahannya, bukan tinjauan manual.
     *
     * Tidak ada lagi pengecualian -- sales-return.label/sales-return.pdf
     * ditutup di issue #451 langkah 5, satu-satunya yang tersisa dari
     * sapuan batch 7.
     */
    public function test_every_print_route_in_the_web_auth_group_checks_a_permission(): void
    {
        $source = file_get_contents(base_path('routes/web.php'));

        $groupStart = strpos($source, "Route::middleware(['web', 'auth'])->group(function () {");
        $this->assertNotFalse($groupStart, 'Grup web+auth tidak ditemukan -- routes/web.php berubah struktur.');

        $pushSubscriptionsStart = strpos($source, "Route::middleware('auth')->group(function () {");
        $groupBody = substr($source, $groupStart, $pushSubscriptionsStart - $groupStart);

        $pengecualian = [];

        // Peta nama pendek -> FQCN dari `use App\Http\Controllers\X;` di
        // kepala berkas, supaya `POCattlePrintController::class` di dalam
        // route bisa diselesaikan ke kelas yang benar.
        preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+\\\\([A-Za-z0-9_]+));$/m', $source, $useMatches, PREG_SET_ORDER);
        $namespaceMap = [];
        foreach ($useMatches as [, $fqcn, $short]) {
            $namespaceMap[$short] = $fqcn;
        }

        preg_match_all(
            '/Route::get\((.*?)\)->name\(\'([a-zA-Z0-9_.\\-]+)\'\);/s',
            $groupBody,
            $matches,
            PREG_SET_ORDER
        );

        $this->assertGreaterThanOrEqual(28, count($matches), 'Jumlah route yang tersapu jauh lebih sedikit dari yang diharapkan -- regex mungkin tidak lagi cocok dengan bentuk berkasnya.');

        $diperiksa = 0;

        foreach ($matches as [$fullMatch, $body, $name]) {
            if (in_array($name, $pengecualian, true)) {
                continue;
            }

            $diperiksa++;

            if (preg_match('/([A-Za-z0-9_]+Controller)::class/', $body, $controllerMatch)) {
                $short = $controllerMatch[1];
                $class = $namespaceMap[$short] ?? $short;
                $this->assertTrue(class_exists($class), "Controller $class dirujuk route '$name' tapi tidak ditemukan.");

                $file = (new \ReflectionClass($class))->getFileName();
                $controllerSource = file_get_contents($file);

                $this->assertStringContainsString(
                    'hasPermission(',
                    $controllerSource,
                    "Route '$name' memakai controller $class yang tidak memeriksa hasPermission()."
                );

                continue;
            }

            $this->assertStringContainsString(
                'hasPermission(',
                $body,
                "Route '$name' tidak memeriksa hasPermission() -- cetakan/ekspor baru wajib menjaga modulnya."
            );
        }

        // 34 sejak issue #480 langkah 3 menambah print.costing; 33 sejak
        // issue #453 langkah 3 menambah expense.print; 32 sejak langkah 2
        // menambah expense.receipt-photo; 31 sejak issue #451 menambah
        // sales-return-plan.print; tidak ada lagi pengecualian sejak
        // sales-return.label/.pdf ditutup di langkah 5.
        $this->assertSame(34 - count($pengecualian), $diperiksa, 'Jumlah route yang benar-benar diperiksa tidak sesuai dugaan -- periksa apakah ada route baru yang perlu ditangani atau dikecualikan secara sadar.');
    }
}
