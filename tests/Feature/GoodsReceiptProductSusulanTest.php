<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\GoodsReceiptProductResource;
use App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages\InputGoodsReceiptProduct;
use App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages\LabelingGoodsReceiptProduct;
use App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages\ScanGoodsReceiptProduct;
use App\Models\BeefStock;
use App\Models\GoodsReceiptProduct;
use App\Models\GoodsReceiptProductItem;
use App\Models\Grade;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseProduct;
use App\Models\PurchaseProductItem;
use App\Models\Supplier;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran GR Beef, 17 September 2026.
 *
 * ScanGoodsReceiptProduct/LabelingGoodsReceiptProduct sebelumnya sama sekali
 * tidak punya `canAccess()` -- satu-satunya gerbang cuma
 * `view_goods_receipt_products` milik Resource. `lockGr()`/`deleteGr()`/
 * `saveGr()` di InputGoodsReceiptProduct nol pemeriksaan izin sama sekali.
 * Ditambah: barcode ganda (BeefStock tidak ikut dikunci saat label),
 * GR terkunci masih bisa disisipi lewat scan/label, dan stok bisa lahir
 * dengan harga nol.
 */
class GoodsReceiptProductSusulanTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Grade $grade;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
    }

    /**
     * `view_goods_receipt_products` selalu ikut disertakan: halaman ini
     * mewarisi DUA gerbang `canAccess()` berbeda yang berjalan
     * sendiri-sendiri -- milik halaman sendiri (baru ditambahkan di sini,
     * mensyaratkan `edit_goods_receipt_products`) dan milik
     * `GoodsReceiptProductResource` (bawaan Filament, mensyaratkan
     * `view_goods_receipt_products` lewat `canViewAny()`). Keduanya harus
     * lolos bersamaan.
     */
    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (array_unique([...$permissionNames, 'view_goods_receipt_products']) as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'GR Beef', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function grDenganPo(float $price = 150000): GoodsReceiptProduct
    {
        $supplier = Supplier::create(['name' => 'FIXTURE SUPPLIER', 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top_days' => 30]);
        $requisition = \App\Models\ProductRequisition::create([
            'supplier_id' => (\App\Models\Supplier::first() ?? \App\Models\Supplier::create(['name' => 'Supplier Test ' . uniqid(), 'address' => 'Bogor', 'pic' => 'Test', 'top_days' => 30, 'is_active' => true]))->id,
            'document_number' => 'PR-FIXTURE-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'due_date' => now()->addWeek()->toDateString(),
        ]);
        $po = PurchaseProduct::create([
            'product_requisition_id' => $requisition->id,
            'po_number' => 'PO-FIXTURE-'.uniqid(), 'po_date' => now()->toDateString(),
            'supplier_id' => $supplier->id, 'total_amount' => 0, 'status' => 'partial',
        ]);
        PurchaseProductItem::create([
            'purchase_product_id' => $po->id, 'product_id' => $this->product->id,
            'qty' => 100, 'price' => $price, 'subtotal' => $price * 100,
        ]);

        return GoodsReceiptProduct::create([
            'purchase_product_id' => $po->id, 'supplier_id' => $supplier->id,
            'receive_date' => now()->toDateString(),
            'is_locked' => false, 'created_by' => $requisition->user_id,
        ]);
    }

    private function tallyItem(string $barcode): TallyItem
    {
        $segment = \App\Models\CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $customer = \App\Models\Customer::create([
            'name' => 'FIXTURE CUSTOMER', 'customer_segment_id' => $segment->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
        $so = \App\Models\SalesOrder::create([
            'customer_id' => $customer->id, 'delivery_date' => now()->addDay()->format('Y-m-d'),
            'status' => \App\Models\SalesOrder::STATUS_WAITING,
        ]);
        $tally = \App\Models\Tally::create(['sales_order_id' => $so->id, 'status' => \App\Models\Tally::STATUS_PROCESSING]);

        return TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => $barcode, 'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'grade_id' => $this->grade->id,
            'weight' => 10.5, 'qty_pcs' => 1, 'pack_date' => now(), 'origin' => 'BONING',
        ]);
    }

    // =========================================================================
    // canAccess(): dua gerbang, keduanya wajib lolos
    // =========================================================================

    /** @test */
    public function scan_page_is_closed_over_http_without_edit_goods_receipt_products(): void
    {
        $gr = $this->grDenganPo();

        $this->actingAs($this->employee())
            ->get(GoodsReceiptProductResource::getUrl('scan', ['record' => $gr]))
            ->assertForbidden();

        $this->actingAs($this->employee(['edit_goods_receipt_products']))
            ->get(GoodsReceiptProductResource::getUrl('scan', ['record' => $gr]))
            ->assertSuccessful();
    }

    /** @test */
    public function labeling_page_is_closed_over_http_without_edit_goods_receipt_products(): void
    {
        $gr = $this->grDenganPo();

        $this->actingAs($this->employee())
            ->get(GoodsReceiptProductResource::getUrl('labeling', ['record' => $gr]))
            ->assertForbidden();

        $this->actingAs($this->employee(['edit_goods_receipt_products']))
            ->get(GoodsReceiptProductResource::getUrl('labeling', ['record' => $gr]))
            ->assertSuccessful();
    }

    // =========================================================================
    // Lapis kedua: `canAccess()` menjaga pintu, tapi `scan()`/`create()`
    // sendiri adalah method Livewire publik yang bisa dipanggil langsung
    // terlepas dari itu -- keduanya juga menolak sendiri.
    //
    // Tidak disimulasikan lewat Livewire::test() yang menukar user
    // `actingAs()` di tengah sesi: `User::hasPermission()` menyimpan
    // hasilnya di properti PHP, dan menukar objek user di tengah rangkaian
    // `Livewire::test()` merusak checksum snapshot Livewire itu sendiri
    // (bukan mencerminkan perilaku produksi). Batch 2 (BoningSusulanTest)
    // juga tidak mencoba pola itu untuk kasus yang sama -- diperiksa lewat
    // pembacaan sumber, sama seperti pola pada GoodsReceiptWarehouseAndPhTest.
    // =========================================================================

    /** @test */
    public function scan_and_create_reject_from_inside_their_own_body_too(): void
    {
        $scan = file_get_contents(app_path(
            'Filament/Admin/Resources/GoodsReceiptProductResource/Pages/ScanGoodsReceiptProduct.php'
        ));
        $labeling = file_get_contents(app_path(
            'Filament/Admin/Resources/GoodsReceiptProductResource/Pages/LabelingGoodsReceiptProduct.php'
        ));

        foreach (['scan' => $scan, 'create' => $labeling] as $method => $source) {
            $badan = substr($source, strpos($source, "function {$method}("), 800);

            $this->assertStringContainsString(
                "hasPermission('edit_goods_receipt_products')",
                $badan,
                "{$method}() harus memeriksa izinnya sendiri, bukan cuma menumpang canAccess() halaman.",
            );
        }
    }

    // =========================================================================
    // DeleteAction: butuh izin yang sama
    // =========================================================================

    /** @test */
    public function voiding_a_scanned_item_requires_the_permission(): void
    {
        $gr = $this->grDenganPo();
        $item = GoodsReceiptProductItem::create([
            'goods_receipt_product_id' => $gr->id, 'product_id' => $this->product->id,
            'grade_id' => $this->grade->id, 'weight' => 10.0, 'qty_pcs' => 1,
            'barcode' => 'FX-GR-VOID-0001', 'origin' => 'FIXTURE', 'pack_date' => now(), 'price' => 100, 'subtotal' => 1000,
        ]);

        Livewire::actingAs($this->employee(['edit_goods_receipt_products']))
            ->test(ScanGoodsReceiptProduct::class, ['record' => $gr])
            ->mountTableAction('delete', $item->id)
            ->callMountedTableAction();

        $this->assertSoftDeleted('goods_receipt_product_items', ['id' => $item->id]);
    }

    /** @test */
    public function an_item_already_on_a_tally_shows_t_and_loses_its_delete_button(): void
    {
        $gr = $this->grDenganPo();
        $item = GoodsReceiptProductItem::create([
            'goods_receipt_product_id' => $gr->id, 'product_id' => $this->product->id,
            'grade_id' => $this->grade->id, 'weight' => 10.0, 'qty_pcs' => 1,
            'barcode' => 'FX-GR-TALLY-0001', 'origin' => 'FIXTURE', 'pack_date' => now(), 'price' => 100, 'subtotal' => 1000,
        ]);
        $this->tallyItem('FX-GR-TALLY-0001');

        foreach ([ScanGoodsReceiptProduct::class, LabelingGoodsReceiptProduct::class] as $halaman) {
            Livewire::actingAs($this->employee(['edit_goods_receipt_products']))
                ->test($halaman, ['record' => $gr])
                ->assertTableActionVisible('tally_status', $item)
                ->assertTableActionHidden('delete', $item);
        }
    }

    // =========================================================================
    // GR terkunci: scan dan label harus ditolak, bukan menyisip diam-diam
    // =========================================================================

    /** @test */
    public function scanning_after_the_gr_was_locked_from_another_session_creates_nothing(): void
    {
        $gr = $this->grDenganPo();
        $this->tallyItem('FX-GR-LOCKED-SCAN');

        $test = Livewire::actingAs($this->employee(['edit_goods_receipt_products']))
            ->test(ScanGoodsReceiptProduct::class, ['record' => $gr])
            ->set('warehouse_id', $this->warehouse->id);

        GoodsReceiptProduct::whereKey($gr->id)->update(['is_locked' => true]);

        $test->set('barcode', 'FX-GR-LOCKED-SCAN')->call('scan');

        $this->assertSame(0, BeefStock::count());
    }

    /** @test */
    public function labeling_after_the_gr_was_locked_from_another_session_creates_nothing(): void
    {
        $gr = $this->grDenganPo();

        $test = Livewire::actingAs($this->employee(['edit_goods_receipt_products']))
            ->test(LabelingGoodsReceiptProduct::class, ['record' => $gr]);

        GoodsReceiptProduct::whereKey($gr->id)->update(['is_locked' => true]);

        $test->set('data.warehouse_id', $this->warehouse->id)
            ->set('data.origin', '7')
            ->set('data.product_id', $this->product->id)
            ->set('data.grade_id', $this->grade->id)
            ->set('data.qty_pcs_combined', '10.00/1')
            ->set('data.ph_level', 5.5)
            ->call('create');

        $this->assertSame(0, BeefStock::count());
    }

    // =========================================================================
    // Stok tidak boleh lahir tanpa harga
    // =========================================================================

    /** @test */
    public function scanning_a_product_with_no_price_on_the_po_creates_no_stock(): void
    {
        $gr = $this->grDenganPo(price: 0);
        $this->tallyItem('FX-GR-NOPRICE');

        Livewire::actingAs($this->employee(['edit_goods_receipt_products']))
            ->test(ScanGoodsReceiptProduct::class, ['record' => $gr])
            ->set('warehouse_id', $this->warehouse->id)
            ->set('barcode', 'FX-GR-NOPRICE')
            ->call('scan');

        $this->assertSame(0, BeefStock::count());
    }

    // =========================================================================
    // InputGoodsReceiptProduct: lockGr()/deleteGr()/saveGr() cek izin sendiri
    // =========================================================================

    /** @test */
    public function locking_without_the_permission_does_not_lock_or_create_a_payable(): void
    {
        $gr = $this->grDenganPo();

        Livewire::actingAs($this->employee())
            ->test(InputGoodsReceiptProduct::class, ['record' => $gr])
            ->call('lockGr');

        $this->assertFalse((bool) $gr->fresh()->is_locked);
        $this->assertSame(0, Payable::count());
    }

    /** @test */
    public function locking_with_the_permission_locks_and_creates_a_payable(): void
    {
        $gr = $this->grDenganPo();
        GoodsReceiptProductItem::create([
            'goods_receipt_product_id' => $gr->id, 'product_id' => $this->product->id,
            'grade_id' => $this->grade->id, 'weight' => 10.0, 'qty_pcs' => 1,
            'barcode' => 'FX-GR-LOCK-OK', 'origin' => 'FIXTURE', 'pack_date' => now(), 'price' => 150000, 'subtotal' => 1500000,
        ]);

        Livewire::actingAs($this->employee(['lock_goods_receipt_products']))
            ->test(InputGoodsReceiptProduct::class, ['record' => $gr])
            ->call('lockGr');

        $this->assertTrue((bool) $gr->fresh()->is_locked);
        $this->assertSame(1, Payable::count());
    }

    /** @test */
    public function deleting_without_the_permission_does_nothing(): void
    {
        $gr = $this->grDenganPo();

        Livewire::actingAs($this->employee())
            ->test(InputGoodsReceiptProduct::class, ['record' => $gr])
            ->call('deleteGr');

        $this->assertDatabaseHas('goods_receipt_products', ['id' => $gr->id]);
    }

    /** @test */
    public function saving_the_header_without_the_permission_does_nothing(): void
    {
        $gr = $this->grDenganPo();
        $originalSjNumber = $gr->sj_number;

        Livewire::actingAs($this->employee())
            ->test(InputGoodsReceiptProduct::class, ['record' => $gr])
            ->set('data.sj_number', 'SJ-DIUBAH-PAKSA')
            ->call('saveGr');

        $this->assertSame($originalSjNumber, $gr->fresh()->sj_number);
    }

    // =========================================================================
    // Penerimaan terkunci: label/barangnya tidak boleh terhapus (hutang sudah dihitung dari barisnya)
    // =========================================================================

    private function itemDenganStok(GoodsReceiptProduct $gr, string $barcode): GoodsReceiptProductItem
    {
        $item = GoodsReceiptProductItem::create([
            'goods_receipt_product_id' => $gr->id, 'product_id' => $this->product->id,
            'grade_id' => $this->grade->id, 'weight' => 10.0, 'qty_pcs' => 1,
            'barcode' => $barcode, 'origin' => 'FIXTURE', 'pack_date' => now(), 'price' => 100, 'subtotal' => 1000,
        ]);
        BeefStock::create([
            'barcode' => $barcode, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 10.0, 'qty_pcs' => 1, 'pack_date' => now(), 'status' => 'IN_STOCK',
        ]);

        return $item;
    }

    /** @test */
    public function the_labeling_page_redirects_away_once_the_receipt_is_locked(): void
    {
        $gr = $this->grDenganPo();
        $gr->update(['is_locked' => true]);

        Livewire::actingAs($this->employee(['edit_goods_receipt_products']))
            ->test(LabelingGoodsReceiptProduct::class, ['record' => $gr])
            ->assertRedirect();
    }

    /** @test */
    public function a_tab_opened_before_the_lock_cannot_delete_the_item_afterwards(): void
    {
        $gr = $this->grDenganPo();
        $pengguna = $this->employee(['edit_goods_receipt_products']);
        $halamanDanItem = [
            LabelingGoodsReceiptProduct::class => $this->itemDenganStok($gr, 'FX-LOCK-LBL'),
            ScanGoodsReceiptProduct::class => $this->itemDenganStok($gr, 'FX-LOCK-SCN'),
        ];

        // Dua tab dibuka SEBELUM penerimaan dikunci.
        $tab = [];
        foreach ($halamanDanItem as $halaman => $item) {
            $tab[$halaman] = Livewire::actingAs($pengguna)->test($halaman, ['record' => $gr]);
        }

        GoodsReceiptProduct::whereKey($gr->id)->update(['is_locked' => true]);

        foreach ($halamanDanItem as $halaman => $item) {
            $komponen = $tab[$halaman];
            $barcode = $item->barcode;

            try {
                $komponen->callTableAction('delete', $item);
            } catch (\Throwable) {
                // penolakan di dalam aksi; yang dinilai adalah datanya
            }

            $this->assertNotNull(GoodsReceiptProductItem::find($item->id), "{$halaman}: baris terhapus padahal penerimaan terkunci.");
            $this->assertNotNull(BeefStock::where('barcode', $barcode)->first(), "{$halaman}: stok terhapus padahal penerimaan terkunci.");
        }
    }

    /** @test */
    public function a_barcode_on_a_tally_without_a_delivery_order_cannot_be_scanned_as_a_buyback(): void
    {
        $gr = $this->grDenganPo();
        $this->tallyItem('FX-GR-NODO');

        Livewire::actingAs($this->employee(['edit_goods_receipt_products']))
            ->test(ScanGoodsReceiptProduct::class, ['record' => $gr])
            ->set('warehouse_id', $this->warehouse->id)
            ->set('barcode', 'FX-GR-NODO')
            ->call('scan');

        $this->assertSame(0, BeefStock::count());
        $this->assertSame(0, $gr->items()->count());
    }

    /** @test */
    public function deleting_a_receipt_during_a_stock_count_does_not_pull_its_stock_out(): void
    {
        $gr = $this->grDenganPo();
        $item = GoodsReceiptProductItem::create([
            'goods_receipt_product_id' => $gr->id, 'product_id' => $this->product->id,
            'grade_id' => $this->grade->id, 'weight' => 10.0, 'qty_pcs' => 1,
            'barcode' => 'FX-GR-FREEZE-1', 'origin' => 'FIXTURE', 'pack_date' => now(), 'price' => 100, 'subtotal' => 1000,
        ]);
        BeefStock::create([
            'barcode' => 'FX-GR-FREEZE-1', 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 10.0, 'qty_pcs' => 1, 'pack_date' => now(), 'origin' => '1', 'status' => 'IN_STOCK',
        ]);

        \App\Models\StockTake::create([
            'document_number' => 'ST#FREEZE', 'period' => now()->format('Y-m'), 'date' => now()->toDateString(),
            'status' => \App\Models\StockTake::STATUS_IN_PROGRESS, 'created_by' => User::factory()->create()->id,
        ]);

        try {
            $gr->delete();
        } catch (\Throwable) {
            // Ditolak karena opname berjalan: itu yang diharapkan.
        }

        $this->assertDatabaseHas('beef_stocks', ['barcode' => 'FX-GR-FREEZE-1']);
    }

    /** @test */
    public function a_zero_weight_label_is_refused_but_a_normal_one_is_accepted(): void
    {
        $gr = $this->grDenganPo();

        $isi = fn (string $berat) => Livewire::actingAs($this->employee(['edit_goods_receipt_products']))
            ->test(LabelingGoodsReceiptProduct::class, ['record' => $gr])
            ->set('data.warehouse_id', $this->warehouse->id)
            ->set('data.origin', '7')
            ->set('data.product_id', $this->product->id)
            ->set('data.grade_id', $this->grade->id)
            ->set('data.qty_pcs_combined', $berat)
            ->set('data.ph_level', 5.5)
            ->call('create');

        try {
            $isi('0/1');
        } catch (\Throwable $e) {
        }
        $this->assertSame(0, BeefStock::count());

        $isi('10.00/1');
        $this->assertSame(1, BeefStock::count());
    }

    /** @test */
    public function scanning_into_an_unknown_or_inactive_warehouse_is_refused(): void
    {
        $gr = $this->grDenganPo();
        $tally = $this->tallyItem('FX-GR-WH-0001')->tally;
        \App\Models\DeliveryOrder::create([
            'tally_id' => $tally->id, 'sales_order_id' => $tally->sales_order_id, 'customer_id' => $tally->salesOrder->customer_id,
            'delivery_date' => now()->toDateString(), 'status' => 'Ready',
        ]);
        $gudangMati = Warehouse::create(['code' => 'OFF', 'name' => 'GUDANG MATI', 'is_active' => false]);

        $test = Livewire::actingAs($this->employee(['edit_goods_receipt_products']))
            ->test(ScanGoodsReceiptProduct::class, ['record' => $gr]);

        foreach ([999999, $gudangMati->id] as $gudang) {
            try {
                $test->set('warehouse_id', $gudang)->set('barcode', 'FX-GR-WH-0001')->call('scan');
            } catch (\Throwable $e) {
            }
        }
        $this->assertSame(0, BeefStock::count(), 'Gudang tidak dikenal/nonaktif tidak boleh menerima stok.');

        $test->set('warehouse_id', $this->warehouse->id)->set('barcode', 'FX-GR-WH-0001')->call('scan');
        $this->assertSame(1, BeefStock::count());
    }
}
