<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BoningResource\Pages\LabelingBoning;
use App\Models\BeefStock;
use App\Models\Boning;
use App\Models\BoningItem;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran Boning, 15 September 2026.
 */
class BoningSusulanTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Warehouse $warehouse;
    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN BEEF', 'code' => 'B001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
    }

    private function boning(bool $kunci = false): Boning
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        return Boning::create([
            'boning_date' => now()->format('Y-m-d'), 'created_by' => $user->id,
            'kunci' => $kunci, 'status' => $kunci ? 'LOCKED' : 'OPEN',
        ]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (array_unique([...$permissionNames, 'view_bonings']) as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Boning', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    // =========================================================================
    // LabelingBoning butuh edit_bonings -- terbukti lewat HTTP sungguhan
    // =========================================================================

    /** @test */
    public function the_labeling_page_is_closed_over_http_without_edit_bonings(): void
    {
        $boning = $this->boning();
        $user = $this->employee(); // tanpa edit_bonings

        $this->actingAs($user)
            ->get(\App\Filament\Admin\Resources\BoningResource::getUrl('labeling', ['record' => $boning->getKey()]))
            ->assertForbidden();

        $user->permissions()->attach(
            Permission::firstOrCreate(['name' => 'edit_bonings'], ['module_name' => 'Boning', 'description' => 'x'])->id
        );

        $this->actingAs($user->fresh())
            ->get(\App\Filament\Admin\Resources\BoningResource::getUrl('labeling', ['record' => $boning->getKey()]))
            ->assertSuccessful();
    }

    // =========================================================================
    // create(): kunci dibaca ulang dari DB, bukan dari state
    // =========================================================================

    /** @test */
    public function creating_a_label_on_a_locked_boning_creates_no_stock(): void
    {
        $boning = $this->boning();
        $user = $this->employee(['edit_bonings']);

        $test = Livewire::actingAs($user)->test(LabelingBoning::class, ['record' => $boning]);

        // Dikunci dari "sesi lain" SETELAH halaman ini dimuat -- meniru
        // tab yang masih terbuka.
        Boning::whereKey($boning->id)->update(['kunci' => true, 'status' => 'LOCKED']);

        $test->set('data.warehouse_id', $this->warehouse->id)
            ->set('data.product_id', $this->product->id)
            ->set('data.grade_id', $this->grade->id)
            ->set('data.pack_date', now()->format('Y-m-d'))
            ->set('data.qty_pcs_combined', '10.5/2')
            ->set('data.ph_level', '5.5')
            ->call('create');

        $this->assertSame(0, BoningItem::count());
        $this->assertSame(0, BeefStock::count());
    }

    /** @test */
    public function creating_a_label_on_an_open_boning_creates_stock(): void
    {
        $boning = $this->boning();
        $user = $this->employee(['edit_bonings']);

        Livewire::actingAs($user)
            ->test(LabelingBoning::class, ['record' => $boning])
            ->set('data.warehouse_id', $this->warehouse->id)
            ->set('data.product_id', $this->product->id)
            ->set('data.grade_id', $this->grade->id)
            ->set('data.pack_date', now()->format('Y-m-d'))
            ->set('data.qty_pcs_combined', '10.5/2')
            ->set('data.ph_level', '5.5')
            ->call('create');

        $this->assertSame(1, BoningItem::count());
        $this->assertSame(1, BeefStock::count());
    }

    // =========================================================================
    // Void/delete item: kunci dibaca ulang dari baris yang dikunci
    // =========================================================================

    /** @test */
    public function voiding_an_item_after_the_document_was_locked_elsewhere_keeps_the_stock(): void
    {
        $boning = $this->boning();
        $user = $this->employee(['edit_bonings']);

        $item = BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 2, 'ph_level' => 5.5,
            'pack_date' => now(), 'exp_date' => now()->addMonths(3),
            'barcode' => '1'.now()->format('dmy').'B0011105000255001', 'created_by' => $user->id,
        ]);
        BeefStock::create([
            'barcode' => $item->barcode, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 2,
            'pack_date' => now(), 'origin' => 'BONING', 'status' => 'IN_STOCK',
        ]);

        $test = Livewire::actingAs($user)->test(LabelingBoning::class, ['record' => $boning]);

        Boning::whereKey($boning->id)->update(['kunci' => true, 'status' => 'LOCKED']);

        $test->mountTableAction('delete', $item->id)->callMountedTableAction();

        $this->assertDatabaseHas('boning_items', ['id' => $item->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('beef_stocks', ['barcode' => $item->barcode]);
    }

    // Boning::lock()/unlock() sekarang mengunci baris + membaca ulang
    // sebelum menulis (lihat App\Models\Boning). Skenario "tidak bisa
    // dikunci dua kali" sudah punya test lengkap dengan fixture karkas
    // sungguhan di CarcassYieldTest::test_a_locked_boning_cannot_be_locked_twice
    // -- dijalankan sebagai bagian dari suite penuh untuk membuktikan
    // perubahan ini tidak meregresi perilakunya, tidak diulang di sini.
}
