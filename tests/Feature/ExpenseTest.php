<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Langkah 1 dari issue #453: migrasi + model + policy Expense. Belum ada
 * Resource -- itu langkah 2. Aturan di sini murni model: dua jenis
 * dokumen (advance/reimburse), transisi status, dan setiap pergerakan kas
 * lewat BankTransaction.
 */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private BankAccount $cash;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'employee', 'is_active' => true]));

        $this->cash = BankAccount::cashAccount();
        // Bukan "ATK" -- nama itu sudah diseed migrasi tabelnya, tabrakan
        // dengan unique index.
        $this->category = ExpenseCategory::create(['name' => 'FIXTURE CATEGORY']);
    }

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'expense_date' => now()->toDateString(),
            'bank_account_id' => $this->cash->id,
            'expense_category_id' => $this->category->id,
            'recipient_name' => 'budi',
        ], $overrides);
    }

    // =========================================================================
    // Migrasi: izin ada
    // =========================================================================

    /** @test */
    public function the_five_permissions_exist_after_migrating(): void
    {
        foreach ([
            'view_expenses', 'create_expenses', 'edit_expenses',
            'delete_expenses', 'manage_expense_categories',
        ] as $name) {
            $this->assertTrue(Permission::where('name', $name)->exists(), "Izin $name tidak ada.");
        }
    }

    // =========================================================================
    // Model dasar
    // =========================================================================

    /** @test */
    public function creating_an_expense_autogenerates_a_prefixed_number_and_uppercases_the_recipient(): void
    {
        $expense = Expense::createAdvance($this->baseData(['advance_amount' => 100000]));

        $this->assertStringStartsWith('EXP#'.date('y'), $expense->expense_number);
        $this->assertSame('BUDI', $expense->recipient_name);
    }

    /** @test */
    public function an_expense_defaults_to_the_main_cash_account(): void
    {
        $data = $this->baseData(['advance_amount' => 50000]);
        unset($data['bank_account_id']);

        $expense = Expense::createAdvance($data);

        $this->assertSame($this->cash->id, $expense->bank_account_id);
    }

    // =========================================================================
    // Advance: uang keluar dulu, kredit kembali/tambah saat settle
    // =========================================================================

    /** @test */
    public function an_advance_moves_cash_out_immediately_and_stays_open(): void
    {
        $expense = Expense::createAdvance($this->baseData(['advance_amount' => 100000]));

        $this->assertSame(Expense::STATUS_OPEN, $expense->status);
        $this->assertSame(-100000.0, $this->cash->fresh()->currentBalance());
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Expense::class, 'reference_id' => $expense->id,
            'type' => 'out', 'amount' => 100000,
        ]);
    }

    /** @test */
    public function settling_an_advance_for_less_than_the_receipt_returns_the_difference(): void
    {
        $expense = Expense::createAdvance($this->baseData(['advance_amount' => 100000]));

        $expense->settle(85000);

        $this->assertSame(Expense::STATUS_SETTLED, $expense->fresh()->status);
        $this->assertSame(85000.0, (float) $expense->fresh()->receipt_amount);
        $this->assertSame(15000.0, $expense->fresh()->balance_due);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Expense::class, 'reference_id' => $expense->id,
            'type' => 'in', 'amount' => 15000,
        ]);
        // Keluar 100.000, masuk kembali 15.000 -- bersih 85.000 yang benar-benar dibelanjakan.
        $this->assertSame(-85000.0, $this->cash->fresh()->currentBalance());
    }

    /** @test */
    public function settling_an_advance_for_more_than_given_takes_out_the_difference(): void
    {
        $expense = Expense::createAdvance($this->baseData(['advance_amount' => 100000]));

        $expense->settle(120000);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Expense::class, 'reference_id' => $expense->id,
            'type' => 'out', 'amount' => 20000,
        ]);
        $this->assertSame(-120000.0, $this->cash->fresh()->currentBalance());
    }

    /** @test */
    public function settling_an_advance_for_exactly_the_amount_given_creates_no_extra_transaction(): void
    {
        $expense = Expense::createAdvance($this->baseData(['advance_amount' => 100000]));

        $expense->settle(100000);

        $this->assertSame(1, BankTransaction::where('reference_type', Expense::class)
            ->where('reference_id', $expense->id)->count());
    }

    /** @test */
    public function settling_twice_only_ever_records_one_settlement(): void
    {
        $expense = Expense::createAdvance($this->baseData(['advance_amount' => 100000]));

        $expense->settle(85000);

        $this->expectException(\RuntimeException::class);

        $expense->fresh()->settle(85000);
    }

    // =========================================================================
    // Reimburse: satu transaksi keluar, langsung Settled
    // =========================================================================

    /** @test */
    public function a_reimbursement_is_a_single_outflow_and_immediately_settled(): void
    {
        $expense = Expense::createReimburse($this->baseData(['receipt_amount' => 50000]));

        $this->assertSame(Expense::STATUS_SETTLED, $expense->status);
        $this->assertSame(1, $expense->transactions()->count());
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Expense::class, 'reference_id' => $expense->id,
            'type' => 'out', 'amount' => 50000,
        ]);
    }

    // =========================================================================
    // Batal & hapus
    // =========================================================================

    /** @test */
    public function cancelling_an_open_advance_returns_the_full_amount_and_keeps_the_row(): void
    {
        $expense = Expense::createAdvance($this->baseData(['advance_amount' => 100000]));

        $expense->cancel();

        $this->assertSame(Expense::STATUS_CANCELLED, $expense->fresh()->status);
        $this->assertSame(0.0, $this->cash->fresh()->currentBalance());
        $this->assertNotSoftDeleted('expenses', ['id' => $expense->id]);
    }

    /** @test */
    public function a_settled_advance_cannot_be_cancelled(): void
    {
        $expense = Expense::createAdvance($this->baseData(['advance_amount' => 100000]));
        $expense->settle(100000);

        $this->expectException(\RuntimeException::class);

        $expense->fresh()->cancel();
    }

    /** @test */
    public function deleting_an_open_expense_refunds_it_automatically(): void
    {
        $expense = Expense::createAdvance($this->baseData(['advance_amount' => 100000]));

        $expense->delete();

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
        $this->assertSame(0.0, $this->cash->fresh()->currentBalance());
    }

    /** @test */
    public function a_settled_expense_cannot_be_deleted(): void
    {
        $expense = Expense::createReimburse($this->baseData(['receipt_amount' => 50000]));

        $this->expectException(\Exception::class);

        $expense->delete();
    }

    /** @test */
    public function a_settled_expense_cannot_be_edited(): void
    {
        $expense = Expense::createReimburse($this->baseData(['receipt_amount' => 50000]));

        $this->expectException(\Exception::class);

        $expense->update(['description' => 'ubah setelah settled']);
    }

    /** @test */
    public function an_open_expense_can_still_be_edited(): void
    {
        $expense = Expense::createAdvance($this->baseData(['advance_amount' => 100000]));

        $expense->update(['description' => 'catatan baru']);

        $this->assertSame('catatan baru', $expense->fresh()->description);
    }

    // =========================================================================
    // ExpenseCategory
    // =========================================================================

    /** @test */
    public function a_category_still_used_cannot_be_deleted(): void
    {
        Expense::createAdvance($this->baseData(['advance_amount' => 10000]));

        $this->expectException(\Exception::class);

        $this->category->delete();
    }

    /** @test */
    public function an_unused_category_can_be_deleted(): void
    {
        $unused = ExpenseCategory::create(['name' => 'lain-lain']);

        $unused->delete();

        $this->assertSoftDeleted('expense_categories', ['id' => $unused->id]);
    }

    /** @test */
    public function a_category_name_is_uppercased_on_create(): void
    {
        $category = ExpenseCategory::create(['name' => 'bensin']);

        $this->assertSame('BENSIN', $category->name);
    }
}
