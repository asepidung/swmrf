<?php

namespace Tests\Feature;

use App\Models\Boning;
use App\Models\BoningCarcass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** B-01: satu karkas tidak boleh dihitung di dua boning terkunci (biaya beli sapi terhitung dua kali). */
class BoningCarcassSharedLockTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_boning_cannot_be_locked_when_its_carcass_is_already_in_another_locked_boning(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($user);

        $supplier = \App\Models\Supplier::create(['name' => 'TEGUH AMANAH', 'address' => 'Bogor', 'pic' => 'Teguh', 'top_days' => 30]);
        $po = \App\Models\PurchaseCattle::create(['supplier_id' => $supplier->id, 'shipping_date' => now()->toDateString(), 'created_by' => $user->id]);
        $receiving = \App\Models\CattleReceiving::create(['purchase_cattle_id' => $po->id, 'supplier_id' => $supplier->id, 'receive_date' => now()->toDateString(), 'created_by' => $user->id]);
        $weighing = \App\Models\CattleWeighing::create(['cattle_receiving_id' => $receiving->id, 'weighing_date' => now()->toDateString(), 'created_by' => $user->id]);
        $karkas = \App\Models\Carcass::create(['cattle_weighing_id' => $weighing->id, 'kill_date' => now()->toDateString(), 'created_by' => $user->id]);

        $pertama = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $user->id]);
        $kedua = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $user->id]);

        BoningCarcass::create(['boning_id' => $pertama->id, 'carcass_id' => $karkas->id]);
        BoningCarcass::create(['boning_id' => $kedua->id, 'carcass_id' => $karkas->id]);

        Boning::whereKey($pertama->id)->update(['kunci' => true, 'status' => 'LOCKED']);

        try {
            $kedua->fresh()->lock();
            $this->fail('Boning kedua seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('carcasses', $e->getMessage());
        }

        $this->assertFalse((bool) $kedua->fresh()->kunci);
    }
}
