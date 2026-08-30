<?php

namespace Tests\Feature;

use App\Models\GoodsReceiptProduct;
use App\Models\Payable;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\PurchaseProduct;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uang muka (DP) ke supplier.
 *
 * Inti persoalannya: DP dibayar saat ORDER, sementara utang lahir saat barang
 * DITERIMA. Jadi saat DP dicatat, utangnya belum ada.
 *
 * Kalau DP tidak ditelusuri kembali ketika utang terbit, utang akan tercatat
 * sebesar nilai penuh meski sebagian sudah dibayar — kesalahan yang tidak
 * menimbulkan error apa pun dan baru ketahuan saat supplier menagih.
 */
class SupplierAdvancePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Finance',
            'username' => 'finance_dp',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->supplier = Supplier::create([
            'name' => 'H DONI',
            'address' => 'Bogor',
            'pic' => 'Doni',
            'top_days' => 30,
        ]);

        \App\Models\Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $category = ProductCategory::create(['name' => 'PRIMARY CUTS', 'prefix' => 1]);

        $this->product = Product::create([
            'name' => 'CUBEROLL',
            'code' => '100100',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    /** Rantai dokumen lengkap: Request -> PO -> Goods Receipt. */
    protected function buildChain(float $subtotal): GoodsReceiptProduct
    {
        $requisition = ProductRequisition::create([
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => 'PO Created',
        ]);

        $po = PurchaseProduct::create([
            'po_number' => 'PO-PRD-DP',
            'product_requisition_id' => $requisition->id,
            'supplier_id' => $this->supplier->id,
            'approved_by' => $this->user->id,
            'po_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $gr = GoodsReceiptProduct::create([
            'gr_number' => 'GRB-DP-001',
            'purchase_product_id' => $po->id,
            'supplier_id' => $this->supplier->id,
            'receive_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $gr->items()->create([
            'product_id' => $this->product->id,
            'barcode' => '7' . now()->format('dmy') . '100100' . '1' . '2250' . '08' . '00' . '0001',
            'grade_id' => 1,
            'weight' => 1,
            'qty_pcs' => 1,
            'pack_date' => now()->toDateString(),
            'origin' => 'GR-BEEF',
            'price' => $subtotal,
            'subtotal' => $subtotal,
        ]);

        return $gr->fresh('items');
    }

    /**
     * DP yang menempel di REQUEST -- lokasi lama, saat DP masih dicatat pada
     * approve finance. Dipertahankan supaya data lama tetap terpotong.
     */
    protected function recordAdvance(ProductRequisition $requisition, float $amount): SupplierPayment
    {
        return SupplierPayment::create([
            'supplier_id' => $this->supplier->id,
            'source_type' => ProductRequisition::class,
            'source_id' => $requisition->id,
            'payment_date' => now()->toDateString(),
            'method' => SupplierPayment::METHOD_TRANSFER,
            'amount' => $amount,
        ]);
    }

    /**
     * DP yang menempel di PO -- lokasi SEKARANG, sejak form uang muka
     * dipindahkan dari Finance Approval ke halaman View PO.
     */
    protected function recordAdvanceOnPo(PurchaseProduct $po, float $amount): SupplierPayment
    {
        return SupplierPayment::create([
            'supplier_id' => $this->supplier->id,
            'source_type' => PurchaseProduct::class,
            'source_id' => $po->id,
            'payment_date' => now()->toDateString(),
            'method' => SupplierPayment::METHOD_TRANSFER,
            'amount' => $amount,
        ]);
    }

    /** @test */
    public function it_numbers_supplier_payments_sequentially()
    {
        $requisition = ProductRequisition::create([
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => 'PO Created',
        ]);

        $first = $this->recordAdvance($requisition, 1000);
        $second = $this->recordAdvance($requisition, 2000);

        $this->assertStringStartsWith('SP#' . date('y'), $first->payment_number);
        $this->assertNotSame($first->payment_number, $second->payment_number);
    }

    /**
     * Kasus utama: DP mengurangi utang yang baru terbit.
     *
     * @test
     */
    public function it_deducts_an_advance_from_the_payable_created_at_goods_receipt()
    {
        $gr = $this->buildChain(10_000_000);
        $requisition = $gr->purchaseProduct->productRequisition;

        $this->recordAdvance($requisition, 3_000_000);

        $payable = Payable::generateForGoodsReceiptProduct($gr);

        $this->assertEquals(10_000_000, $payable->amount);
        $this->assertEquals(3_000_000, $payable->paid_amount, 'Uang muka wajib mengurangi utang.');
        $this->assertEquals(7_000_000, $payable->balance, 'Sisa utang salah hitung.');
        $this->assertSame('partial', $payable->status);
    }

    /** @test */
    public function it_marks_the_payable_paid_when_the_advance_covers_everything()
    {
        $gr = $this->buildChain(5_000_000);
        $requisition = $gr->purchaseProduct->productRequisition;

        $this->recordAdvance($requisition, 5_000_000);

        $payable = Payable::generateForGoodsReceiptProduct($gr);

        $this->assertEquals(0, $payable->balance);
        $this->assertSame('paid', $payable->status);
    }

    /** @test */
    public function it_leaves_the_payable_untouched_when_no_advance_was_paid()
    {
        $gr = $this->buildChain(4_000_000);

        $payable = Payable::generateForGoodsReceiptProduct($gr);

        $this->assertEquals(0, $payable->paid_amount);
        $this->assertEquals(4_000_000, $payable->balance);
        $this->assertSame('unpaid', $payable->status);
    }

    /**
     * Uang muka tidak boleh terpakai dua kali bila utangnya dihitung ulang,
     * misalnya karena item Goods Receipt berubah.
     *
     * @test
     */
    public function it_never_applies_the_same_advance_twice()
    {
        $gr = $this->buildChain(10_000_000);
        $requisition = $gr->purchaseProduct->productRequisition;
        $advance = $this->recordAdvance($requisition, 3_000_000);

        Payable::generateForGoodsReceiptProduct($gr);
        $payable = Payable::generateForGoodsReceiptProduct($gr->fresh('items'));

        $this->assertEquals(3_000_000, $payable->paid_amount, 'Uang muka terpakai lebih dari sekali.');
        $this->assertEquals(3_000_000, $advance->fresh()->allocated_amount);
        $this->assertTrue($advance->fresh()->isFullyAllocated());
    }

    /**
     * Uang muka yang melebihi nilai utang hanya terpakai sebesar utangnya;
     * sisanya tetap menggantung untuk dokumen berikutnya.
     *
     * @test
     */
    public function it_only_uses_as_much_of_the_advance_as_the_payable_needs()
    {
        $gr = $this->buildChain(2_000_000);
        $requisition = $gr->purchaseProduct->productRequisition;
        $advance = $this->recordAdvance($requisition, 5_000_000);

        $payable = Payable::generateForGoodsReceiptProduct($gr);

        $this->assertEquals(2_000_000, $payable->paid_amount);
        $this->assertEquals(0, $payable->balance);
        $this->assertEquals(3_000_000, $advance->fresh()->unallocated_amount, 'Sisa uang muka wajib tetap menggantung.');
    }

    /**
     * DP yang dibayar di halaman PO wajib terpotong dari utang.
     *
     * Ini yang sempat PUTUS. Uang muka dipindahkan ke halaman PO sehingga
     * tersimpan dengan source_type = PurchaseProduct, sementara Payable masih
     * hanya menelusuri Request. Uang mukanya tidak pernah ketemu, dan utang
     * lahir sebesar nilai penuh seolah belum ada yang dibayar -- tanpa error
     * apa pun, baru ketahuan saat supplier menagih.
     *
     * @test
     */
    public function it_deducts_an_advance_paid_on_the_purchase_order()
    {
        $gr = $this->buildChain(10_000_000);

        $this->recordAdvanceOnPo($gr->purchaseProduct, 4_000_000);

        $payable = Payable::generateForGoodsReceiptProduct($gr);

        $this->assertEquals(4_000_000, $payable->paid_amount, 'DP di halaman PO tidak terpotong dari utang.');
        $this->assertEquals(6_000_000, $payable->balance);
        $this->assertSame('partial', $payable->status);
    }

    /**
     * Dua sumber sekaligus: DP lama di Request dan DP baru di PO.
     *
     * Keduanya harus terpotong. Kalau hanya salah satu yang terbaca, utangnya
     * salah tanpa ada yang menyadarinya.
     *
     * @test
     */
    public function it_deducts_advances_from_both_the_request_and_the_purchase_order()
    {
        $gr = $this->buildChain(10_000_000);

        $this->recordAdvance($gr->purchaseProduct->productRequisition, 3_000_000);
        $this->recordAdvanceOnPo($gr->purchaseProduct, 2_000_000);

        $payable = Payable::generateForGoodsReceiptProduct($gr);

        $this->assertEquals(5_000_000, $payable->paid_amount);
        $this->assertEquals(5_000_000, $payable->balance);
        $this->assertSame('partial', $payable->status);
    }

    /**
     * Gabungan dua sumber pun tidak boleh melebihi nilai utangnya.
     *
     * @test
     */
    public function it_never_lets_combined_advances_exceed_the_payable()
    {
        $gr = $this->buildChain(5_000_000);

        $this->recordAdvance($gr->purchaseProduct->productRequisition, 4_000_000);
        $this->recordAdvanceOnPo($gr->purchaseProduct, 4_000_000);

        $payable = Payable::generateForGoodsReceiptProduct($gr);

        $this->assertEquals(5_000_000, $payable->paid_amount, 'Uang muka terpakai melebihi nilai utang.');
        $this->assertEquals(0, $payable->balance);
        $this->assertSame('paid', $payable->status);

        // Sisa 3 juta harus tetap menggantung, bukan hangus.
        $this->assertEquals(
            3_000_000,
            SupplierPayment::sum('amount') - SupplierPayment::sum('allocated_amount'),
            'Sisa uang muka hangus, seharusnya menggantung menunggu utang berikutnya.',
        );
    }
}
