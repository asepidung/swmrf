<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages\ListPendingPoMaterials;
use App\Filament\Admin\Resources\PurchaseMaterialResource\Pages\ViewPurchaseMaterial;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialRequisition;
use App\Models\MaterialUnit;
use App\Models\PurchaseMaterial;
use App\Models\PurchaseMaterialItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * PO-01 dan PO-02: PO yang sudah punya uang muka tidak dibatalkan diam-diam, dan uang muka tidak dicatat untuk PO yang
 * sudah batal.
 */
class PurchaseOrderMoneyGuardTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);

        $this->supplier = Supplier::create(['name' => 'PO SUPPLIER', 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top_days' => 30]);
    }

    private function po(string $status = 'pending'): PurchaseMaterial
    {
        $material = Material::create([
            'code' => 'MAT'.random_int(100, 999), 'name' => 'GARAM '.uniqid(),
            'material_category_id' => MaterialCategory::create(['name' => 'CAT '.uniqid()])->id,
            'material_unit_id' => MaterialUnit::create(['name' => 'KG '.uniqid()])->id, 'min_stock' => 0, 'is_active' => true,
        ]);
        $requisition = MaterialRequisition::create([
            'supplier_id' => $this->supplier->id, 'document_number' => 'MR-'.uniqid(), 'user_id' => $this->user->id,
            'due_date' => now()->addWeek()->toDateString(),
        ]);
        $po = PurchaseMaterial::create([
            'po_number' => 'PO-'.uniqid(), 'material_requisition_id' => $requisition->id, 'supplier_id' => $this->supplier->id,
            'approved_by' => $this->user->id, 'po_date' => now()->toDateString(), 'total_amount' => 1000000, 'status' => $status,
        ]);
        PurchaseMaterialItem::create(['purchase_material_id' => $po->id, 'material_id' => $material->id, 'qty' => 100, 'price' => 10000, 'subtotal' => 1000000]);

        return $po;
    }

    private function downPayment(PurchaseMaterial $po): void
    {
        SupplierPayment::create([
            'supplier_id' => $po->supplier_id, 'source_type' => PurchaseMaterial::class, 'source_id' => $po->id,
            'payment_date' => now()->toDateString(), 'method' => SupplierPayment::METHOD_CASH, 'amount' => 100000,
            'allocated_amount' => 0,
        ]);
    }

    /** @test */
    public function a_po_with_a_down_payment_is_not_cancelled_from_the_pending_list(): void
    {
        $po = $this->po();
        $this->downPayment($po);

        Livewire::test(ListPendingPoMaterials::class)->callTableAction('cancel_po', $po);

        $this->assertSame('pending', $po->fresh()->status);
    }

    /** @test */
    public function a_po_without_money_or_receipts_is_still_cancelled(): void
    {
        $po = $this->po();

        Livewire::test(ListPendingPoMaterials::class)->callTableAction('cancel_po', $po);

        $this->assertSame('canceled', $po->fresh()->status);
    }

    /** @test */
    public function a_down_payment_is_not_recorded_for_a_cancelled_po(): void
    {
        $po = $this->po('canceled');

        Livewire::test(ViewPurchaseMaterial::class, ['record' => $po->id])
            ->callAction('pay_down_payment', [
                'payment_date' => now()->toDateString(), 'method' => SupplierPayment::METHOD_CASH, 'amount_input' => '100.000',
            ]);

        $this->assertSame(0, SupplierPayment::where('source_id', $po->id)->count());
    }
}
