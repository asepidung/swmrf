<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\ApproveFinanceMaterialRequisition;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialRequisition;
use App\Models\MaterialUnit;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sama seperti Request Beef (#99), modal pembayaran Material tadinya tidak
 * punya pemisah ribuan maupun batas atas nilai. Uang muka bisa diisi
 * melebihi tagihan tanpa error apa pun -- kelebihannya baru terasa jauh
 * kemudian, saat utang dihitung dan tidak pernah terpakai habis.
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

    /** Tagihannya 100 x 15.000 = 1.500.000, tanpa pajak (supplier default tidak kena is_tax_11). */
    protected function makeRequisition(): MaterialRequisition
    {
        $requisition = MaterialRequisition::create([
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => 'Pending Finance',
        ]);

        $requisition->items()->create([
            'material_id' => $this->material->id,
            'qty' => 100,
            'price' => 15000,
            'subtotal' => 1500000,
        ]);

        $requisition->updateTotalAmount();

        return $requisition;
    }

    /** @test */
    public function it_rejects_an_advance_payment_larger_than_the_bill()
    {
        $requisition = $this->makeRequisition();

        Livewire::actingAs($this->user)
            ->test(ApproveFinanceMaterialRequisition::class, ['record' => $requisition->id])
            ->callAction('approve', ['payment_amount' => '2.000.000'])
            ->assertHasActionErrors(['payment_amount']);

        $this->assertSame('Pending Finance', $requisition->fresh()->status);
        $this->assertSame(0, SupplierPayment::count());
    }

    /** @test */
    public function it_accepts_an_advance_payment_equal_to_the_bill()
    {
        $requisition = $this->makeRequisition();

        Livewire::actingAs($this->user)
            ->test(ApproveFinanceMaterialRequisition::class, ['record' => $requisition->id])
            ->callAction('approve', ['payment_amount' => '1.500.000', 'payment_method' => 'cash'])
            ->assertHasNoActionErrors();

        $this->assertSame('PO Created', $requisition->fresh()->status);
        $this->assertEquals(1500000, SupplierPayment::sum('amount'));
    }

    /** @test */
    public function it_masks_the_payment_field_with_thousand_separators()
    {
        $source = file_get_contents(app_path(
            'Filament/Admin/Resources/MaterialRequisitionResource/Pages/ApproveFinanceMaterialRequisition.php',
        ));

        $this->assertStringContainsString('->mask(RawJs::make(', $source);
        $this->assertStringContainsString(
            "\$money(\$input, ',', '.', 0)",
            stripslashes($source),
        );
    }
}
