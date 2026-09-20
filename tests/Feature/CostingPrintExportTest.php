<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CostingResource\Pages\EditCosting;
use App\Filament\Admin\Resources\CostingResource\Pages\ListCostings;
use App\Filament\Admin\Resources\CostingResource\Pages\ViewCosting;
use App\Models\Boning;
use App\Models\CattleClass;
use App\Models\Costing;
use App\Models\CostingItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Langkah 3 dari issue #480 (Mesin HPP): cetak dokumen costing dan ekspor
 * daftarnya. Otorisasi ROUTE-nya sendiri sudah diuji tuntas di
 * `PrintRoutePermissionGuardsTest` -- di sini fokus ke ISI cetakannya
 * (termasuk tanda NO_REFERENCE/NO_PRICE) dan pengkabelan tombolnya.
 */
class CostingPrintExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $this->actingAs($this->user);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'TOPSIDE', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Costings', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    /** Costing lengkap dengan satu baris sapi dan dua baris produk (satu NO_REFERENCE, satu NO_PRICE). */
    private function costingWithFlaggedItems(): Costing
    {
        $boning = Boning::create(['boning_date' => '2026-06-16', 'created_by' => $this->user->id]);

        $costing = Costing::create([
            'costing_date' => '2026-06-16', 'boning_id' => $boning->id,
            'purchase_cost' => 680500000, 'total_sales_value' => 717762805.70,
            'ratio_k' => 0.948085, 'overhead_per_kg' => 3000, 'total_kg' => 6115.08, 'profit' => 1000000,
        ]);

        $steer = CattleClass::create(['name' => 'STEER']);
        $costing->cattle()->create([
            'cattle_class_id' => $steer->id, 'head_count' => 20,
            'received_weight' => 10888, 'price_per_kg' => 62500, 'amount' => 680500000,
        ]);

        $costing->items()->create([
            'product_id' => $this->product->id, 'weight_kg' => 100,
            'reference_group_id' => null, 'gross_price' => 149000, 'trading_terms_percent' => 0,
            'net_price' => 149000, 'sales_value' => 14900000, 'hpp_per_kg' => 141315.61,
            'flag' => CostingItem::FLAG_NO_REFERENCE,
        ]);

        $noPriceProduct = Product::create([
            'name' => 'PRODUK TANPA HARGA', 'code' => 'MT999', 'category_id' => $this->product->category_id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $costing->items()->create([
            'product_id' => $noPriceProduct->id, 'weight_kg' => 5,
            'reference_group_id' => null, 'gross_price' => 0, 'trading_terms_percent' => 0,
            'net_price' => 0, 'sales_value' => 0, 'hpp_per_kg' => 0,
            'flag' => CostingItem::FLAG_NO_PRICE,
        ]);

        return $costing->fresh();
    }

    /** @test */
    public function the_printed_document_shows_cattle_cost_products_and_flags(): void
    {
        $costing = $this->costingWithFlaggedItems();

        $response = $this->actingAs($this->employee(['view_costings']))
            ->get(route('print.costing', $costing));

        $response->assertSuccessful();
        $response->assertSee($costing->costing_number);
        $response->assertSee('STEER');
        $response->assertSee('TOPSIDE');
        $response->assertSee('PRODUK TANPA HARGA');
        $response->assertSee('No reference group -- valued using general price');
        $response->assertSee('No price found');
    }

    /** @test */
    public function the_print_button_is_wired_on_edit_and_view(): void
    {
        $costing = $this->costingWithFlaggedItems();

        Livewire::actingAs($this->employee(['view_costings', 'edit_costings']))
            ->test(EditCosting::class, ['record' => $costing->getRouteKey()])
            ->assertActionVisible('print');

        // Lock butuh nol item NO_PRICE -- buang baris itu dulu supaya
        // fixture ini bisa dikunci, tanpa mengganggu test cetakan di atas
        // yang justru butuh baris NO_PRICE itu ada.
        $costing->items()->where('flag', CostingItem::FLAG_NO_PRICE)->delete();
        $costing->lock();

        Livewire::actingAs($this->employee(['view_costings']))
            ->test(ViewCosting::class, ['record' => $costing->getRouteKey()])
            ->assertActionVisible('print');
    }

    /** @test */
    public function excel_and_pdf_export_run_without_errors(): void
    {
        $this->costingWithFlaggedItems();

        Livewire::actingAs($this->employee(['view_costings']))
            ->test(ListCostings::class)
            ->callTableAction('excel')
            ->assertHasNoTableActionErrors()
            ->callTableAction('pdf')
            ->assertHasNoTableActionErrors();
    }
}
