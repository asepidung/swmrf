<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\GradeResource;
use App\Filament\Admin\Resources\GradeResource\Pages\CreateGrade;
use App\Filament\Admin\Resources\GradeResource\Pages\ListGrades;
use App\Filament\Admin\Resources\WarehouseResource;
use App\Filament\Admin\Resources\WarehouseResource\Pages\CreateWarehouse;
use App\Filament\Admin\Resources\WarehouseResource\Pages\ListWarehouses;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataGradeWarehouseTest extends TestCase
{
    use RefreshDatabase;

    protected User $programmer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->programmer = User::create([
            'name' => 'Programmer',
            'username' => 'programmer_test',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);
    }

    protected function makeEmployee(array $permissionNames = []): User
    {
        $employee = User::create([
            'name' => 'Employee',
            'username' => 'employee_' . uniqid(),
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['module_name' => 'Test', 'description' => $name]
            );
            $employee->permissions()->attach($permission->id);
        }

        return $employee;
    }

    /**
     * Digit grade pada barcode 26 karakter mengacu langsung ke id di tabel
     * grades. Kalau seeder menghasilkan id yang berbeda, seluruh barcode lama
     * berubah arti — karena itu id-nya dikunci dan diuji secara eksplisit.
     *
     * @test
     */
    public function it_seeds_grades_with_locked_ids_matching_the_barcode_digit()
    {
        $this->seed(DatabaseSeeder::class);

        $expected = [
            1 => 'CHILL',
            2 => 'FROZEN',
            3 => 'A',
            4 => 'B',
            5 => 'R',
        ];

        foreach ($expected as $id => $name) {
            $this->assertDatabaseHas('grades', [
                'id' => $id,
                'name' => $name,
                'is_active' => true,
            ]);
        }

        $this->assertSame(count($expected), Grade::count());
    }

    /** @test */
    public function it_seeds_grades_idempotently_without_shifting_ids()
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, Grade::count());
        $this->assertSame('CHILL', Grade::find(1)->name);
        $this->assertSame('R', Grade::find(5)->name);
    }

    /** @test */
    public function it_seeds_the_permissions_for_grades_and_warehouses()
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['grades', 'warehouses'] as $module) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $this->assertDatabaseHas('permissions', ['name' => $action . '_' . $module]);
            }
        }
    }

    /** @test */
    public function it_lists_grades_for_a_user_holding_the_permission()
    {
        Grade::create(['name' => 'CHILL', 'is_active' => true]);

        Livewire::actingAs($this->makeEmployee(['view_grades']))
            ->test(ListGrades::class)
            ->assertSuccessful()
            ->assertSee('CHILL');
    }

    /** @test */
    public function it_hides_grades_from_a_user_without_the_permission()
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee);

        $this->assertFalse(GradeResource::canViewAny());
        $this->assertFalse(GradeResource::shouldRegisterNavigation());
    }

    /** @test */
    public function it_shows_grades_in_navigation_for_a_programmer()
    {
        $this->actingAs($this->programmer);

        $this->assertTrue(GradeResource::canViewAny());
        $this->assertTrue(GradeResource::shouldRegisterNavigation());
        $this->assertTrue(WarehouseResource::canViewAny());
        $this->assertTrue(WarehouseResource::shouldRegisterNavigation());
    }

    /** @test */
    public function it_creates_a_grade_and_forces_the_name_to_uppercase()
    {
        Livewire::actingAs($this->programmer)
            ->test(CreateGrade::class)
            ->fillForm([
                'name' => 'chill',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('grades', ['name' => 'CHILL']);
    }

    /** @test */
    public function it_rejects_a_duplicate_grade_name()
    {
        Grade::create(['name' => 'CHILL', 'is_active' => true]);

        Livewire::actingAs($this->programmer)
            ->test(CreateGrade::class)
            ->fillForm(['name' => 'CHILL', 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['name']);
    }

    /** @test */
    public function it_creates_a_warehouse_and_forces_code_and_name_to_uppercase()
    {
        Livewire::actingAs($this->programmer)
            ->test(CreateWarehouse::class)
            ->fillForm([
                'code' => 'jonggol',
                'name' => 'gudang jonggol',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('warehouses', [
            'code' => 'JONGGOL',
            'name' => 'GUDANG JONGGOL',
        ]);
    }

    /** @test */
    public function it_rejects_a_duplicate_warehouse_code()
    {
        Warehouse::create(['code' => 'JONGGOL', 'name' => 'JONGGOL', 'is_active' => true]);

        Livewire::actingAs($this->programmer)
            ->test(CreateWarehouse::class)
            ->fillForm(['code' => 'JONGGOL', 'name' => 'LAIN', 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['code']);
    }

    /** @test */
    public function it_lists_warehouses_for_a_user_holding_the_permission()
    {
        Warehouse::create(['code' => 'PERUM', 'name' => 'PERUM', 'is_active' => true]);

        Livewire::actingAs($this->makeEmployee(['view_warehouses']))
            ->test(ListWarehouses::class)
            ->assertSuccessful()
            ->assertSee('PERUM');
    }

    /** @test */
    public function it_records_an_activity_log_when_a_grade_changes()
    {
        $grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Grade::class,
            'subject_id' => $grade->id,
            'event' => 'created',
        ]);

        $grade->update(['name' => 'CHILLED']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Grade::class,
            'subject_id' => $grade->id,
            'event' => 'updated',
        ]);
    }

    /** @test */
    public function it_records_an_activity_log_when_a_warehouse_changes()
    {
        $warehouse = Warehouse::create(['code' => 'PERUM', 'name' => 'PERUM', 'is_active' => true]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Warehouse::class,
            'subject_id' => $warehouse->id,
            'event' => 'created',
        ]);
    }
}
