<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\EditMaterialRequisition;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialRequisition;
use App\Models\MaterialUnit;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Material Request tidak boleh lagi diedit (item, harga, qty) atau dihapus
 * begitu statusnya sudah lewat "Requested" -- terutama begitu PO sudah
 * terbit dari dokumen itu. Sebelum ini halaman `edit` biasa (beda dari
 * Review/Finance Approval yang memang sengaja tetap terbuka di tahapnya
 * masing-masing) sama sekali tidak mengecek status: item/harga/qty bisa
 * diubah bebas, dan dokumennya bisa dihapus, padahal PO yang sudah terbit
 * dari sana tetap menyimpan angka lama dan tidak ikut berubah -- catatan
 * request jadi tidak cocok lagi dengan PO yang sebenarnya, tanpa satu pun
 * peringatan.
 */
class MaterialRequisitionEditLockTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer',
            'username' => 'mr_lock_programmer',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'CV KEMASAN JAYA',
            'address' => 'Bogor',
            'pic' => 'Rudi',
            'top_days' => 30,
        ]);

        $this->material = Material::create([
            'code' => 'MTR001',
            'name' => 'PLASTIK VACUUM',
            'material_category_id' => MaterialCategory::create(['name' => 'KEMASAN'])->id,
            'material_unit_id' => MaterialUnit::create(['name' => 'PCS'])->id,
            'is_active' => true,
        ]);
    }

    protected function makeRequisition(string $status): MaterialRequisition
    {
        $requisition = MaterialRequisition::create([
            'supplier_id' => (\App\Models\Supplier::first() ?? \App\Models\Supplier::create(['name' => 'Supplier Test ' . uniqid(), 'address' => 'Bogor', 'pic' => 'Test', 'top_days' => 30, 'is_active' => true]))->id,
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => $status,
        ]);

        $requisition->items()->create([
            'material_id' => $this->material->id,
            'qty' => 10,
            'price' => 5000,
            'subtotal' => 50000,
        ]);
        $requisition->updateTotalAmount();

        return $requisition;
    }

    /** @test */
    public function it_still_allows_editing_while_the_request_is_requested()
    {
        $requisition = $this->makeRequisition('Requested');

        Livewire::actingAs($this->user)
            ->test(EditMaterialRequisition::class, ['record' => $requisition->id])
            ->assertOk();
    }

    /** @test */
    public function opening_the_edit_page_for_a_request_past_requested_redirects_to_view()
    {
        $requisition = $this->makeRequisition('Pending Finance');

        Livewire::actingAs($this->user)
            ->test(EditMaterialRequisition::class, ['record' => $requisition->id])
            ->assertRedirect(
                \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('view', ['record' => $requisition]),
            );
    }

    /**
     * Lapis kedua: sebuah tab yang sudah terbuka SEBELUM status berubah
     * (mis. dari sesi lain) tidak boleh lolos menyimpan hanya karena
     * mount()-nya sempat lewat saat status masih "Requested".
     *
     * @test
     */
    public function a_tab_already_open_before_the_status_changed_cannot_save_either()
    {
        $requisition = $this->makeRequisition('Requested');

        $test = Livewire::actingAs($this->user)
            ->test(EditMaterialRequisition::class, ['record' => $requisition->id]);

        // Status berubah dari sesi lain SETELAH halaman ini terbuka.
        MaterialRequisition::whereKey($requisition->id)->update(['status' => 'PO Created']);

        $itemsData = $test->get('data.items');
        $key = array_key_first($itemsData);

        $test->set("data.items.$key.price", '999999')
            ->call('save');

        $requisition->refresh();
        $this->assertEquals(5000, $requisition->items()->first()->price, 'Tab basi berhasil menyimpan padahal status sudah berubah.');
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

    /**
     * Kembar dengan penjagaan yang sudah lebih dulu ada di
     * ProductRequisition::generatePurchaseOrder() -- tanpa ini, membuka
     * ulang URL finance-approval untuk request yang PO-nya sudah terbit
     * menerbitkan PO KEDUA tanpa galat apa pun.
     *
     * @test
     */
    public function generating_a_purchase_order_twice_is_rejected()
    {
        $requisition = $this->makeRequisition('PO Created');
        $requisition->generatePurchaseOrder();

        $this->expectException(\RuntimeException::class);

        $requisition->generatePurchaseOrder();
    }
}
