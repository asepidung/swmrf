<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CattleReceivingResource\Pages\EditCattleReceiving;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cattle Receiving tidak boleh lagi bisa diubah atau dihapus setelah ditimbang.
 *
 * ->disabled() di form Resource cuma melindungi Repeater items yang
 * relationship-backed -- field polos (doc_no, sv_ok, skkh_ok) tidak ikut
 * terlindungi. sv_ok/skkh_ok adalah penanda kepatuhan (Surat Veteriner/SKKH);
 * doc_no adalah rujukan ke dokumen fisik.
 *
 * Force-delete juga wajib ditolak: FK cattle_weighings.cattle_receiving_id
 * di MySQL CASCADE ON DELETE, jadi tanpa guard di sini, menghapus paksa
 * Cattle Receiving yang sudah ditimbang akan diam-diam menghapus
 * CattleWeighing-nya lewat CASCADE DI DATABASE -- melewati sama sekali
 * guard CattleWeighing::deleting(), dan bisa mengorbankan Carcass yang
 * sudah lahir dari situ.
 */
class CattleReceivingEditLockTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected CattleClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Petugas Terima',
            'username' => 'cr_lock_petugas',
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

    private function receiving(): CattleReceiving
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
            'receive_date' => now()->toDateString(), 'doc_no' => 'SV/ORIGINAL', 'sv_ok' => false,
            'created_by' => $this->user->id,
        ]);
        $receiving->items()->create([
            'cattle_class_id' => $this->class->id, 'eartag' => 'ID-9101', 'initial_weight' => 400,
        ]);

        return $receiving;
    }

    private function weigh(CattleReceiving $receiving): CattleWeighing
    {
        return CattleWeighing::create([
            'cattle_receiving_id' => $receiving->id, 'weighing_date' => now()->toDateString(), 'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_still_allows_editing_before_weighing_exists()
    {
        $receiving = $this->receiving();

        Livewire::test(EditCattleReceiving::class, ['record' => $receiving->id])
            ->assertOk();
    }

    /** @test */
    public function opening_the_edit_page_after_weighing_exists_redirects_to_view()
    {
        $receiving = $this->receiving();
        $this->weigh($receiving);

        Livewire::test(EditCattleReceiving::class, ['record' => $receiving->id])
            ->assertRedirect(
                \App\Filament\Admin\Resources\CattleReceivingResource::getUrl('view', ['record' => $receiving]),
            );
    }

    /**
     * Tab yang sudah terbuka SEBELUM ditimbang dari sesi lain tidak boleh
     * lolos menyimpan field polos (doc_no, sv_ok) -- ini yang sebelumnya
     * lolos meski form ->disabled().
     *
     * @test
     */
    public function a_tab_already_open_before_weighing_existed_cannot_tamper_plain_fields()
    {
        $receiving = $this->receiving();

        $test = Livewire::test(EditCattleReceiving::class, ['record' => $receiving->id]);

        // Ditimbang dari "sesi lain" SETELAH halaman ini dimuat.
        $this->weigh($receiving);

        $test->set('data.doc_no', 'TAMPERED-DOC')
            ->set('data.sv_ok', true)
            ->call('save');

        $receiving->refresh();
        $this->assertSame('SV/ORIGINAL', $receiving->doc_no, 'doc_no berhasil diubah lewat tab basi.');
        $this->assertFalse((bool) $receiving->sv_ok, 'sv_ok berhasil diubah lewat tab basi.');
    }

    /** @test */
    public function deleting_a_receiving_that_has_already_been_weighed_is_rejected()
    {
        $receiving = $this->receiving();
        $this->weigh($receiving);

        $this->expectException(\Exception::class);

        $receiving->delete();
    }

    /** @test */
    public function force_deleting_a_receiving_that_has_already_been_weighed_is_rejected()
    {
        $receiving = $this->receiving();
        $weighing = $this->weigh($receiving);

        $this->expectException(\Exception::class);

        $receiving->forceDelete();

        // Kalau tidak terhenti oleh exception, buktikan CASCADE benar-benar
        // tidak diam-diam menghapus weighing-nya.
        $this->assertNotNull(CattleWeighing::withTrashed()->find($weighing->id));
    }

    /** @test */
    public function a_receiving_without_weighing_can_still_be_deleted()
    {
        $receiving = $this->receiving();

        $receiving->delete();

        $this->assertSoftDeleted($receiving);
    }
}
