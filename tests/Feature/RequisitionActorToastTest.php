<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\ApproveFinanceMaterialRequisition as MaterialFinance;
use App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\ReviewMaterialRequisition as MaterialReview;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ApproveFinanceProductRequisition as ProductFinance;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ReviewProductRequisition as ProductReview;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialRequisition;
use App\Models\MaterialUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Keputusan Project Owner, 26 Agustus 2026: toast "Approved successfully" /
 * "PO Generated successfully" / "Returned successfully" / "Rejected
 * successfully" yang dibuat tangan (bukan bawaan Filament) dihapus dari
 * keempat halaman keputusan Request Beef dan Request Material.
 *
 * Push notification sudah memberi tahu ORANG LAIN yang harus bertindak
 * berikutnya; toast buatan tangan ini dianggap berlebihan bagi PELAKU aksinya
 * sendiri. Toast bawaan Filament (mis. "Created" saat submit form) TETAP ada
 * -- yang dihapus hanya yang sengaja ditulis manual di keempat halaman ini.
 */
class RequisitionActorToastTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Programmer',
            'username' => 'toast_programmer',
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
    }

    protected function toastTitles(): array
    {
        return collect(session('filament.notifications') ?? [])->pluck('title')->all();
    }

    protected function makeProductRequisition(string $status): ProductRequisition
    {
        $category = ProductCategory::create(['name' => 'PRIMARY CUTS', 'prefix' => 1]);
        $product = Product::create([
            'name' => 'CUBEROLL',
            'code' => '100100',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        $requisition = ProductRequisition::create([
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => $status,
        ]);

        $requisition->items()->create([
            'product_id' => $product->id,
            'qty' => 300,
            'price' => 250000,
            'subtotal' => 75000000,
        ]);
        $requisition->updateTotalAmount();

        return $requisition;
    }

    protected function makeMaterialRequisition(string $status): MaterialRequisition
    {
        $material = Material::create([
            'code' => 'MTR001',
            'name' => 'PLASTIK VACUUM',
            'material_category_id' => MaterialCategory::create(['name' => 'KEMASAN'])->id,
            'material_unit_id' => MaterialUnit::create(['name' => 'PCS'])->id,
            'is_active' => true,
        ]);

        $requisition = MaterialRequisition::create([
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'due_date' => now()->toDateString(),
            'status' => $status,
        ]);

        $requisition->items()->create([
            'material_id' => $material->id,
            'qty' => 100,
            'price' => 15000,
            'subtotal' => 1500000,
        ]);
        $requisition->updateTotalAmount();

        return $requisition;
    }

    public static function decisionPages(): array
    {
        return [
            'Beef - purchasing approve' => [ProductReview::class, 'makeProductRequisition', 'Requested', 'approve', []],
            'Beef - purchasing reject' => [ProductReview::class, 'makeProductRequisition', 'Requested', 'reject', ['reject_note' => 'salah barang']],
            'Beef - finance approve' => [ProductFinance::class, 'makeProductRequisition', 'Pending Finance', 'approve', ['payment_amount' => 0]],
            'Beef - finance return' => [ProductFinance::class, 'makeProductRequisition', 'Pending Finance', 'reject', ['reject_note' => 'harga tinggi']],
            'Material - purchasing approve' => [MaterialReview::class, 'makeMaterialRequisition', 'Requested', 'approve', []],
            'Material - purchasing reject' => [MaterialReview::class, 'makeMaterialRequisition', 'Requested', 'reject', ['reject_note' => 'salah barang']],
            'Material - finance approve' => [MaterialFinance::class, 'makeMaterialRequisition', 'Pending Finance', 'approve', ['payment_amount' => 0]],
            'Material - finance return' => [MaterialFinance::class, 'makeMaterialRequisition', 'Pending Finance', 'reject', ['reject_note' => 'harga tinggi']],
        ];
    }

    /**
     * @test
     *
     * @dataProvider decisionPages
     */
    public function it_no_longer_shows_a_hand_made_success_toast(
        string $page,
        string $factory,
        string $status,
        string $action,
        array $data,
    ) {
        $requisition = $this->{$factory}($status);

        Livewire::actingAs($this->user)
            ->test($page, ['record' => $requisition->id])
            ->callAction($action, $data);

        $titles = $this->toastTitles();

        foreach (['Approved successfully', 'Rejected successfully', 'PO Generated successfully', 'Returned successfully'] as $removed) {
            $this->assertNotContains(
                $removed,
                $titles,
                "Toast \"$removed\" seharusnya sudah dihapus dari halaman ini.",
            );
        }
    }
}
