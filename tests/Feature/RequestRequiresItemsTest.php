<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\CreateMaterialRequisition;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\CreateProductRequisition;
use App\Models\MaterialRequisition;
use App\Models\ProductRequisition;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pelatihan 28 Oktober 2026: Request tanpa item bisa tersimpan. Halaman membuang baris kosong sebelum validasi,
 * sehingga form berisi satu baris kosong (atau tanpa baris) lolos dengan nol item.
 */
class RequestRequiresItemsTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Programmer', 'username' => 'prog_'.uniqid(), 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'programmer', 'is_active' => true,
        ]));

        $this->supplier = Supplier::create(['name' => 'SUPPLIER A', 'address' => 'Bogor', 'pic' => 'A', 'phone' => '1', 'top_days' => 30, 'is_tax_11' => false, 'is_active' => true]);
    }

    /** @test */
    public function a_material_request_with_only_an_empty_row_is_refused(): void
    {
        Livewire::test(CreateMaterialRequisition::class)
            ->fillForm(['supplier_id' => $this->supplier->id, 'due_date' => now()->toDateString(), 'items' => [['material_id' => null, 'qty' => 0]]])
            ->call('create')
            ->assertHasFormErrors(['items']);

        $this->assertSame(0, MaterialRequisition::count());
    }

    /** @test */
    public function a_material_request_with_no_rows_at_all_is_refused(): void
    {
        Livewire::test(CreateMaterialRequisition::class)
            ->fillForm(['supplier_id' => $this->supplier->id, 'due_date' => now()->toDateString(), 'items' => []])
            ->call('create')
            ->assertHasFormErrors(['items']);

        $this->assertSame(0, MaterialRequisition::count());
    }

    /** @test */
    public function a_beef_request_with_only_an_empty_row_is_refused(): void
    {
        Livewire::test(CreateProductRequisition::class)
            ->fillForm(['supplier_id' => $this->supplier->id, 'due_date' => now()->toDateString(), 'items' => [['product_id' => null, 'qty' => 0]]])
            ->call('create')
            ->assertHasFormErrors(['items']);

        $this->assertSame(0, ProductRequisition::count());
    }

    /** @test */
    public function a_beef_request_with_no_rows_at_all_is_refused(): void
    {
        Livewire::test(CreateProductRequisition::class)
            ->fillForm(['supplier_id' => $this->supplier->id, 'due_date' => now()->toDateString(), 'items' => []])
            ->call('create')
            ->assertHasFormErrors(['items']);

        $this->assertSame(0, ProductRequisition::count());
    }
}
