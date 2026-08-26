<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ApproveFinanceProductRequisition;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ListProductRequisitionDetails;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ListProductRequisitions;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ReviewProductRequisition;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\PurchaseProduct;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Empat lubang pada alur Request Beef yang semuanya lolos tanpa error apa pun.
 *
 * Tiga yang pertama satu tema: penjagaan hanya dipasang di tempat yang TERLIHAT
 * -- tombol di halaman View, baris yang punya harga -- sementara jalur lain
 * menuju tahap yang sama dibiarkan terbuka. Yang keempat kesalahan nama relasi
 * yang membuat export Excel gagal fatal.
 */
class RequisitionFlowGuardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer',
            'username' => 'flow_guard_programmer',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'H DONI',
            'address' => 'Bogor',
            'pic' => 'Doni',
            'top_days' => 30,
        ]);

        $category = ProductCategory::create(['name' => 'PRIMARY CUTS', 'prefix' => 1]);

        $this->product = Product::create([
            'name' => 'CUBEROLL',
            'code' => '100100',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
    }

    protected function makeRequisition(string $status, ?User $requester = null): ProductRequisition
    {
        $requisition = ProductRequisition::create([
            'user_id' => ($requester ?? $this->user)->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => $status,
        ]);

        $requisition->items()->create([
            'product_id' => $this->product->id,
            'qty' => 300,
            'price' => 250000,
            'subtotal' => 300 * 250000,
        ]);

        $requisition->updateTotalAmount();

        return $requisition;
    }

    protected function makeEmployee(string $username, array $permissions = []): User
    {
        $employee = User::create([
            'name' => 'Staf Gudang',
            'username' => $username,
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['module_name' => 'Product Requisition', 'description' => $name],
            );

            $employee->permissions()->attach($permission->id);
        }

        return $employee;
    }

    /**
     * Dokumen yang PO-nya sudah terbit tidak boleh menerbitkan PO kedua.
     *
     * Halaman finance-approval dulu tidak memeriksa status sama sekali, jadi
     * cukup membuka ulang URL-nya lalu menekan Approve untuk mendapat PO kedua
     * berikut dokumen uang muka kedua.
     *
     * @test
     */
    public function it_refuses_to_open_finance_approval_once_the_po_exists()
    {
        $requisition = $this->makeRequisition('PO Created');
        $requisition->generatePurchaseOrder();

        Livewire::actingAs($this->user)
            ->test(ApproveFinanceProductRequisition::class, ['record' => $requisition->id])
            ->assertRedirect(ProductRequisitionResource::getUrl('view', ['record' => $requisition]));

        $this->assertSame(1, PurchaseProduct::where('product_requisition_id', $requisition->id)->count());
    }

    /**
     * Lapis kedua di model, supaya penjagaannya tidak bergantung pada satu halaman.
     *
     * @test
     */
    public function it_refuses_to_generate_a_second_purchase_order()
    {
        $requisition = $this->makeRequisition('PO Created');
        $requisition->generatePurchaseOrder();

        $this->expectException(\RuntimeException::class);

        try {
            $requisition->generatePurchaseOrder();
        } finally {
            $this->assertSame(1, PurchaseProduct::where('product_requisition_id', $requisition->id)->count());
        }
    }

    /** @test */
    public function it_refuses_to_open_the_review_page_once_the_document_has_moved_on()
    {
        $requisition = $this->makeRequisition('Pending Finance');

        Livewire::actingAs($this->user)
            ->test(ReviewProductRequisition::class, ['record' => $requisition->id])
            ->assertRedirect(ProductRequisitionResource::getUrl('view', ['record' => $requisition]));

        $this->assertSame('Pending Finance', $requisition->fresh()->status);
    }

    /**
     * Request tanpa barang tidak boleh maju ke Finance.
     *
     * Penjagaan harga hanya memeriksa baris yang punya product_id, sehingga
     * dokumen yang seluruh barisnya dikosongkan lolos begitu saja -- lalu naik
     * ke Finance dengan 0 item dan total 0.
     *
     * @test
     */
    public function it_blocks_purchasing_approval_when_the_request_has_no_items()
    {
        $requisition = $this->makeRequisition('Requested');

        Livewire::actingAs($this->user)
            ->test(ReviewProductRequisition::class, ['record' => $requisition->id])
            ->set('data.items', [])
            ->callAction('approve');

        $this->assertSame('Requested', $requisition->fresh()->status);
    }

    /** @test */
    public function it_blocks_finance_approval_when_the_request_has_no_items()
    {
        $requisition = $this->makeRequisition('Pending Finance');

        Livewire::actingAs($this->user)
            ->test(ApproveFinanceProductRequisition::class, ['record' => $requisition->id])
            ->set('data.items', [])
            ->callAction('approve', ['payment_amount' => 0]);

        $this->assertSame('Pending Finance', $requisition->fresh()->status);
        $this->assertSame(0, PurchaseProduct::where('product_requisition_id', $requisition->id)->count());
    }

    /**
     * Tombol Resubmit HANYA ada di halaman View. Kalau barisnya tidak bisa
     * diklik, jalur "ditolak, perbaiki, ajukan ulang" buntu.
     *
     * @test
     */
    public function it_lets_a_requester_open_their_own_rejected_request()
    {
        $requester = $this->makeEmployee('flow_guard_requester', [
            'view_product_requisitions',
            'create_product_requisitions',
        ]);

        $rejected = $this->makeRequisition('Rejected', $requester);

        $table = Livewire::actingAs($requester)
            ->test(ListProductRequisitions::class)
            ->instance()
            ->getTable();

        $this->assertSame(
            ProductRequisitionResource::getUrl('view', ['record' => $rejected]),
            $table->getRecordUrl($rejected),
        );
    }

    /** @test */
    public function it_lets_purchasing_open_a_rejected_request_as_an_archive()
    {
        $purchasing = $this->makeEmployee('flow_guard_purchasing', [
            'view_product_requisitions',
            'review_product_requisitions',
        ]);

        $rejected = $this->makeRequisition('Rejected');

        $table = Livewire::actingAs($purchasing)
            ->test(ListProductRequisitions::class)
            ->instance()
            ->getTable();

        $this->assertNotNull($table->getRecordUrl($rejected));
    }

    /**
     * Export Excel memanggil relasi bernama "requisition", padahal namanya
     * "productRequisition". Akibatnya bukan kolom kosong, melainkan gagal fatal.
     *
     * @test
     */
    public function it_exports_the_detail_list_to_excel_without_crashing()
    {
        $this->makeRequisition('PO Created');

        Livewire::actingAs($this->user)
            ->test(ListProductRequisitionDetails::class)
            ->callTableAction('excel')
            ->assertHasNoTableActionErrors();
    }

    /** @test */
    public function it_exports_selected_detail_rows_to_excel_without_crashing()
    {
        $requisition = $this->makeRequisition('PO Created');

        Livewire::actingAs($this->user)
            ->test(ListProductRequisitionDetails::class)
            ->callTableBulkAction('excel_bulk', $requisition->items)
            ->assertHasNoTableBulkActionErrors();
    }
}
