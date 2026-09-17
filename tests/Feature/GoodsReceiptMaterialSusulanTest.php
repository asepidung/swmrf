<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages\CreateGoodsReceiptMaterial;
use App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages\EditGoodsReceiptMaterial;
use App\Models\GoodsReceiptMaterial;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialRequisition;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\PurchaseMaterial;
use App\Models\PurchaseMaterialItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran GR Material, 17 September 2026.
 *
 * `CreateGoodsReceiptMaterial` (halaman custom, bukan `CreateRecord`) tidak
 * pernah mengecek `create_gr_materials` -- satu-satunya gerbang cuma
 * `view_gr_materials` milik Resource. GR yang sudah `is_locked` masih bisa
 * dibuka dan diedit selama Payable-nya belum `partial`/`paid`. Nomor GR
 * dibaca-lalu-tulis tanpa kunci, berbeda dari GoodsReceiptProduct yang sudah
 * dipindah ke `DocumentNumber::next()`.
 */
class GoodsReceiptMaterialSusulanTest extends TestCase
{
    use RefreshDatabase;

    private Material $material;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $category = MaterialCategory::create(['name' => 'FIXTURE CATEGORY']);
        $unit = MaterialUnit::create(['name' => 'KG']);
        $this->material = Material::create([
            'code' => 'MAT001', 'name' => 'GARAM', 'material_category_id' => $category->id,
            'material_unit_id' => $unit->id, 'min_stock' => 0, 'is_active' => true,
        ]);
        $this->supplier = Supplier::create(['name' => 'FIXTURE SUPPLIER', 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top_days' => 30]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (array_unique([...$permissionNames, 'view_gr_materials']) as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'GR Material', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function poMaterial(): PurchaseMaterial
    {
        $approver = User::factory()->create();
        $requisition = MaterialRequisition::create([
            'document_number' => 'MR-FIXTURE-'.uniqid(), 'user_id' => $approver->id,
            'due_date' => now()->addWeek()->toDateString(),
        ]);
        $po = PurchaseMaterial::create([
            'po_number' => 'PO-FIXTURE-'.uniqid(), 'material_requisition_id' => $requisition->id,
            'supplier_id' => $this->supplier->id, 'approved_by' => $approver->id,
            'po_date' => now()->toDateString(), 'total_amount' => 1000000, 'status' => 'pending',
        ]);
        PurchaseMaterialItem::create([
            'purchase_material_id' => $po->id, 'material_id' => $this->material->id,
            'qty' => 100, 'price' => 10000, 'subtotal' => 1000000,
        ]);

        return $po;
    }

    private function grMaterial(bool $locked = false): GoodsReceiptMaterial
    {
        $po = $this->poMaterial();

        return GoodsReceiptMaterial::create([
            'purchase_material_id' => $po->id, 'supplier_id' => $this->supplier->id,
            'receive_date' => now()->toDateString(), 'is_locked' => $locked,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    // =========================================================================
    // create_gr_materials: sebelumnya cuma view_gr_materials yang dicek
    // =========================================================================

    /** @test */
    public function create_page_is_closed_over_http_without_create_gr_materials(): void
    {
        $po = $this->poMaterial();

        $this->actingAs($this->employee())
            ->get(route('filament.admin.resources.goods-receipt-materials.create', ['po_id' => $po->id]))
            ->assertForbidden();

        $this->actingAs($this->employee(['create_gr_materials']))
            ->get(route('filament.admin.resources.goods-receipt-materials.create', ['po_id' => $po->id]))
            ->assertSuccessful();
    }

    /** @test */
    public function executing_create_rejects_from_inside_its_own_body_too(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Admin/Resources/GoodsReceiptMaterialResource/Pages/CreateGoodsReceiptMaterial.php'
        ));
        $badan = substr($source, strpos($source, 'function executeCreate('), 500);

        $this->assertStringContainsString("hasPermission('create_gr_materials')", $badan);
    }

    // =========================================================================
    // GR terkunci: tidak bisa diedit sama sekali, bukan cuma tergantung Payable
    // =========================================================================

    /** @test */
    public function opening_the_edit_page_for_a_locked_gr_redirects_away(): void
    {
        $gr = $this->grMaterial(locked: true);

        Livewire::actingAs($this->employee(['edit_gr_materials']))
            ->test(EditGoodsReceiptMaterial::class, ['record' => $gr->getRouteKey()])
            ->assertRedirect(route('filament.admin.resources.goods-receipt-materials.index'));
    }

    /** @test */
    public function saving_a_gr_locked_from_another_session_is_rejected(): void
    {
        $gr = $this->grMaterial(locked: false);

        $test = Livewire::actingAs($this->employee(['edit_gr_materials']))
            ->test(EditGoodsReceiptMaterial::class, ['record' => $gr->getRouteKey()]);

        // Dikunci dari "sesi lain" SETELAH halaman ini dimuat -- meniru tab
        // yang masih terbuka.
        GoodsReceiptMaterial::whereKey($gr->id)->update(['is_locked' => true]);

        $test->set('data.sj_number', 'SJ-DIUBAH-PAKSA')->call('save');

        $this->assertNotSame('SJ-DIUBAH-PAKSA', $gr->fresh()->sj_number);
    }

    // =========================================================================
    // Nomor GR: lewat DocumentNumber::next(), bukan baca-lalu-tulis sendiri
    // =========================================================================

    /** @test */
    public function two_grs_created_back_to_back_never_collide_on_gr_number(): void
    {
        $gr1 = $this->grMaterial();
        $gr2 = $this->grMaterial();

        $this->assertNotSame($gr1->gr_number, $gr2->gr_number);
        $this->assertStringStartsWith('SWM-GRM#', $gr1->gr_number);
    }

    /** @test */
    public function create_page_no_longer_computes_the_gr_number_itself(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Admin/Resources/GoodsReceiptMaterialResource/Pages/CreateGoodsReceiptMaterial.php'
        ));

        $this->assertStringNotContainsString('GoodsReceiptMaterial::latest', $source);
        $this->assertStringNotContainsString("'gr_number' =>", $source);
    }
}
