<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PurchaseCattleResource\Pages\EditPurchaseCattle;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** CR-02: PO Sapi yang sudah punya penerimaan tidak boleh berubah lewat permintaan paksa, bukan hanya disembunyikan tombolnya. */
class PurchaseCattleServerLockTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_cattle_po_with_a_receiving_refuses_a_forced_save_but_saves_before_one(): void
    {
        $user = User::create(['name' => 'P', 'username' => 'po_sapi_lock', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'programmer', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'S '.uniqid(), 'address' => 'Bogor', 'pic' => 'T', 'top_days' => 30]);
        $class = CattleClass::firstOrCreate(['name' => 'STEER'], ['is_active' => true]);
        $po = PurchaseCattle::create(['supplier_id' => $supplier->id, 'shipping_date' => now()->toDateString(), 'created_by' => $user->id, 'summary_note' => 'ASAL']);
        $po->items()->create(['cattle_class_id' => $class->id, 'qty' => 1, 'price' => 55000, 'created_by' => $user->id]);

        $this->actingAs($user);

        // Kontrol positif: sebelum ada penerimaan, simpan berjalan.
        Livewire::test(EditPurchaseCattle::class, ['record' => $po->id])->fillForm(['summary_note' => 'SEBELUM'])->call('save');
        $this->assertSame('SEBELUM', $po->fresh()->summary_note);

        $page = Livewire::test(EditPurchaseCattle::class, ['record' => $po->id]);

        CattleReceiving::create(['purchase_cattle_id' => $po->id, 'supplier_id' => $supplier->id, 'receive_date' => now()->toDateString(), 'created_by' => $user->id]);

        $page->fillForm(['summary_note' => 'SESUDAH'])->call('save')
            ->assertNotified(__('This purchase order already has a receiving and cannot be edited.'));

        $this->assertSame('SEBELUM', $po->fresh()->summary_note, 'PO yang sudah punya penerimaan tidak berubah');
    }
}
