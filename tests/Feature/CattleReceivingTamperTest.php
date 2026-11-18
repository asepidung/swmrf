<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CattleReceivingResource\Pages\CreateCattleReceiving;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CR-01: pemasok dan PO Penerimaan Sapi tidak boleh ditentukan field tersembunyi dari browser. */
class CattleReceivingTamperTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_supplier_and_po_come_from_the_po_the_page_was_opened_for(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($user);

        $buat = fn (string $nama) => PurchaseCattle::create([
            'supplier_id' => Supplier::create(['name' => $nama, 'address' => 'X', 'pic' => 'X', 'top_days' => 30])->id,
            'shipping_date' => now()->toDateString(), 'created_by' => $user->id,
        ]);
        $asli = $buat('PEMASOK ASLI');
        $lain = $buat('PEMASOK LAIN');

        $halaman = Livewire::withQueryParams(['po_id' => $asli->id])->test(CreateCattleReceiving::class)
            ->set('data.purchase_cattle_id', $lain->id)
            ->set('data.supplier_id', $lain->supplier_id)
            ->call('create');

        $this->assertSame($asli->id, (int) $halaman->get('data.purchase_cattle_id'));
        $this->assertSame($asli->supplier_id, (int) $halaman->get('data.supplier_id'));
    }
}
