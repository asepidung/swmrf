<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BoningResource\Pages\EditBoning;
use App\Filament\Admin\Resources\DeliveryOrderResource\Pages\EditDeliveryOrder;
use App\Models\Boning;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
use App\Models\Tally;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ED-01: halaman Edit yang hanya menyembunyikan Save harus menolak simpan di server dari tab lama.
 */
class EditPageServerGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function a_boning_tab_opened_before_locking_cannot_save_afterwards(): void
    {
        $boning = Boning::create(['boning_date' => '2026-11-01', 'note' => 'ASLI', 'created_by' => $this->user->id]);

        $tab = Livewire::test(EditBoning::class, ['record' => $boning->getRouteKey()]);

        Boning::whereKey($boning->id)->update(['kunci' => 1]);

        $tab->set('data.note', 'DIUBAH TAB LAMA')->call('save');

        $this->assertSame('ASLI', $boning->fresh()->note);
    }

    /** @test */
    public function a_delivery_order_tab_opened_while_ready_cannot_save_after_it_moved_on(): void
    {
        $segment = CustomerSegment::create(['name' => 'SEG', 'is_active' => true]);
        $customer = Customer::create(['name' => 'ED CUSTOMER', 'customer_segment_id' => $segment->id, 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30]);
        $so = SalesOrder::create(['customer_id' => $customer->id, 'delivery_date' => now()->addDay()->format('Y-m-d'), 'po_number' => 'PO-ED', 'created_by' => $this->user->id, 'status' => 'on_delivery']);
        $tally = Tally::create(['sales_order_id' => $so->id, 'tally_number' => 'TS#260888', 'status' => 'do']);
        $do = DeliveryOrder::create([
            'tally_id' => $tally->id, 'sales_order_id' => $so->id, 'customer_id' => $customer->id,
            'delivery_date' => now()->addDay()->format('Y-m-d'), 'po_number' => 'PO-ED',
            'driver_id' => \App\Models\Driver::firstOrCreate(['name' => 'JOKO'])->id, 'status' => 'Ready',
        ]);

        $tab = Livewire::test(EditDeliveryOrder::class, ['record' => $do->id]);

        DeliveryOrder::whereKey($do->id)->update(['status' => 'Invoiced']);

        $tab->set('data.po_number', 'PO-DIUBAH')->call('save');

        $this->assertSame('PO-ED', $do->fresh()->po_number);
    }

    /** @test */
    public function dropping_delivery_order_rows_from_the_submitted_state_does_not_save(): void
    {
        $segment = CustomerSegment::create(['name' => 'SEG2', 'is_active' => true]);
        $customer = Customer::create(['name' => 'RPT CUSTOMER', 'customer_segment_id' => $segment->id, 'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30]);
        $so = SalesOrder::create(['customer_id' => $customer->id, 'delivery_date' => now()->addDay()->format('Y-m-d'), 'po_number' => 'PO-RPT', 'created_by' => $this->user->id, 'status' => 'on_delivery']);
        $tally = Tally::create(['sales_order_id' => $so->id, 'tally_number' => 'TS#260889', 'status' => 'do']);
        $do = DeliveryOrder::create([
            'tally_id' => $tally->id, 'sales_order_id' => $so->id, 'customer_id' => $customer->id,
            'delivery_date' => now()->addDay()->format('Y-m-d'), 'po_number' => 'PO-RPT',
            'driver_id' => \App\Models\Driver::firstOrCreate(['name' => 'JOKO'])->id, 'status' => 'Ready',
        ]);
        $category = \App\Models\ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        foreach (['MT001', 'MT002'] as $kode) {
            $p = \App\Models\Product::create(['name' => $kode, 'code' => $kode, 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
            $do->items()->create(['product_id' => $p->id, 'box' => 1, 'weight' => 5]);
        }

        $tab = Livewire::test(EditDeliveryOrder::class, ['record' => $do->id]);
        $baris = $tab->get('data.items');
        $this->assertCount(2, $baris);
        unset($baris[array_key_first($baris)]);

        $tab->set('data.items', $baris)->set('data.po_number', 'PO-DIUBAH')->call('save');

        $this->assertSame(2, $do->items()->count());
        $this->assertSame('PO-RPT', $do->fresh()->po_number);
    }
}
