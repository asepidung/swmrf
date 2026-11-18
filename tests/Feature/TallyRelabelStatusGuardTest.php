<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\TallyResource\Pages\ScanTally;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Grade;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** TL-02: relabel di Scan Tally membaca ulang status tally; tab lama pada tally yang sudah dikunci tidak boleh mengubah barcode. */
class TallyRelabelStatusGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tally, 1: TallyItem, 2: User} */
    private function siapkan(): array
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'RELABEL CUSTOMER', 'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
        $product = Product::create([
            'name' => 'CHUCK', 'code' => '100100',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true])->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $so = SalesOrder::create([
            'customer_id' => $customer->id, 'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'created_by' => $user->id, 'status' => 'processing',
        ]);
        $tally = Tally::create(['sales_order_id' => $so->id, 'status' => 'processing']);
        $item = TallyItem::create([
            'tally_id' => $tally->id, 'barcode' => '2140626100100100111105540001',
            'product_id' => $product->id, 'warehouse_id' => Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true])->id,
            'grade_id' => Grade::create(['name' => 'CHILL', 'is_active' => true])->id,
            'weight' => 11.11, 'qty_pcs' => 5, 'ph_level' => 5.4, 'pack_date' => '2026-06-14', 'origin' => 'BONING',
        ]);

        return [$tally, $item, $user];
    }

    /** @test */
    public function relabel_from_a_stale_tab_after_the_tally_was_locked_changes_nothing(): void
    {
        [$tally, $item, $user] = $this->siapkan();
        $lama = $item->barcode;

        $tab = Livewire::actingAs($user)->test(ScanTally::class, ['record' => $tally])->set('podLimit', 5);

        Tally::whereKey($tally->id)->update(['status' => 'locked']);

        $tab->callTableAction('relabel', $item, ['pack_date' => '2026-10-05', 'show_exp' => false]);

        $this->assertSame($lama, $item->fresh()->barcode);
        $this->assertDatabaseMissing('beef_stock_movements', ['transaction_type' => 'TALLY_RELABEL']);
    }

    /** @test */
    public function relabel_on_a_processing_tally_still_works(): void
    {
        [$tally, $item, $user] = $this->siapkan();
        $lama = $item->barcode;

        Livewire::actingAs($user)->test(ScanTally::class, ['record' => $tally])->set('podLimit', 5)
            ->callTableAction('relabel', $item, ['pack_date' => '2026-10-05', 'show_exp' => false]);

        $this->assertNotSame($lama, $item->fresh()->barcode);
    }
}
