<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\GradeResource\Pages\EditGrade;
use App\Filament\Admin\Resources\GradeResource\Pages\ListGrades;
use App\Filament\Admin\Resources\SupplierResource;
use App\Filament\Admin\Resources\SupplierResource\Pages\EditSupplier;
use App\Models\CattleReceiving;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Susulan penyisiran Grade & Supplier, 15 September 2026.
 */
class GradeSupplierSusulanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);
    }

    // =====================================================================
    // Temuan 1 -- Delete dari halaman Edit Grade tidak lagi crash
    // =====================================================================

    /** @test */
    public function deleting_a_grade_still_in_use_from_the_edit_page_shows_a_friendly_notification(): void
    {
        $grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'B001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);

        \App\Models\BeefStock::create([
            'barcode' => 'BARCODE-GRADE-MASIH-DIPAKAI',
            'product_id' => $product->id,
            'warehouse_id' => \App\Models\Warehouse::create(['code' => 'JG', 'name' => 'JONGGOL', 'is_active' => true])->id,
            'grade_id' => $grade->id,
            'weight' => 10, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(),
            'origin' => '1', 'status' => 'IN_STOCK',
        ]);

        Livewire::actingAs($this->user)
            ->test(EditGrade::class, ['record' => $grade->getRouteKey()])
            ->callAction('delete')
            ->assertNotified();

        $this->assertDatabaseHas('grades', ['id' => $grade->id]);
    }

    // =====================================================================
    // Temuan 2 -- ekspor PDF Grade
    // =====================================================================

    /** @test */
    public function it_exports_grades_to_pdf_without_crashing(): void
    {
        Grade::create(['name' => 'CHILL', 'is_active' => true]);

        Livewire::actingAs($this->user)
            ->test(ListGrades::class)
            ->callTableAction('pdf')
            ->assertHasNoTableActionErrors();
    }

    // =====================================================================
    // Temuan 3 -- menu Supplier disembunyikan dari yang tidak berhak
    // =====================================================================

    /** @test */
    public function the_supplier_menu_is_hidden_from_a_user_without_view_suppliers(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($employee);

        $this->assertFalse(SupplierResource::shouldRegisterNavigation());
        $this->assertFalse(SupplierResource::canViewAny());
    }

    /** @test */
    public function the_supplier_menu_shows_for_a_holder_of_view_suppliers(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $employee->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_suppliers'], ['module_name' => 'Suppliers', 'description' => 'view_suppliers'])->id
        );
        $this->actingAs($employee->fresh());

        $this->assertTrue(SupplierResource::shouldRegisterNavigation());
        $this->assertTrue(SupplierResource::canViewAny());
    }

    // =====================================================================
    // Temuan 5 -- Supplier dengan riwayat sapi/DP tidak boleh terhapus
    // =====================================================================

    private function supplier(): Supplier
    {
        return Supplier::create([
            'name' => 'H DONI', 'address' => 'Bogor', 'pic' => 'Doni', 'top_days' => 30,
        ]);
    }

    /** @test */
    public function deleting_a_supplier_with_a_cattle_receiving_is_refused(): void
    {
        $supplier = $this->supplier();
        $po = PurchaseCattle::create([
            'document_number' => 'PC-001', 'supplier_id' => $supplier->id,
            'shipping_date' => now()->toDateString(), 'created_by' => $this->user->id,
        ]);
        CattleReceiving::create([
            'receiving_number' => 'CR-001', 'purchase_cattle_id' => $po->id,
            'supplier_id' => $supplier->id, 'receive_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue($supplier->isInUse());

        Livewire::actingAs($this->user)
            ->test(EditSupplier::class, ['record' => $supplier->getRouteKey()])
            ->mountAction('delete')
            ->callMountedAction()
            ->assertNotified();

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseHas('cattle_receivings', ['id' => 1]);
    }

    /** @test */
    public function deleting_a_supplier_with_a_payment_is_refused_and_the_payment_survives(): void
    {
        $supplier = $this->supplier();
        $requisition = ProductRequisition::create([
            'user_id' => $this->user->id, 'supplier_id' => $supplier->id,
            'due_date' => now()->toDateString(), 'status' => 'PO Created',
        ]);
        $payment = SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'source_type' => ProductRequisition::class,
            'source_id' => $requisition->id,
            'payment_date' => now()->toDateString(),
            'method' => SupplierPayment::METHOD_TRANSFER,
            'amount' => 500000,
        ]);

        $this->assertTrue($supplier->isInUse());

        Livewire::actingAs($this->user)
            ->test(EditSupplier::class, ['record' => $supplier->getRouteKey()])
            ->mountAction('delete')
            ->callMountedAction()
            ->assertNotified();

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseHas('supplier_payments', ['id' => $payment->id]);
    }

    /** @test */
    public function a_supplier_with_no_history_can_still_be_deleted(): void
    {
        $supplier = $this->supplier();

        $this->assertFalse($supplier->isInUse());

        Livewire::actingAs($this->user)
            ->test(EditSupplier::class, ['record' => $supplier->getRouteKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    // =====================================================================
    // Temuan 6 -- Supplier tidak punya jejak audit
    // =====================================================================

    /** @test */
    public function creating_a_supplier_is_logged(): void
    {
        $supplier = $this->supplier();

        $this->assertTrue(
            Activity::where('subject_type', Supplier::class)
                ->where('subject_id', $supplier->id)
                ->where('event', 'created')
                ->exists(),
        );
    }

    // =====================================================================
    // Temuan 7 -- Supplier bisa ditemukan di Global Search
    // =====================================================================

    /** @test */
    public function supplier_is_globally_searchable_by_name(): void
    {
        $this->assertSame('name', SupplierResource::getRecordTitleAttribute());
        $this->assertContains('name', SupplierResource::getGloballySearchableAttributes());
    }

    // =====================================================================
    // Temuan 8 -- Supplier nonaktif dikeluarkan dari 3 dropdown pembelian
    // =====================================================================

    private function supplierDropdownOptions(string $createPageClass): array
    {
        $test = Livewire::actingAs($this->user)->test($createPageClass);

        return $test->instance()
            ->getForm('form')
            ->getComponent(fn ($component) => method_exists($component, 'getName') && $component->getName() === 'supplier_id')
            ->getOptions();
    }

    /** @test */
    public function inactive_suppliers_are_excluded_from_material_requisition_dropdown(): void
    {
        $active = $this->supplier();
        $inactive = Supplier::create(['name' => 'PEMASOK MATI', 'address' => 'Bogor', 'pic' => 'X', 'top_days' => 30, 'is_active' => false]);

        $options = $this->supplierDropdownOptions(\App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\CreateMaterialRequisition::class);

        $this->assertArrayHasKey($active->id, $options);
        $this->assertArrayNotHasKey($inactive->id, $options);
    }

    /** @test */
    public function inactive_suppliers_are_excluded_from_product_requisition_dropdown(): void
    {
        $active = $this->supplier();
        $inactive = Supplier::create(['name' => 'PEMASOK MATI', 'address' => 'Bogor', 'pic' => 'X', 'top_days' => 30, 'is_active' => false]);

        $options = $this->supplierDropdownOptions(\App\Filament\Admin\Resources\ProductRequisitionResource\Pages\CreateProductRequisition::class);

        $this->assertArrayHasKey($active->id, $options);
        $this->assertArrayNotHasKey($inactive->id, $options);
    }

    /** @test */
    public function inactive_suppliers_are_excluded_from_purchase_cattle_dropdown(): void
    {
        $active = $this->supplier();
        $inactive = Supplier::create(['name' => 'PEMASOK MATI', 'address' => 'Bogor', 'pic' => 'X', 'top_days' => 30, 'is_active' => false]);

        $options = $this->supplierDropdownOptions(\App\Filament\Admin\Resources\PurchaseCattleResource\Pages\CreatePurchaseCattle::class);

        $this->assertArrayHasKey($active->id, $options);
        $this->assertArrayNotHasKey($inactive->id, $options);
    }
}
