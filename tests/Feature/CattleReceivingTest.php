<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Supplier;
use App\Models\PurchaseCattle;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\CattleReceivingItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CattleReceivingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_cattle_receiving_and_locks_associated_po()
    {
        $user = User::factory()->create();
        $supplier = Supplier::create([
            'name' => 'SUPPLIER A',
            'address' => 'ADDRESS A',
            'pic' => 'PIC A',
            'phone' => '12345',
            'top_days' => 30,
            'is_tax_11' => false,
            'is_active' => true,
        ]);

        $cattleClass = CattleClass::create(['name' => 'STEER']);

        $po = PurchaseCattle::create([
            'supplier_id' => $supplier->id,
            'shipping_date' => now()->addDays(5)->format('Y-m-d'),
            'summary_note' => 'TEST PO NOTE',
            'created_by' => $user->id,
        ]);

        $receiving = CattleReceiving::create([
            'purchase_cattle_id' => $po->id,
            'supplier_id' => $supplier->id,
            'receive_date' => now()->format('Y-m-d'),
            'doc_no' => 'SV/2026/001',
            'sv_ok' => true,
            'skkh_ok' => true,
            'note' => 'TEST NOTE',
            'created_by' => $user->id,
        ]);

        $item = CattleReceivingItem::create([
            'cattle_receiving_id' => $receiving->id,
            'cattle_class_id' => $cattleClass->id,
            'eartag' => 'TAG-001',
            'initial_weight' => 500,
            'notes' => 'ITEM NOTE',
        ]);

        // Assert receiving number prefix
        $year = date('y');
        $this->assertStringStartsWith("CR#{$year}", $receiving->receiving_number);

        // Assert PO locks (receivings exists)
        $this->assertTrue($po->receivings()->exists());

        // Assert model-level deletion prevention
        $this->expectException(\Exception::class);
        $po->delete();
    }
}
