<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CattleWeighingResource\Pages\EditCattleWeighing;
use App\Models\Carcass;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;
use App\Models\FinancialLoss;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cattle Weighing tidak boleh lagi bisa diubah setelah diproses jadi Carcass.
 *
 * Halaman ini dulu SAMA SEKALI tidak punya ->disabled() -- satu-satunya
 * "penjagaan" adalah tombol Simpan yang disembunyikan, dan itu tidak
 * menghalangi permintaan Livewire yang dipaksa. actual_weight bisa diubah
 * bebas setelah Carcass lahir, dan karena afterSave() menghitung ulang
 * Financial Loss di setiap simpan tanpa syarat, angka susut yang sudah
 * dipakai finance ikut tertimpa diam-diam.
 */
class CattleWeighingEditLockTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected CattleClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Petugas Timbang',
            'username' => 'cw_lock_petugas',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->supplier = Supplier::create([
            'name' => 'FEEDLOT JAYA', 'address' => 'Bogor', 'pic' => 'Doni', 'top_days' => 30,
        ]);

        $this->class = CattleClass::create(['name' => 'BALI', 'is_active' => true]);
    }

    private function weighing(): CattleWeighing
    {
        $po = PurchaseCattle::create([
            'supplier_id' => $this->supplier->id,
            'shipping_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);
        $po->items()->create([
            'cattle_class_id' => $this->class->id, 'qty' => 1, 'price' => 55000, 'created_by' => $this->user->id,
        ]);

        $receiving = CattleReceiving::create([
            'purchase_cattle_id' => $po->id, 'supplier_id' => $this->supplier->id,
            'receive_date' => now()->toDateString(), 'created_by' => $this->user->id,
        ]);
        $receivingItem = $receiving->items()->create([
            'cattle_class_id' => $this->class->id, 'eartag' => 'ID-9001', 'initial_weight' => 400,
        ]);

        $weighing = CattleWeighing::create([
            'cattle_receiving_id' => $receiving->id, 'weighing_date' => now()->toDateString(), 'created_by' => $this->user->id,
        ]);
        $weighing->items()->create([
            'cattle_receiving_item_id' => $receivingItem->id, 'actual_weight' => 390,
        ]);

        return $weighing->fresh('items');
    }

    /** @test */
    public function it_still_allows_editing_when_no_carcass_exists()
    {
        $weighing = $this->weighing();

        Livewire::test(EditCattleWeighing::class, ['record' => $weighing->id])
            ->assertOk();
    }

    /** @test */
    public function opening_the_edit_page_after_carcass_exists_redirects_to_view()
    {
        $weighing = $this->weighing();
        Carcass::create([
            'carcass_number' => 'CC#26001', 'cattle_weighing_id' => $weighing->id,
            'kill_date' => now()->toDateString(), 'created_by' => $this->user->id,
        ]);

        Livewire::test(EditCattleWeighing::class, ['record' => $weighing->id])
            ->assertRedirect(
                \App\Filament\Admin\Resources\CattleWeighingResource::getUrl('view', ['record' => $weighing]),
            );
    }

    /**
     * Tab yang sudah terbuka SEBELUM Carcass lahir dari sesi lain tidak
     * boleh lolos menyimpan -- dan yang lebih penting, Financial Loss yang
     * sudah dihitung dari angka lama tidak boleh ikut tertimpa.
     *
     * @test
     */
    public function a_tab_already_open_before_carcass_existed_cannot_tamper_the_weight()
    {
        $weighing = $this->weighing();
        $weighing->calculateAndSaveFinancialLoss();
        $originalLoss = FinancialLoss::first()->amount;

        $test = Livewire::test(EditCattleWeighing::class, ['record' => $weighing->id]);

        // Carcass lahir dari sesi lain SETELAH halaman ini terbuka.
        Carcass::create([
            'carcass_number' => 'CC#26002', 'cattle_weighing_id' => $weighing->id,
            'kill_date' => now()->toDateString(), 'created_by' => $this->user->id,
        ]);

        $itemsData = $test->get('data.items');
        $key = array_key_first($itemsData);

        $test->set("data.items.$key.actual_weight", '1')
            ->call('save');

        $weighing->items->first()->refresh();
        $this->assertEquals(390, $weighing->items->first()->fresh()->actual_weight, 'Berat aktual berhasil diubah lewat tab basi.');
        $this->assertEquals($originalLoss, FinancialLoss::first()->amount, 'Financial Loss ikut tertimpa oleh simpan yang seharusnya ditolak.');
    }
}
