<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialUsageResource\Pages\CreateManualUsage;
use App\Models\Material;
use App\Models\MaterialAdjustment;
use App\Models\MaterialCategory;
use App\Models\MaterialStock;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Create Manual Usage" tidak punya Policy sama sekali sebelumnya --
 * MaterialUsageHeaderPolicy tidak ada, dan MaterialUsageResource tidak
 * override canCreate(). Tanpa Policy terdaftar dan tanpa `Gate::before`,
 * authorizeAccess() bawaan Filament (bukan cuma canAccess() kosmetik untuk
 * navigasi) jatuh ke Response::allow() -- siapa pun yang login, terlepas
 * dari izin apa pun, bisa membuka halaman ini dan membuat penyesuaian stok
 * material sungguhan lewat StockService.
 */
class MaterialUsageManualCreateAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->material = Material::create([
            'code' => 'MTR001', 'name' => 'PLASTIK',
            'material_category_id' => MaterialCategory::create(['name' => 'KEMASAN'])->id,
            'material_unit_id' => MaterialUnit::create(['name' => 'PCS'])->id,
            'is_active' => true,
        ]);

        MaterialStock::create(['material_id' => $this->material->id, 'qty' => 100]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Material Usages', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    /** @test */
    public function a_user_without_any_permission_cannot_even_open_the_page(): void
    {
        $user = $this->employee();

        $this->assertFalse($user->hasPermission('create_material_usages'));

        $this->actingAs($user)
            ->get(route('filament.admin.resources.material-usages.create'))
            ->assertForbidden();

        $this->assertSame(0, MaterialAdjustment::count());
    }

    /**
     * Butuh view_material_usages SEKALIGUS create_material_usages -- pola
     * "gerbang ganda" Filament yang sama seperti di halaman lain: satu untuk
     * canCreate() eksplisit, satu lagi karena panel tetap membaca izin
     * dasar Resource-nya saat me-render konteksnya (breadcrumb dsb.).
     * Realistis juga: di UI sungguhan, pengguna harus bisa MELIHAT modulnya
     * dulu sebelum bisa mengeklik masuk ke tombol Create-nya.
     *
     * @test
     */
    public function a_user_with_create_material_usages_can_open_the_page_and_create_an_adjustment(): void
    {
        $user = $this->employee(['create_material_usages', 'view_material_usages']);
        $this->actingAs($user);

        $test = Livewire::test(CreateManualUsage::class);

        $key = array_key_first($test->get('data.materialUsages'));

        $test->set('data.note', 'Penyesuaian rutin')
            ->set("data.materialUsages.$key.material_id", $this->material->id)
            ->set("data.materialUsages.$key.qty", 5)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, MaterialAdjustment::count());
        $this->assertEquals(95, MaterialStock::where('material_id', $this->material->id)->value('qty'));
    }

    /** @test */
    public function the_policy_is_reachable_for_this_resource(): void
    {
        $this->assertSame(
            \App\Models\MaterialUsageHeader::class,
            \App\Filament\Admin\Resources\MaterialUsageResource::getModel(),
        );
        $this->assertTrue(class_exists(\App\Policies\MaterialUsageHeaderPolicy::class));
    }
}
