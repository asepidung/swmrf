<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CashBookResource;
use App\Filament\Admin\Resources\CashBookResource\Pages\ListCashBook;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Buku Kas: uang keluar-masuk yang selama ini tercatat tanpa punya layar.
 *
 * `bank_transactions` sudah terisi sejak 26 Agustus 2026, tetapi tidak ada
 * satu pun halaman yang menampilkannya -- jadi tidak ada cara memeriksa apakah
 * pencatatannya benar selain membuka database.
 *
 * Yang dijaga di sini bukan "halamannya terbuka", melainkan bahwa baris yang
 * lahir dari dokumen lain benar-benar SAMPAI ke buku kas dengan nilai dan arah
 * yang benar. Salah arah tidak menimbulkan error apa pun: uang keluar akan
 * terbaca sebagai uang masuk, dan saldo baru terlihat menyimpang saat
 * rekonsiliasi bank.
 */
class CashBookTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Finance Kas',
            'username' => 'finance_cashbook',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->supplier = Supplier::create([
            'name' => 'H DONI',
            'address' => 'Bogor',
            'pic' => 'Doni',
            'top_days' => 30,
        ]);
    }

    /** DP tunai yang dibayar dari mana pun harus muncul di buku kas. */
    public function test_a_supplier_advance_payment_reaches_the_cash_book_as_money_going_out(): void
    {
        SupplierPayment::create([
            'payment_number' => SupplierPayment::generateNumber(),
            'supplier_id' => $this->supplier->id,
            'source_type' => \App\Models\PurchaseProduct::class,
            'source_id' => 1,
            'payment_date' => now()->toDateString(),
            'method' => SupplierPayment::METHOD_CASH,
            'amount' => 4_000_000,
            'created_by' => $this->user->id,
        ]);

        $row = BankTransaction::latest('id')->first();

        $this->assertNotNull($row, 'DP tidak menghasilkan baris buku kas sama sekali.');
        $this->assertSame('out', $row->type, 'DP adalah uang KELUAR; arah yang salah tidak akan menimbulkan error apa pun.');
        $this->assertEquals(4_000_000, (float) $row->amount);

        Livewire::test(ListCashBook::class)
            ->assertCanSeeTableRecords([$row]);
    }

    /**
     * Halaman ini murni jendela ke dokumen lain, jadi tidak boleh ada jalan
     * mengetik baris kas secara langsung -- itu akan memutus hubungan baris
     * dengan dokumen yang melahirkannya.
     */
    public function test_the_cash_book_is_read_only(): void
    {
        $this->assertFalse(CashBookResource::canCreate());
        $this->assertSame(['index'], array_keys(CashBookResource::getPages()));
    }

    /** Melihat uang perusahaan butuh hak akses tersendiri. */
    public function test_it_is_hidden_from_users_without_the_permission(): void
    {
        $outsider = User::create([
            'name' => 'Gudang',
            'username' => 'gudang_cashbook',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        $this->actingAs($outsider);

        $this->assertFalse(CashBookResource::canViewAny());
        $this->assertFalse(CashBookResource::shouldRegisterNavigation());

        $permission = Permission::firstOrCreate(
            ['name' => 'view_cash_book'],
            ['module_name' => 'Cash Book', 'description' => 'View cash book'],
        );
        $outsider->permissions()->attach($permission->id);

        $this->assertTrue(CashBookResource::canViewAny($outsider->fresh()));
    }

    /** Permission-nya wajib sampai ke server lewat migrasi, bukan seeder. */
    public function test_the_permission_is_delivered_by_migration_not_only_by_the_seeder(): void
    {
        // Seeder tidak dijalankan di test ini; kalau barisnya ada, ia datang
        // dari migrasi. Seeder tidak boleh dijalankan di server hidup karena
        // ia menyetel ulang password superuser.
        $this->assertDatabaseHas('permissions', [
            'name' => 'view_cash_book',
            'module_name' => 'Cash Book',
        ]);
    }

    /** Ekspor PDF harus benar-benar merender, bukan sekadar ada tombolnya. */
    public function test_the_pdf_export_template_renders(): void
    {
        $account = BankAccount::cashAccount();

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'type' => 'in',
            'amount' => 1_500_000,
            'description' => 'Penerimaan piutang',
            'transaction_date' => now()->toDateString(),
        ]);

        $html = View::make('exports.cash-book-pdf', [
            'records' => BankTransaction::with('bankAccount')->get(),
            'title' => 'Buku Kas',
        ])->render();

        $this->assertStringContainsString('Penerimaan piutang', $html);
        $this->assertStringContainsString('1.500.000', $html);
    }
}
