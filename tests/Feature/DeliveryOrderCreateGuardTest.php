<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\DeliveryOrderResource\Pages\CreateDeliveryOrder;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
use App\Models\Tally;
use Livewire\Livewire;

/**
 * Surat jalan hanya lahir dari tally yang sudah dikunci, untuk pelanggan dan SO tally itu sendiri
 * (audit pra-rilis 14 November 2026, K-04).
 *
 * `tally_id` field tersembunyi, `customer_id` dan `sales_order_id` field nonaktif yang tetap disimpan,
 * dan halaman Create hanya memeriksa bahwa tally-nya ADA. Membuka alamat Create dengan id tally yang
 * masih dipindai membuat surat jalan dari muatan yang belum selesai, dan ketiga field itu bisa diisi
 * dari browser sehingga surat jalan menunjuk pelanggan atau SO lain.
 */
class DeliveryOrderCreateGuardTest extends DeliveryOrderTest
{
    private function soDanTally(string $statusTally, ?Customer $customer = null): Tally
    {
        $so = SalesOrder::create([
            'customer_id' => ($customer ?? $this->customer)->id,
            'delivery_date' => now()->addDay()->format('Y-m-d'),
            'created_by' => $this->user->id,
            'status' => 'ready',
        ]);

        return Tally::create([
            'sales_order_id' => $so->id,
            'tally_number' => 'TALLY-AUDIT-'.uniqid(),
            'status' => $statusTally,
        ]);
    }

    public function test_a_delivery_order_cannot_be_created_from_a_tally_that_is_still_being_scanned(): void
    {
        $tally = $this->soDanTally(Tally::STATUS_PROCESSING);

        Livewire::actingAs($this->user)
            ->withQueryParams(['tally_id' => $tally->id])
            ->test(CreateDeliveryOrder::class)
            ->assertRedirect(\App\Filament\Admin\Resources\DeliveryOrderResource::getUrl('draft'));

        $this->assertSame(0, DeliveryOrder::where('tally_id', $tally->id)->count(), 'Tally yang belum dikunci tidak boleh punya surat jalan.');
        $this->assertSame(Tally::STATUS_PROCESSING, $tally->fresh()->status);
    }

    public function test_the_delivery_order_takes_customer_and_sales_order_from_its_tally(): void
    {
        $tally = $this->soDanTally(Tally::STATUS_LOCKED);

        $lain = Customer::create(array_merge(
            $this->customer->only(['customer_group_id', 'customer_segment_id']),
            ['name' => 'PELANGGAN LAIN', 'address' => 'X', 'is_active' => true],
        ));
        $tallyLain = $this->soDanTally(Tally::STATUS_LOCKED, $lain);

        Livewire::actingAs($this->user)
            ->withQueryParams(['tally_id' => $tally->id])
            ->test(CreateDeliveryOrder::class)
            ->fillForm(['delivery_date' => now()->addDay()->format('Y-m-d'), 'driver_id' => \App\Models\Driver::firstOrCreate(['name' => 'JOKO'])->id])
            ->set('data.customer_id', $lain->id)
            ->set('data.sales_order_id', $tallyLain->sales_order_id)
            ->call('create');

        $do = DeliveryOrder::where('tally_id', $tally->id)->firstOrFail();

        $this->assertSame($this->customer->id, $do->customer_id, 'Pelanggan surat jalan harus pelanggan SO tally-nya.');
        $this->assertSame($tally->sales_order_id, $do->sales_order_id);
    }

    public function test_a_locked_tally_still_gets_its_delivery_order(): void
    {
        $tally = $this->soDanTally(Tally::STATUS_LOCKED);

        Livewire::actingAs($this->user)
            ->withQueryParams(['tally_id' => $tally->id])
            ->test(CreateDeliveryOrder::class)
            ->fillForm(['delivery_date' => now()->addDay()->format('Y-m-d'), 'driver_id' => \App\Models\Driver::firstOrCreate(['name' => 'JOKO'])->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, DeliveryOrder::where('tally_id', $tally->id)->count());
        $this->assertSame(Tally::STATUS_DELIVERED, $tally->fresh()->status);
    }
}
