<?php

namespace Tests\Feature;

use App\Models\Boning;
use App\Models\BoningItem;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Label Boning memakai logo Halal Indonesia rebah (#495).
 *
 * Nomor sertifikat halal sudah ada DI DALAM gambar logo baru, jadi teks nomor
 * lama tidak boleh dicetak lagi. Rasio aspek logo dijaga: hanya satu batas
 * ukuran, tinggi mengikuti `auto`.
 */
class BoningLabelHalalLogoTest extends TestCase
{
    use RefreshDatabase;

    private function labelHtml(): string
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_bonings'], ['module_name' => 'Boning', 'description' => 'view_bonings'])->id
        );

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => '2', 'is_active' => true]);
        $product = Product::create([
            'name' => 'CHUCK', 'code' => '200100', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $warehouse = Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);
        $grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $boning = Boning::create(['boning_date' => now()->format('Y-m-d'), 'created_by' => $user->id]);

        $item = BoningItem::create([
            'boning_id' => $boning->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'grade_id' => $grade->id, 'weight' => 10.25, 'qty_pcs' => 3, 'ph_level' => 5.4,
            'pack_date' => '2026-10-04', 'exp_date' => '2027-01-04',
            'barcode' => '1041026200100100102503540008', 'created_by' => $user->id,
        ]);

        return $this->actingAs($user->fresh())->get(route('boning.label', $item->id))->assertOk()->getContent();
    }

    public function test_the_label_uses_the_landscape_halal_logo(): void
    {
        $html = $this->labelHtml();

        $this->assertStringContainsString('img/halalrebah.png', $html);
        $this->assertStringNotContainsString('img/halal.png', $html, 'logo lama tegak tidak boleh ikut tercetak');
        $this->assertFileExists(public_path('img/halalrebah.png'));
    }

    public function test_the_old_halal_number_is_no_longer_printed_as_text(): void
    {
        $html = $this->labelHtml();

        $this->assertStringNotContainsString('ID00110015321510124', $html);
        // Nomor RPHR tetap; urusan NKV menyusul.
        $this->assertStringContainsString('RPHR 3201170-027', $html);
    }

    public function test_the_logo_keeps_its_aspect_ratio_and_stays_inside_its_cell(): void
    {
        $html = $this->labelHtml();

        preg_match('/<img[^>]*halalrebah\.png[^>]*>/', $html, $m);
        $this->assertNotEmpty($m, 'tag logo tidak ditemukan');
        $tag = $m[0];

        // Tinggi tidak boleh dipatok bersamaan dengan lebar -- itu yang
        // menggepengkan atau melarkan gambar.
        $this->assertDoesNotMatchRegularExpression('/\sheight="\d+"/', $tag);
        $this->assertStringContainsString('height:auto', $tag);
        $this->assertStringContainsString('max-width:100%', $tag);
    }
}
