<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\UserResource;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran User, 15 September 2026 -- keputusan Ayah.
 *
 * `edit_users` dulu menanggung dua hal sekaligus: data & status aktif akun,
 * DAN izin modul lain lewat checkbox yang sama -- siapa pun yang bisa
 * mengedit user otomatis bisa mengangkat dirinya sendiri (atau siapa pun) ke
 * akses penuh. Izin baru `manage_user_permissions` memisahkan keduanya, dan
 * mengedit izin akun SENDIRI ditolak tidak peduli izin apa yang dipegang.
 */
class UserPermissionEscalationTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(array $permissionNames = []): User
    {
        $employee = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['module_name' => 'Users', 'description' => $name]
            );
            $employee->permissions()->attach($permission->id);
        }

        return $employee;
    }

    /**
     * Aktor yang benar-benar bisa MEMBUKA halaman Edit User.
     *
     * `view_users` wajib di sini bukan cuma soal tampilan menu -- Filament
     * menggerbangi SELURUH halaman Resource (termasuk Edit) di belakang
     * `canViewAny()`, bukan hanya halaman List. `edit_users` sendiri
     * mensyaratkan `canEdit()` (`UserPolicy::update()`), yang terpisah.
     * Tanpa `view_users`, membuka Edit User akan berakhir 403 lebih dulu
     * sebelum sempat menguji apa pun soal `manage_user_permissions`.
     *
     * @param  array<int, string>  $tambahan
     */
    private function makeActor(array $tambahan = []): User
    {
        return $this->makeEmployee([...['view_users', 'edit_users'], ...$tambahan]);
    }

    // =====================================================================
    // Form: seksi izin cuma tampil dengan manage_user_permissions, dan
    // sembunyi total saat mengedit akun sendiri.
    // =====================================================================

    /** @test */
    public function the_permissions_section_is_hidden_from_a_holder_of_edit_users_alone(): void
    {
        $target = $this->makeEmployee();

        $this->assertFalse(
            $this->sectionVisible($this->makeActor(), $target),
        );
    }

    /** @test */
    public function the_permissions_section_shows_for_a_holder_of_manage_user_permissions_editing_someone_else(): void
    {
        $target = $this->makeEmployee();

        $this->assertTrue(
            $this->sectionVisible($this->makeActor(['manage_user_permissions']), $target),
        );
    }

    /** @test */
    public function the_permissions_section_is_hidden_when_editing_your_own_account_no_matter_the_permission(): void
    {
        $self = $this->makeActor(['manage_user_permissions']);

        $this->assertFalse($this->sectionVisible($self, $self));
    }

    private function sectionVisible(User $actor, User $record): bool
    {
        $form = Livewire::actingAs($actor->fresh())
            ->test(EditUser::class, ['record' => $record->getRouteKey()])
            ->instance()
            ->getForm('form');

        $section = $form->getComponent(
            fn ($component) => method_exists($component, 'getHeading')
                && $component->getHeading() === __('Permissions (Hak Akses)'),
        );

        return $section?->isVisible() ?? false;
    }

    // =====================================================================
    // Server: sync() ditolak tanpa manage_user_permissions, dan saat
    // mengedit akun sendiri -- terlepas dari apa yang dikirim form.
    // =====================================================================

    /** @test */
    public function saving_permissions_is_refused_for_a_holder_of_edit_users_alone(): void
    {
        $actor = $this->makeActor();
        $target = $this->makeEmployee();
        $add = Permission::firstOrCreate(['name' => 'test_probe_permission'], ['module_name' => 'Test', 'description' => 'x']);

        Livewire::actingAs($actor->fresh())
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['permissions_Test' => [$add->id]])
            ->call('save');

        $this->assertFalse(
            $target->fresh()->hasPermission('test_probe_permission'),
            'edit_users saja tidak boleh cukup untuk mengubah izin akun lain.',
        );
    }

    /** @test */
    public function saving_permissions_on_your_own_account_is_refused_even_with_the_permission(): void
    {
        $self = $this->makeActor(['manage_user_permissions']);

        $add = Permission::firstOrCreate(['name' => 'test_probe_permission'], ['module_name' => 'Test', 'description' => 'x']);

        Livewire::actingAs($self->fresh())
            ->test(EditUser::class, ['record' => $self->getRouteKey()])
            ->fillForm(['permissions_Test' => [$add->id]])
            ->call('save');

        $this->assertFalse(
            $self->fresh()->hasPermission('test_probe_permission'),
            'Mengedit akun sendiri tidak boleh bisa mengubah izin sendiri, walau punya manage_user_permissions.',
        );
    }

    /** @test */
    public function saving_permissions_still_works_for_a_holder_of_manage_user_permissions_on_someone_else(): void
    {
        $actor = $this->makeActor(['manage_user_permissions']);
        $target = $this->makeEmployee();
        $add = Permission::firstOrCreate(['name' => 'test_probe_permission'], ['module_name' => 'Test', 'description' => 'x']);

        Livewire::actingAs($actor->fresh())
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['permissions_Test' => [$add->id]])
            ->call('save');

        $this->assertTrue(
            $target->fresh()->hasPermission('test_probe_permission'),
            'Jalur normal (bukan akun sendiri, punya manage_user_permissions) tidak boleh ikut tertutup.',
        );
    }
}
