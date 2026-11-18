<?php

namespace Tests\Feature;

use App\Models\CattleReceiving;
use App\Models\Payable;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CR-03: penerimaan sapi yang hutangnya sudah dibayar tidak bisa dihapus (sama dengan GR Material/Produk). */
class CattleReceivingPaidPayableTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_receiving_whose_payable_was_already_paid_cannot_be_deleted(): void
    {
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($user);

        $supplier = Supplier::create(['name' => 'TEGUH AMANAH', 'address' => 'Bogor', 'pic' => 'Teguh', 'top_days' => 30]);
        $po = PurchaseCattle::create(['supplier_id' => $supplier->id, 'shipping_date' => now()->toDateString(), 'created_by' => $user->id]);
        $receiving = CattleReceiving::create(['purchase_cattle_id' => $po->id, 'supplier_id' => $supplier->id, 'receive_date' => now()->toDateString(), 'created_by' => $user->id]);

        $payable = $receiving->payable()->first();
        if (! $payable) {
            $payable = Payable::unguarded(fn () => $receiving->payable()->create([
                'supplier_id' => $supplier->id, 'document_number' => 'PAY-TEST', 'balance' => 600000, 'amount' => 1000000, 'paid_amount' => 400000, 'status' => 'partial', 'due_date' => now()->addMonth()->toDateString(),
            ]));
        } else {
            $payable->forceFill(['paid_amount' => 400000, 'status' => 'partial'])->saveQuietly();
        }

        try {
            $receiving->fresh()->delete();
            $this->fail('Penghapusan seharusnya ditolak.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('payable', $e->getMessage());
        }

        $this->assertNotNull($receiving->fresh());
        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'deleted_at' => null]);
    }
}
