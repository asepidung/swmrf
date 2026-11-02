<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RepackResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Label repack/boning dicetak lewat iframe tersembunyi di halaman input (keluhan kecepatan, 2 November 2026): dalam bentuk
 * tertanam label tidak mencetak/menutup dirinya sendiri, dan tanggal kedaluwarsa mengikuti pilihan input, bukan query url.
 */
class LabelEmbeddedPrintTest extends TestCase
{
    use RefreshDatabase;

    private function hasilRepack(): RepackResult
    {
        $repack = \App\Models\Repack::forceCreate(['doc_no' => 'RP#26099', 'repack_date' => now()->toDateString(), 'status' => 'draft', 'created_by' => \App\Models\User::factory()->create()->id]);
        $produk = Product::create(['name' => 'SIRLOIN', 'code' => 'B001', 'category_id' => ProductCategory::firstOrCreate(['name' => 'MEAT'], ['prefix' => 'B0', 'is_active' => true])->id, 'structure_type' => 'main', 'is_active' => true]);
        $grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        return RepackResult::forceCreate([
            'repack_id' => $repack->id, 'product_id' => $produk->id, 'grade_id' => $grade->id, 'warehouse_id' => \App\Models\Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true])->id,
            'barcode' => 'BC-UJI-1', 'weight' => 10, 'qty_pcs' => 1, 'pack_date' => now()->toDateString(), 'exp_date' => now()->addYear()->toDateString(),
        ])->load(['product', 'repack', 'grade']);
    }

    public function test_the_standalone_label_prints_and_closes_itself(): void
    {
        $html = view('print.repack-label', ['item' => $this->hasilRepack()])->render();

        $this->assertStringContainsString('window.print()', $html);
        $this->assertStringContainsString('window.close()', $html);
    }

    public function test_the_embedded_label_leaves_printing_to_the_parent_page(): void
    {
        $html = view('print.repack-label', ['item' => $this->hasilRepack(), 'embedded' => true, 'showExp' => true])->render();

        $this->assertStringNotContainsString('window.print()', $html);
        $this->assertStringNotContainsString('window.close()', $html);
        $this->assertStringContainsString('BC-UJI-1', $html);
        $this->assertStringContainsString('Expired Date', $html);
    }

    public function test_the_expiry_date_is_hidden_when_the_input_page_did_not_ask_for_it(): void
    {
        $html = view('print.repack-label', ['item' => $this->hasilRepack(), 'embedded' => true, 'showExp' => false])->render();

        $this->assertStringNotContainsString('Expired Date', $html);
    }

    public function test_label_views_load_the_barcode_library_from_our_own_server(): void
    {
        $this->assertFileExists(public_path('js/JsBarcode.all.min.js'));

        foreach (glob(resource_path('views/print/*-label.blade.php')) as $berkas) {
            $isi = file_get_contents($berkas);

            $this->assertStringNotContainsString('cdn.jsdelivr.net', $isi, basename($berkas));
            $this->assertStringContainsString('js/JsBarcode.all.min.js', $isi, basename($berkas));
        }
    }
}
