<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialFinding;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\User;
use App\Policies\BankTransactionPolicy;
use App\Policies\MaterialFindingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Policy untuk `BankTransaction` (Buku Kas) dan `MaterialFinding`.
 *
 * Keduanya sempat tidak punya Policy, sehingga Laravel mengizinkan apa saja
 * pada modelnya (fail-open). `ResourceHasPolicyTest` menangkapnya; test ini
 * membuktikan Policy yang ditambahkan benar-benar MENOLAK, bukan sekadar ada.
 *
 * Memakai pengguna berperan `employee`: `hasPermission()` selalu `true` untuk
 * `programmer`, jadi test dengan peran itu selalu lulus tanpa menguji apa pun.
 */
class BankTransactionMaterialFindingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function employee(array $permissions = []): User
    {
        $user = User::create([
            'name' => 'Staf',
            'username' => 'staf_'.uniqid(),
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['module_name' => 'Test', 'description' => $name],
            );
            $user->permissions()->attach($permission->id);
        }

        return $user->fresh();
    }

    private function transaction(): BankTransaction
    {
        return BankTransaction::create([
            'bank_account_id' => BankAccount::cashAccount()->id,
            'type' => 'in',
            'amount' => 100_000,
            'description' => 'Penerimaan piutang',
            'transaction_date' => now()->toDateString(),
        ]);
    }

    private function finding(User $creator): MaterialFinding
    {
        $material = Material::create([
            'code' => 'MTR-POL',
            'name' => 'KERTAS HVS',
            'material_category_id' => MaterialCategory::create(['name' => 'KERTAS'])->id,
            'material_unit_id' => MaterialUnit::create(['name' => 'RIM'])->id,
            'min_stock' => 0,
            'show_in_stock' => true,
        ]);

        return MaterialFinding::create([
            'date' => now()->toDateString(),
            'material_id' => $material->id,
            'qty' => 5,
            'note' => 'ditemukan di rak',
            'created_by' => $creator->id,
        ]);
    }

    public function test_both_models_resolve_to_their_policy(): void
    {
        $this->assertInstanceOf(BankTransactionPolicy::class, Gate::getPolicyFor(BankTransaction::class));
        $this->assertInstanceOf(MaterialFindingPolicy::class, Gate::getPolicyFor(MaterialFinding::class));
    }

    public function test_cash_book_is_readable_only_with_view_cash_book(): void
    {
        $row = $this->transaction();

        $outsider = $this->employee();
        $this->assertFalse($outsider->can('viewAny', BankTransaction::class));
        $this->assertFalse($outsider->can('view', $row));

        $reader = $this->employee(['view_cash_book']);
        $this->assertTrue($reader->can('viewAny', BankTransaction::class));
        $this->assertTrue($reader->can('view', $row));
    }

    public function test_nobody_can_write_a_cash_book_row_through_the_policy(): void
    {
        $row = $this->transaction();

        // Izin apa pun yang berbau Buku Kas/bank tidak boleh membuka tulis.
        $reader = $this->employee(['view_cash_book', 'view_bank_accounts', 'edit_bank_accounts', 'delete_bank_accounts']);

        foreach (['create'] as $ability) {
            $this->assertFalse($reader->can($ability, BankTransaction::class), $ability);
        }
        foreach (['update', 'delete', 'restore', 'forceDelete'] as $ability) {
            $this->assertFalse($reader->can($ability, $row), $ability);
        }
        foreach (['deleteAny', 'restoreAny', 'forceDeleteAny'] as $ability) {
            $this->assertFalse($reader->can($ability, BankTransaction::class), $ability);
        }
    }

    public function test_material_findings_need_record_material_findings(): void
    {
        $row = $this->finding($this->employee());

        // `view_material_stocks` membuka rumpun Materials Stock, tetapi tidak
        // boleh menambah atau menghapus stok bahan.
        $outsider = $this->employee(['view_material_stocks']);

        $this->assertFalse($outsider->can('viewAny', MaterialFinding::class));
        $this->assertFalse($outsider->can('view', $row));
        $this->assertFalse($outsider->can('create', MaterialFinding::class));
        $this->assertFalse($outsider->can('delete', $row));
        $this->assertFalse($outsider->can('deleteAny', MaterialFinding::class));

        $recorder = $this->employee(['record_material_findings']);

        $this->assertTrue($recorder->can('viewAny', MaterialFinding::class));
        $this->assertTrue($recorder->can('view', $row));
        $this->assertTrue($recorder->can('create', MaterialFinding::class));
        $this->assertTrue($recorder->can('delete', $row));
        $this->assertTrue($recorder->can('deleteAny', MaterialFinding::class));
    }

    public function test_a_material_finding_can_never_be_edited_or_restored(): void
    {
        $recorder = $this->employee(['record_material_findings']);
        $row = $this->finding($recorder);

        $this->assertFalse($recorder->can('update', $row));
        $this->assertFalse($recorder->can('restore', $row));
        $this->assertFalse($recorder->can('forceDelete', $row));
        $this->assertFalse($recorder->can('restoreAny', MaterialFinding::class));
        $this->assertFalse($recorder->can('forceDeleteAny', MaterialFinding::class));
    }
}
