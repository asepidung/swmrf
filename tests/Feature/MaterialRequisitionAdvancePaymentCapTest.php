<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PurchaseMaterialResource\Pages\ViewPurchaseMaterial;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialRequisition;
use App\Models\MaterialUnit;
use App\Models\PurchaseMaterial;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batas uang muka DP pada PO Material, kembar dengan PO Beef.
 *
 * CATATAN LOKASI: form uang muka DIPINDAHKAN dari halaman Finance Approval ke
 * halaman View PO (27-28 Agustus 2026). DP dibayar saat order, dan PO adalah
 * dokumen order itu sendiri. Test ini ikut pindah menargetkan lokasi barunya.
 */
class MaterialRequisitionAdvancePaymentCapTest extends TestCase
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
            'username' => 'mat_cap_programmer',
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

    /** Tagihannya 100 x 15.000 = 1.500.000, supplier default tidak kena is_tax_11. */
    protected function makePurchaseOrder(): PurchaseMaterial
    {
        $requisition = MaterialRequisition::create([
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => 'PO Created',
        ]);

        $requisition->items()->create([
            'material_id' => $this->material->id,
            'qty' => 100,
            'price' => 15000,
            'subtotal' => 1500000,
        ]);

        $requisition->updateTotalAmount();
        $requisition->generatePurchaseOrder();

        return PurchaseMaterial::where('material_requisition_id', $requisition->id)->firstOrFail();
    }

    /** @test */
    public function it_rejects_an_advance_payment_larger_than_the_bill()
    {
        $po = $this->makePurchaseOrder();

        Livewire::actingAs($this->user)
            ->test(ViewPurchaseMaterial::class, ['record' => $po->id])
            ->callAction('pay_down_payment', [
                'payment_date' => now()->toDateString(),
                'method' => SupplierPayment::METHOD_CASH,
                'amount_input' => '2.000.000',
            ])
            ->assertHasActionErrors(['amount_input']);

        $this->assertSame(0, SupplierPayment::count());
    }

    /** @test */
    public function it_rejects_an_advance_payment_of_zero()
    {
        $po = $this->makePurchaseOrder();

        Livewire::actingAs($this->user)
            ->test(ViewPurchaseMaterial::class, ['record' => $po->id])
            ->callAction('pay_down_payment', [
                'payment_date' => now()->toDateString(),
                'method' => SupplierPayment::METHOD_CASH,
                'amount_input' => '0',
            ])
            ->assertHasActionErrors(['amount_input']);

        $this->assertSame(0, SupplierPayment::count());
    }

    /** @test */
    public function it_accepts_an_advance_payment_equal_to_the_bill()
    {
        $po = $this->makePurchaseOrder();

        Livewire::actingAs($this->user)
            ->test(ViewPurchaseMaterial::class, ['record' => $po->id])
            ->callAction('pay_down_payment', [
                'payment_date' => now()->toDateString(),
                'method' => SupplierPayment::METHOD_CASH,
                'amount_input' => '1.500.000',
            ])
            ->assertHasNoActionErrors();

        $this->assertEquals(1500000, SupplierPayment::sum('amount'));
    }

    /** @test */
    public function it_masks_the_payment_field_with_thousand_separators()
    {
        $source = file_get_contents(app_path(
            'Filament/Admin/Resources/PurchaseMaterialResource/Pages/ViewPurchaseMaterial.php',
        ));

        $this->assertStringContainsString('amount_input', $source);
        $this->assertStringContainsString('Intl.NumberFormat("id-ID")', $source);
    }
}
