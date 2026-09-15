<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MutationResource\Pages\EditMutation;
use App\Filament\Admin\Resources\MutationResource\Pages\ScanMutation;
use App\Models\BeefStock;
use App\Models\Grade;
use App\Models\Mutation;
use App\Models\MutationItem;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran Mutation, 15 September 2026.
 */
class MutationSusulanTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Warehouse $from;
    private Warehouse $to;
    private Grade $grade;

    private User $seedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Mutation::booted() hanya mengisi created_by kalau auth()->check()
        // -- fixture di sini dibuat SEBELUM actingAs(), jadi harus diisi
        // eksplisit supaya tidak menabrak NOT NULL.
        $this->seedUser = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'SIRLOIN', 'code' => 'MT001', 'category_id' => $category->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);
        $this->from = Warehouse::create(['code' => 'JGL', 'name' => 'JONGGOL', 'is_active' => true]);
        $this->to = Warehouse::create(['code' => 'PRM', 'name' => 'PERUM', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'CHILL', 'is_active' => true]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach (array_unique([...$permissionNames, 'view_mutations']) as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Mutations', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function draftMutation(): Mutation
    {
        return Mutation::create([
            'mutation_date' => now()->format('Y-m-d'), 'from_warehouse_id' => $this->from->id,
            'to_warehouse_id' => $this->to->id, 'status' => 'DRAFT', 'created_by' => $this->seedUser->id,
        ]);
    }

    private function stock(string $barcode): BeefStock
    {
        return BeefStock::create([
            'barcode' => $barcode, 'product_id' => $this->product->id, 'warehouse_id' => $this->from->id,
            'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1,
            'pack_date' => now(), 'origin' => 'BONING', 'status' => 'IN_STOCK',
        ]);
    }

    // =========================================================================
    // addBarcode() butuh edit_mutations
    // =========================================================================

    /** @test */
    public function scanning_without_the_permission_does_not_move_stock(): void
    {
        $mutation = $this->draftMutation();
        $this->stock('FX-NOPERM');

        Livewire::actingAs($this->employee())
            ->test(ScanMutation::class, ['record' => $mutation])
            ->set('barcode', 'FX-NOPERM')
            ->call('addBarcode');

        $this->assertDatabaseHas('beef_stocks', ['barcode' => 'FX-NOPERM']);
        $this->assertSame(0, MutationItem::count());
    }

    /** @test */
    public function scanning_with_the_permission_moves_stock(): void
    {
        $mutation = $this->draftMutation();
        $this->stock('FX-WITHPERM');

        Livewire::actingAs($this->employee(['edit_mutations']))
            ->test(ScanMutation::class, ['record' => $mutation])
            ->set('barcode', 'FX-WITHPERM')
            ->call('addBarcode');

        $this->assertDatabaseMissing('beef_stocks', ['barcode' => 'FX-WITHPERM']);
        $this->assertDatabaseHas('mutation_items', ['mutation_id' => $mutation->id, 'barcode' => 'FX-WITHPERM']);
    }

    // =========================================================================
    // Unscan: izin DAN status -- tab basi tidak boleh mengembalikan stok
    // =========================================================================

    /** @test */
    public function unscanning_without_the_permission_leaves_the_item_in_place(): void
    {
        $mutation = $this->draftMutation();
        $item = MutationItem::create([
            'mutation_id' => $mutation->id, 'barcode' => 'FX-UNSCAN', 'product_id' => $this->product->id,
            'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1, 'pack_date' => now(), 'origin' => 'BONING',
        ]);

        Livewire::actingAs($this->employee())
            ->test(ScanMutation::class, ['record' => $mutation])
            ->mountTableAction('delete', $item->id)
            ->callMountedTableAction();

        $this->assertDatabaseHas('mutation_items', ['id' => $item->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('beef_stocks', ['barcode' => 'FX-UNSCAN']);
    }

    /** @test */
    public function unscanning_after_the_mutation_has_been_sent_returns_nothing_to_stock(): void
    {
        $mutation = $this->draftMutation();
        $item = MutationItem::create([
            'mutation_id' => $mutation->id, 'barcode' => 'FX-STALE', 'product_id' => $this->product->id,
            'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1, 'pack_date' => now(), 'origin' => 'BONING',
        ]);

        $test = Livewire::actingAs($this->employee(['edit_mutations']))
            ->test(ScanMutation::class, ['record' => $mutation]);

        // "Sesi lain" mengirim mutasinya.
        Mutation::whereKey($mutation->id)->update(['status' => 'SENT']);

        $test->mountTableAction('delete', $item->id)->callMountedTableAction();

        $this->assertDatabaseHas('mutation_items', ['id' => $item->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('beef_stocks', ['barcode' => 'FX-STALE']);
    }

    // =========================================================================
    // to_warehouse_id dibekukan sesudah DRAFT
    // =========================================================================

    /** @test */
    public function the_destination_warehouse_cannot_be_changed_after_sending(): void
    {
        $mutation = $this->draftMutation();
        $mutation->update(['status' => 'SENT']);
        $user = $this->employee(['edit_mutations']);

        Livewire::actingAs($user)
            ->test(EditMutation::class, ['record' => $mutation->getRouteKey()])
            ->fillForm(['to_warehouse_id' => $this->from->id])
            ->call('save');

        $this->assertSame($this->to->id, $mutation->fresh()->to_warehouse_id);
    }

    // =========================================================================
    // EditMutation Delete: ikut aturan ViewMutation (DRAFT + kosong)
    // =========================================================================

    /** @test */
    public function editing_a_draft_mutation_with_items_hides_the_delete_button(): void
    {
        $mutation = $this->draftMutation();
        MutationItem::create([
            'mutation_id' => $mutation->id, 'barcode' => 'FX-HASITEM', 'product_id' => $this->product->id,
            'grade_id' => $this->grade->id, 'weight' => 10.5, 'qty_pcs' => 1, 'pack_date' => now(), 'origin' => 'BONING',
        ]);

        Livewire::actingAs($this->employee(['edit_mutations', 'delete_mutations']))
            ->test(EditMutation::class, ['record' => $mutation->getRouteKey()])
            ->assertActionHidden('delete');
    }

    // =========================================================================
    // Route cetak: mutasi & bukti bayar piutang butuh izin modulnya
    // =========================================================================

    /** @test */
    public function the_mutation_print_route_requires_view_mutations(): void
    {
        $mutation = $this->draftMutation();
        $orangLuar = $this->employee();
        $orangLuar->permissions()->detach(); // hapus bahkan view_mutations

        $this->actingAs($orangLuar)
            ->get(route('filament.admin.resources.mutations.print', ['record' => $mutation->id]))
            ->assertForbidden();

        $orangLuar->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_mutations'], ['module_name' => 'x', 'description' => 'x'])->id
        );

        $this->actingAs($orangLuar->fresh())
            ->get(route('filament.admin.resources.mutations.print', ['record' => $mutation->id]))
            ->assertSuccessful();
    }

    /** @test */
    public function the_payment_receipt_print_route_requires_view_receivables(): void
    {
        $group = \App\Models\CustomerGroup::create(['name' => 'FX GROUP']);
        $bankAccount = \App\Models\BankAccount::create([
            'initial' => 'FXB', 'bank_name' => 'BANK FIXTURE', 'account_number' => '000',
            'account_holder' => 'X', 'is_active' => true,
        ]);
        $payment = Payment::create([
            'customer_group_id' => $group->id, 'bank_account_id' => $bankAccount->id,
            'payment_date' => now()->format('Y-m-d'), 'amount' => 100000, 'total_deduction' => 0,
        ]);

        $orangLuar = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        $this->actingAs($orangLuar)
            ->get(route('print.payment-receipt', ['id' => $payment->id]))
            ->assertForbidden();

        $orangLuar->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_receivables'], ['module_name' => 'x', 'description' => 'x'])->id
        );

        $this->actingAs($orangLuar->fresh())
            ->get(route('print.payment-receipt', ['id' => $payment->id]))
            ->assertSuccessful();
    }
}
