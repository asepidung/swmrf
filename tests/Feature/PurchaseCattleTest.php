<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Supplier;
use App\Models\PurchaseCattle;
use App\Models\CattleClass;
use App\Models\PurchaseCattleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseCattleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_purchase_cattle_and_generates_document_number()
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

        $po = PurchaseCattle::create([
            'supplier_id' => $supplier->id,
            'shipping_date' => now()->addDays(5)->format('Y-m-d'),
            'summary_note' => 'TEST NOTE',
            'created_by' => $user->id,
        ]);

        $year = date('y');
        $expectedNumber = "SWM-CPO#{$year}001";
        $this->assertEquals($expectedNumber, $po->document_number);
    }

    /** @test */
    public function it_allows_printing_po_cattle_when_authenticated()
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
        
        $cattleClass = CattleClass::create(['name' => 'CATTLE CLASS A']);

        $po = PurchaseCattle::create([
            'supplier_id' => $supplier->id,
            'shipping_date' => now()->addDays(5)->format('Y-m-d'),
            'summary_note' => 'TEST NOTE',
            'created_by' => $user->id,
        ]);

        $item = PurchaseCattleItem::create([
            'purchase_cattle_id' => $po->id,
            'cattle_class_id' => $cattleClass->id,
            'qty' => 10,
            'price' => 65000,
            'item_notes' => 'ITEM NOTE',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('po-cattle.print', $po));

        $response->assertStatus(200);
        $response->assertSee('PT. SANTI WIJAYA MEAT');
        $response->assertSee('PURCHASE ORDER');
        $response->assertSee('CATTLE CLASS A');
        $response->assertSee('65.000');
        $response->assertSee('ITEM NOTE');
    }
}
