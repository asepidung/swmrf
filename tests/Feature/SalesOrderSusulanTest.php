<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SalesOrderResource\Pages\CreateSalesOrder;
use App\Filament\Admin\Resources\SalesOrderResource\Pages\EditSalesOrder;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Tally;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran Sales Order, 15 September 2026.
 *
 * Tiga celah:
 * - Tombol Print di EditSalesOrder TIDAK PERNAH tampil, status apa pun --
 *   `!` di `!$this->record->status === 'cancelled'` mengikat lebih dulu
 *   daripada `===`, jadi selalu `false === 'cancelled'`, selalu false.
 * - ForceDelete (tunggal dan bulk) pada Sales Order yang masih punya Tally
 *   menampilkan galat SQL mentah alih-alih pemberitahuan yang bisa dibaca.
 * - Repeater `items` bisa disimpan kosong -- pesan validasinya sudah ada
 *   sejak awal, tapi `minItems(1)`-nya sendiri tidak pernah dipasang.
 *
 * (#2 dari triase -- race DraftTally saat membuat Tally dari SO yang
 * sedang WAITING -- sudah diperbaiki lewat PR Tally (#419), tidak diulang
 * di sini. #3 dari triase -- afterSave()/afterCreate() tanpa transaksi --
 * ternyata bukan celah: EditRecord::save()/CreateRecord::create() bawaan
 * Filament SUDAH membungkus seluruh alurnya, termasuk kedua hook itu,
 * dalam transaksi dengan rollback otomatis. Tidak ditambah pembungkus
 * lagi supaya tidak dobel.)
 */
class SalesOrderSusulanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $segment = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $this->customer = Customer::create([
            'name' => 'FIXTURE CUSTOMER', 'customer_segment_id' => $segment->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
    }

    private function salesOrder(string $status = SalesOrder::STATUS_WAITING): SalesOrder
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'delivery_date' => now()->addDay()->format('Y-m-d'),
            'po_number' => 'PO-FIXTURE-'.uniqid(), 'status' => $status, 'created_by' => $this->user->id,
        ]);
        SalesOrderItem::create([
            'sales_order_id' => $so->id, 'product_id' => $this->product->id, 'weight' => 20.0, 'price' => 150000,
        ]);

        return $so;
    }

    // =========================================================================
    // Tombol Print: seharusnya tampil untuk status apa pun kecuali cancelled
    // =========================================================================

    /** @test */
    public function the_print_button_is_visible_for_a_waiting_sales_order(): void
    {
        $so = $this->salesOrder(SalesOrder::STATUS_WAITING);

        Livewire::actingAs($this->user)
            ->test(EditSalesOrder::class, ['record' => $so->getRouteKey()])
            ->assertActionVisible('print');
    }

    /** @test */
    public function the_print_button_is_visible_for_a_completed_sales_order(): void
    {
        $so = $this->salesOrder(SalesOrder::STATUS_COMPLETED);

        Livewire::actingAs($this->user)
            ->test(EditSalesOrder::class, ['record' => $so->getRouteKey()])
            ->assertActionVisible('print');
    }

    /** @test */
    public function the_print_button_is_hidden_for_a_cancelled_sales_order(): void
    {
        $so = $this->salesOrder(SalesOrder::STATUS_CANCELLED);

        Livewire::actingAs($this->user)
            ->test(EditSalesOrder::class, ['record' => $so->getRouteKey()])
            ->assertActionHidden('print');
    }

    // =========================================================================
    // ForceDelete: SO yang masih punya Tally harus ditolak dengan ramah
    // =========================================================================

    /** @test */
    public function force_deleting_a_sales_order_still_referenced_by_a_tally_shows_a_friendly_notice(): void
    {
        $so = $this->salesOrder();
        Tally::create(['sales_order_id' => $so->id, 'status' => Tally::STATUS_PROCESSING]);
        $so->delete();

        Livewire::actingAs($this->user)
            ->test(EditSalesOrder::class, ['record' => $so->getRouteKey()])
            ->callAction('forceDelete')
            ->assertNotified();

        $this->assertDatabaseHas('sales_orders', ['id' => $so->id]);
        $this->assertSoftDeleted('sales_orders', ['id' => $so->id]);
    }

    /** @test */
    public function force_deleting_a_sales_order_with_no_tally_or_invoice_succeeds(): void
    {
        $so = $this->salesOrder();
        $so->delete();

        Livewire::actingAs($this->user)
            ->test(EditSalesOrder::class, ['record' => $so->getRouteKey()])
            ->callAction('forceDelete');

        $this->assertDatabaseMissing('sales_orders', ['id' => $so->id]);
    }

    // =========================================================================
    // Repeater items: tidak boleh disimpan kosong
    // =========================================================================

    /** @test */
    public function creating_a_sales_order_without_any_items_is_rejected(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateSalesOrder::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'delivery_date' => now()->addDay()->format('Y-m-d'),
                'items' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['items']);

        $this->assertSame(0, SalesOrder::count());
    }
}
