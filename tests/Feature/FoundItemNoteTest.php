<?php

namespace Tests\Feature;

use App\Filament\Clusters\BeefStocks\Pages\FoundItemScanner;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Found Item (menu Stok Daging) menambah persediaan TANPA dokumen asal --
 * satu-satunya jejaknya adalah alasan yang ditulis orangnya. Keputusan
 * Owner 5 Oktober 2026 (issue #497): catatan WAJIB. Tidak ada batas tanggal
 * pack, itu sengaja.
 */
class FoundItemNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $programmer;

    private Warehouse $warehouse;

    private Product $product;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->programmer = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'CHUCK', 'code' => '100100',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true])->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    private function formData(?string $note): array
    {
        return [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'grade_id' => $this->grade->id,
            'qty_pcs_combined' => '11.11/5',
            'pack_date' => '2026-10-04',
            'note' => $note,
        ];
    }

    public function test_a_found_item_without_a_note_is_refused_and_creates_no_stock(): void
    {
        Livewire::actingAs($this->programmer)
            ->test(FoundItemScanner::class)
            ->mountAction('manualInput')
            ->setActionData($this->formData(null))
            ->callMountedAction()
            ->assertHasActionErrors(['note' => 'required']);

        $this->assertSame(0, BeefStock::count());
        $this->assertSame(0, BeefStockMovement::where('transaction_type', 'FOUND_ITEM')->count());
    }

    public function test_a_found_item_with_a_note_creates_the_stock_and_keeps_the_note(): void
    {
        Livewire::actingAs($this->programmer)
            ->test(FoundItemScanner::class)
            ->mountAction('manualInput')
            ->setActionData($this->formData('ditemukan di rak 3, label rusak'))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $stock = BeefStock::firstOrFail();

        $this->assertSame('ditemukan di rak 3, label rusak', $stock->note);
        $this->assertSame('0', substr($stock->barcode, 0, 1));
        $this->assertSame(1, BeefStockMovement::where('transaction_type', 'FOUND_ITEM')->count());
    }
}
