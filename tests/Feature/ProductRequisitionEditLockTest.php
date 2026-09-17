<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\EditProductRequisition;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kembar dengan MaterialRequisitionEditLockTest -- lihat penjelasan di sana.
 */
class ProductRequisitionEditLockTest extends TestCase
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
            'username' => 'pr_lock_programmer',
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

    protected function makeRequisition(string $status): ProductRequisition
    {
        $requisition = ProductRequisition::create([
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => $status,
        ]);

        $requisition->items()->create([
            'product_id' => $this->product->id,
            'qty' => 300,
            'price' => 250000,
            'subtotal' => 75000000,
        ]);
        $requisition->updateTotalAmount();

        return $requisition;
    }

    /** @test */
    public function it_still_allows_editing_while_the_request_is_requested()
    {
        $requisition = $this->makeRequisition('Requested');

        Livewire::actingAs($this->user)
            ->test(EditProductRequisition::class, ['record' => $requisition->id])
            ->assertOk();
    }

    /** @test */
    public function opening_the_edit_page_for_a_request_past_requested_redirects_to_view()
    {
        $requisition = $this->makeRequisition('Pending Finance');

        Livewire::actingAs($this->user)
            ->test(EditProductRequisition::class, ['record' => $requisition->id])
            ->assertRedirect(
                \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('view', ['record' => $requisition]),
            );
    }

    /** @test */
    public function a_tab_already_open_before_the_status_changed_cannot_save_either()
    {
        $requisition = $this->makeRequisition('Requested');

        $test = Livewire::actingAs($this->user)
            ->test(EditProductRequisition::class, ['record' => $requisition->id]);

        ProductRequisition::whereKey($requisition->id)->update(['status' => 'PO Created']);

        $itemsData = $test->get('data.items');
        $key = array_key_first($itemsData);

        $test->set("data.items.$key.price", '999999')
            ->call('save');

        $requisition->refresh();
        $this->assertEquals(250000, $requisition->items()->first()->price, 'Tab basi berhasil menyimpan padahal status sudah berubah.');
    }

    /** @test */
    public function deleting_a_request_that_already_has_a_purchase_order_is_rejected()
    {
        $requisition = $this->makeRequisition('PO Created');
        $requisition->generatePurchaseOrder();

        $this->expectException(\Exception::class);

        $requisition->delete();
    }

    /** @test */
    public function a_request_without_a_purchase_order_can_still_be_deleted()
    {
        $requisition = $this->makeRequisition('Rejected');

        $requisition->delete();

        $this->assertSoftDeleted($requisition);
    }

    /** @test */
    public function generating_a_purchase_order_twice_is_rejected()
    {
        $requisition = $this->makeRequisition('PO Created');
        $requisition->generatePurchaseOrder();

        $this->expectException(\RuntimeException::class);

        $requisition->generatePurchaseOrder();
    }
}
