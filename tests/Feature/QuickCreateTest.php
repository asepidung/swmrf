<?php

namespace Tests\Feature;

use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Admin\Resources\MaterialResource\Pages\CreateMaterial;
use App\Filament\Support\QuickCreate;
use App\Models\CustomerSegment;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\User;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Issue #512: tombol "+" (membuat master dari dalam dropdown) diseragamkan.
 *
 * Yang dijaga: tombol hanya tampil bagi pemegang izin membuat master itu --
 * dan karena aksi yang tersembunyi dianggap nonaktif oleh Filament, permintaan
 * yang dikirim langsung tanpa izin pun ditolak --, nama disimpan huruf besar,
 * keunikan tidak peka huruf besar/kecil, dan nilai baru langsung terpilih.
 */
class QuickCreateTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string ...$permissions): User
    {
        $user = User::create([
            'name' => 'Penguji', 'username' => 'uji_'.uniqid(), 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        foreach ($permissions as $permission) {
            $user->permissions()->attach(Permission::firstOrCreate(['name' => $permission], ['module_name' => 'Test', 'description' => $permission])->id);
        }

        return $user->fresh();
    }

    /** @return array<string, array{string}> */
    public static function kinds(): array
    {
        return array_map(fn (string $kind): array => [$kind], array_combine(
            ['materialUnit', 'materialCategory', 'productCategory', 'driver', 'vehicle', 'cattleClass', 'customerSegment', 'customerGroup'],
            ['materialUnit', 'materialCategory', 'productCategory', 'driver', 'vehicle', 'cattleClass', 'customerSegment', 'customerGroup'],
        ));
    }

    /**
     * @test
     *
     * @dataProvider kinds
     */
    public function every_kind_has_a_form_and_its_button_follows_the_create_permission_of_that_master(string $kind): void
    {
        $this->assertNotEmpty(QuickCreate::schema($kind));

        $model = QuickCreate::model($kind);
        $make = fn (): Action => (QuickCreate::action($kind))(Action::make('createOption'));

        $this->actingAs($this->userWith());
        $this->assertTrue($make()->isHidden(), "Tombol + untuk {$kind} tampil tanpa izin.");

        // Izin yang dipakai policy modelnya.
        $policyPermission = $this->createPermissionOf($model);
        $this->actingAs($this->userWith($policyPermission));
        $this->assertFalse($make()->isHidden(), "Tombol + untuk {$kind} tidak tampil bagi pemegang izin {$policyPermission}.");
    }

    /** Nama izin `create` yang dipakai policy sebuah model. */
    private function createPermissionOf(string $model): string
    {
        $policy = \Illuminate\Support\Facades\Gate::getPolicyFor($model);
        $source = file_get_contents((new \ReflectionClass($policy))->getFileName());

        preg_match("/function create\(.*?hasPermission\('([a-z_]+)'\)/s", $source, $match);

        return $match[1] ?? $this->fail("Policy {$policy} tidak punya create() yang dikenali.");
    }

    /** @test */
    public function the_plus_on_the_material_unit_creates_an_uppercase_unit_and_selects_it(): void
    {
        $this->actingAs($this->userWith('create_materials', 'view_materials'));

        $test = Livewire::test(CreateMaterial::class)
            ->callFormComponentAction('material_unit_id', 'createOption', data: ['name' => 'box']);

        $unit = MaterialUnit::where('name', 'BOX')->first();

        $this->assertNotNull($unit, 'Satuan baru harus tersimpan HURUF BESAR.');
        $test->assertFormSet(['material_unit_id' => $unit->id]);
    }

    /** @test */
    public function a_duplicate_name_is_refused_whatever_its_case(): void
    {
        MaterialUnit::create(['name' => 'PCS']);

        $this->actingAs($this->userWith('create_materials', 'view_materials'));

        Livewire::test(CreateMaterial::class)
            ->callFormComponentAction('material_unit_id', 'createOption', data: ['name' => 'pcs'])
            ->assertHasFormComponentActionErrors(['name']);

        $this->assertSame(1, MaterialUnit::count());
    }

    /** @test */
    public function the_segment_plus_on_a_new_customer_is_hidden_without_the_create_segment_permission(): void
    {
        $this->actingAs($this->userWith('create_customers', 'view_customers'));

        Livewire::test(CreateCustomer::class)
            ->assertFormComponentActionHidden('customer_segment_id', 'createOption');
    }

    /**
     * Menyembunyikan tombol saja tidak cukup: permintaan Livewire yang dikirim
     * langsung tanpa izin harus ditolak server.
     *
     * @test
     */
    public function a_raw_request_to_create_a_segment_without_permission_is_refused(): void
    {
        $this->actingAs($this->userWith('create_customers', 'view_customers'));

        Livewire::test(CreateCustomer::class)
            ->call('mountFormComponentAction', 'data.customer_segment_id', 'createOption')
            ->set('mountedFormComponentActionsData.0.name', 'HOTEL')
            ->call('callMountedFormComponentAction');

        $this->assertSame(0, CustomerSegment::count(), 'Tanpa create_customer_segments, permintaan langsung pun harus ditolak.');
    }

    /** @test */
    public function the_segment_plus_on_a_new_customer_works_with_the_create_segment_permission(): void
    {
        $this->actingAs($this->userWith('create_customers', 'view_customers', 'create_customer_segments'));

        $test = Livewire::test(CreateCustomer::class)
            ->callFormComponentAction('customer_segment_id', 'createOption', data: ['name' => 'Hotel']);

        $segment = CustomerSegment::where('name', 'HOTEL')->first();

        $this->assertNotNull($segment, 'Segmen baru harus tersimpan HURUF BESAR.');
        $test->assertFormSet(['customer_segment_id' => $segment->id]);
    }
}
