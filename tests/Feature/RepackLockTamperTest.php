<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RepackResource\Pages\EditRepack;
use App\Models\Permission;
use App\Models\Repack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kunci Repack hanya boleh berubah lewat `Repack::lock()` dan `Repack::unlock()` (audit pra-rilis
 * 14 November 2026, S-04).
 *
 * Form Repack dulu memuat `Hidden::make('kunci')` dan `Hidden::make('status')`. Field tersembunyi tetap
 * dikirim dan disimpan: pemegang `edit_repacks` saja bisa mengirim `data.kunci` lewat Livewire lalu
 * memanggil `save` -- repack terkunci tanpa pemeriksaan susut, drylog, dan penilaian, atau terbuka
 * tanpa pemeriksaan stok hasilnya. Tombol Save yang disembunyikan pada repack terkunci tidak menutup
 * method `save`-nya.
 */
class RepackLockTamperTest extends TestCase
{
    use RefreshDatabase;

    private function employee(array $permissionNames): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (array_unique([...$permissionNames, 'view_repacks']) as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Repack', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function repack(bool $locked): Repack
    {
        $pembuat = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $repack = Repack::create(['repack_date' => now()->format('Y-m-d'), 'created_by' => $pembuat->id]);

        if ($locked) {
            Repack::whereKey($repack->id)->update(['kunci' => 1]);
        }

        return $repack->fresh();
    }

    /** @test */
    public function saving_the_edit_form_cannot_lock_a_repack(): void
    {
        $repack = $this->repack(locked: false);

        Livewire::actingAs($this->employee(['edit_repacks']))
            ->test(EditRepack::class, ['record' => $repack->getRouteKey()])
            ->set('data.kunci', 1)
            ->set('data.status', 'LOCKED')
            ->call('save');

        $this->assertFalse((bool) $repack->fresh()->kunci, 'Repack tidak boleh terkunci tanpa melewati Repack::lock().');
        $this->assertNotSame('LOCKED', $repack->fresh()->status);
    }

    /** @test */
    public function saving_the_edit_form_cannot_unlock_or_change_a_locked_repack(): void
    {
        $repack = $this->repack(locked: true);
        $tanggalAsli = $repack->repack_date->toDateString();

        Livewire::actingAs($this->employee(['edit_repacks']))
            ->test(EditRepack::class, ['record' => $repack->getRouteKey()])
            ->set('data.kunci', 0)
            ->set('data.repack_date', now()->subDays(5)->format('Y-m-d'))
            ->call('save');

        $this->assertTrue((bool) $repack->fresh()->kunci, 'Repack terkunci tidak boleh terbuka tanpa melewati Repack::unlock().');
        $this->assertSame($tanggalAsli, $repack->fresh()->repack_date->toDateString(), 'Repack terkunci tidak boleh diubah lewat save.');
    }

    /** @test */
    public function an_open_repack_can_still_be_edited_normally(): void
    {
        $repack = $this->repack(locked: false);

        Livewire::actingAs($this->employee(['edit_repacks']))
            ->test(EditRepack::class, ['record' => $repack->getRouteKey()])
            ->fillForm(['note' => 'CATATAN BARU'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('CATATAN BARU', $repack->fresh()->note);
    }
}
