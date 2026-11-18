<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CattleWeighingResource\Pages\CreateCattleWeighing;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CW-01: baris timbang hanya boleh menunjuk ekor milik penerimaan yang ditimbang. */
class CattleWeighingTamperTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_weighing_row_cannot_point_to_a_cattle_of_another_receiving(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($user);
        $kelas = CattleClass::firstOrCreate(['name' => 'STEER'], ['is_active' => true]);

        $penerimaan = function (string $nama) use ($user, $kelas): array {
            $pemasok = Supplier::create(['name' => $nama, 'address' => 'X', 'pic' => 'X', 'top_days' => 30]);
            $po = PurchaseCattle::create(['supplier_id' => $pemasok->id, 'shipping_date' => now()->toDateString(), 'created_by' => $user->id]);
            $rcv = CattleReceiving::create(['purchase_cattle_id' => $po->id, 'supplier_id' => $pemasok->id, 'receive_date' => now()->toDateString(), 'created_by' => $user->id]);
            $ekor = $rcv->items()->create(['cattle_class_id' => $kelas->id, 'eartag' => 'EAR-'.$nama, 'initial_weight' => 400]);

            return [$rcv, $ekor];
        };

        [$a, $ekorA] = $penerimaan('A');
        [$b, $ekorB] = $penerimaan('B');

        $halaman = Livewire::withQueryParams(['receiving_id' => $a->id])->test(CreateCattleWeighing::class)
            ->set('data.cattle_receiving_id', $b->id)
            ->set('data.items', [
                'x1' => ['cattle_receiving_item_id' => $ekorA->id, 'actual_weight' => 390],
                'x2' => ['cattle_receiving_item_id' => $ekorB->id, 'actual_weight' => 390],
            ])
            ->call('create');

        $this->assertSame($a->id, (int) $halaman->get('data.cattle_receiving_id'));
        $this->assertSame(['x1'], array_keys($halaman->get('data.items')));
    }
}
