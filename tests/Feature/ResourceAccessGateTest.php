<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu hanya boleh muncul bagi yang benar-benar berhak.
 *
 * Kejadian yang melahirkan test ini: `PriceListResource` dan
 * `ReceivableResource` sama-sama memakai model `CustomerGroup`. Laravel
 * menemukan Policy lewat nama MODEL, jadi keduanya jatuh ke
 * `CustomerGroupPolicy` -- `PriceListPolicy` dan `ReceivablePolicy` tidak
 * pernah dipanggil sama sekali dan praktis kode mati.
 *
 * Akibatnya siapa pun yang punya `view_customer_groups` ikut melihat menu
 * Price List dan Receivables beserta datanya, meski tidak diberi hak itu
 * sedikit pun. Tidak ada error, tidak ada gejala -- menunya hanya muncul.
 */
class ResourceAccessGateTest extends TestCase
{
    use RefreshDatabase;

    protected function userWith(array $permissionNames): User
    {
        $user = User::create([
            'name' => 'Pegawai',
            'username' => 'gate_' . substr(md5(implode(',', $permissionNames)), 0, 8),
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['module_name' => 'Test', 'description' => $name],
            );

            $user->permissions()->attach($permission->id);
        }

        return $user;
    }

    /**
     * Inti kejadiannya: hak Customer Group TIDAK boleh membuka Price List
     * maupun Receivables.
     *
     * @test
     */
    public function customer_group_permission_does_not_unlock_price_list_or_receivables()
    {
        $this->actingAs($this->userWith(['view_customer_groups']));

        $this->assertFalse(
            \App\Filament\Admin\Resources\PriceListResource::canViewAny(),
            'Hak Customer Group membuka Price List. Policy jatuh ke CustomerGroupPolicy karena modelnya dipakai bersama.',
        );

        $this->assertFalse(
            \App\Filament\Admin\Resources\ReceivableResource::canViewAny(),
            'Hak Customer Group membuka Receivables. Policy jatuh ke CustomerGroupPolicy karena modelnya dipakai bersama.',
        );
    }

    /** @test */
    public function the_right_permission_does_unlock_each_menu()
    {
        $this->actingAs($this->userWith(['view_price_lists']));
        $this->assertTrue(\App\Filament\Admin\Resources\PriceListResource::canViewAny());
        $this->assertFalse(\App\Filament\Admin\Resources\ReceivableResource::canViewAny());

        $this->actingAs($this->userWith(['view_receivables']));
        $this->assertTrue(\App\Filament\Admin\Resources\ReceivableResource::canViewAny());
        $this->assertFalse(\App\Filament\Admin\Resources\PriceListResource::canViewAny());
    }

    /**
     * Menu yang tidak berhak diakses juga tidak boleh terdaftar di navigasi.
     *
     * `canViewAny()` menjaga datanya, `shouldRegisterNavigation()` menjaga
     * menunya. Keduanya diperiksa terpisah karena bisa berbeda.
     *
     * @test
     */
    public function navigation_is_hidden_when_the_permission_is_missing()
    {
        $this->actingAs($this->userWith(['view_customer_groups']));

        $this->assertFalse(\App\Filament\Admin\Resources\PriceListResource::shouldRegisterNavigation());
        $this->assertFalse(\App\Filament\Admin\Resources\ReceivableResource::shouldRegisterNavigation());
    }

    /** @test */
    public function a_user_without_any_permission_sees_no_menu_at_all()
    {
        $this->actingAs($this->userWith([]));

        $panel = \Filament\Facades\Filament::getPanel('admin');
        \Filament\Facades\Filament::setCurrentPanel($panel);

        $visible = [];
        foreach ($panel->getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                $visible[] = $item->getLabel();
            }
        }

        $this->assertSame([], $visible, 'Masih ada menu yang terlihat: ' . implode(', ', $visible));
    }

    /**
     * Resource yang NAMANYA tidak cocok dengan modelnya wajib punya
     * `canViewAny()` sendiri.
     *
     * Laravel menemukan Policy lewat nama MODEL. Selama nama Resource sejalan
     * dengan modelnya (`MaterialResource` -> `Material` -> `MaterialPolicy`),
     * Policy otomatisnya memang tepat sasaran. Begitu sebuah Resource
     * menumpang model milik modul lain, Policy yang terpanggil adalah milik
     * model itu -- bukan miliknya sendiri -- dan hak akses modul lain ikut
     * membuka pintunya.
     *
     * Itulah yang terjadi pada Price List dan Receivables. Aturan ini menjaga
     * seluruh polanya, bukan cuma dua Resource yang kemarin bocor.
     *
     * @test
     */
    public function every_resource_with_a_borrowed_model_declares_its_own_gate()
    {
        $offenders = [];
        $checked = 0;

        foreach (glob(app_path('Filament/Admin/Resources/*Resource.php')) as $file) {
            $source = file_get_contents($file);

            if (! preg_match('/\$model\s*=\s*\\\\?([A-Za-z\\\\]+)::class/', $source, $match)) {
                continue;
            }

            $model = last(explode('\\', $match[1]));
            $resource = basename($file, '.php');

            if ($resource === $model . 'Resource') {
                continue; // pemilik sah modelnya, Policy otomatis sudah tepat
            }

            $checked++;

            if (! str_contains($source, 'function canViewAny')) {
                $offenders[] = $resource . ' (menumpang model ' . $model . ')';
            }
        }

        $this->assertGreaterThan(
            0,
            $checked,
            'Tidak ada Resource yang terperiksa -- pemindainya kemungkinan rusak, bukan kodenya yang bersih.',
        );

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Resource berikut memakai model milik modul lain tapi tidak punya canViewAny() sendiri, "
            . "sehingga Policy-nya akan salah sasaran dan hak akses modul lain ikut membuka pintunya:\n"
            . implode("\n", $offenders),
        );
    }
}
