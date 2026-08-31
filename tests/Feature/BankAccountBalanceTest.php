<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BankAccountResource\Pages\ListBankAccounts;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Saldo uang diturunkan dari mutasi, tidak disimpan di master data.
 *
 * Keputusan Project Owner: saldo harus berperilaku seperti stok barang, yang
 * berkumpul di tabelnya sendiri dan bukan menempel sebagai angka di master.
 *
 * Alasan teknisnya sama kuatnya. Dulu `bank_accounts.balance` di-increment
 * dan di-decrement setiap ada pembayaran, sehingga ada DUA angka yang
 * sama-sama mengaku benar: kolomnya, dan jumlah baris di `bank_transactions`.
 * Selama keduanya cocok tidak ada yang terasa. Begitu berbeda -- karena satu
 * jalur lupa memperbarui kolom, atau karena sebuah baris kas dihapus -- tidak
 * ada cara menentukan mana yang salah tanpa memeriksa satu per satu.
 *
 * Sekarang tidak ada angka kedua, jadi tidak ada yang bisa menyimpang.
 */
class BankAccountBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Finance Saldo',
            'username' => 'finance_saldo',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    /** Kolomnya benar-benar hilang, bukan sekadar tidak dipakai lagi. */
    public function test_the_master_data_no_longer_carries_a_balance_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('bank_accounts', 'balance'),
            'Kolom balance masih ada di master data; selama ia ada, seseorang akan menulisinya lagi.',
        );
    }

    public function test_the_balance_is_the_sum_of_its_cash_book_entries(): void
    {
        $account = BankAccount::cashAccount();

        BankTransaction::create([
            'bank_account_id' => $account->id, 'type' => 'in', 'amount' => 10_000_000,
            'description' => 'Saldo awal', 'transaction_date' => now()->toDateString(),
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id, 'type' => 'out', 'amount' => 4_000_000,
            'description' => 'DP supplier', 'transaction_date' => now()->toDateString(),
        ]);

        $this->assertSame(6_000_000.0, $account->currentBalance());
    }

    /**
     * Pembayaran hanya menulis SATU tempat.
     *
     * Ini inti perubahannya: DP yang dibayar menggeser saldo semata-mata
     * karena ia menambah baris di buku kas, bukan karena ada kode terpisah
     * yang mengurangi sebuah kolom. Jalur baru mana pun otomatis benar.
     */
    public function test_a_payment_moves_the_balance_only_through_the_cash_book(): void
    {
        $account = BankAccount::cashAccount();

        BankTransaction::create([
            'bank_account_id' => $account->id, 'type' => 'in', 'amount' => 5_000_000,
            'reference_type' => BankAccount::OPENING_BALANCE_REFERENCE,
            'description' => 'Saldo awal', 'transaction_date' => now()->toDateString(),
        ]);

        $supplier = Supplier::create([
            'name' => 'H DONI', 'address' => 'Bogor', 'pic' => 'Doni', 'top_days' => 30,
        ]);

        SupplierPayment::create([
            'payment_number' => SupplierPayment::generateNumber(),
            'supplier_id' => $supplier->id,
            'source_type' => \App\Models\PurchaseProduct::class,
            'source_id' => 1,
            'payment_date' => now()->toDateString(),
            'method' => SupplierPayment::METHOD_CASH,
            'amount' => 2_000_000,
            'created_by' => $this->user->id,
        ]);

        $this->assertSame(3_000_000.0, $account->fresh()->currentBalance());
        $this->assertSame(2, BankTransaction::where('bank_account_id', $account->id)->count());
    }

    /** Saldo awal adalah TRANSAKSI, jadi ia punya tanggal dan jejak. */
    public function test_the_opening_balance_is_recorded_as_a_cash_book_entry(): void
    {
        $account = BankAccount::cashAccount();
        $permission = Permission::firstOrCreate(
            ['name' => 'set_opening_balance'],
            ['module_name' => 'Bank Accounts', 'description' => 'Set opening balance'],
        );
        $this->user->permissions()->attach($permission->id);

        Livewire::test(ListBankAccounts::class)
            ->callTableAction('setOpeningBalance', $account, [
                'transaction_date' => now()->toDateString(),
                'amount_input' => '10.000.000',
            ]);

        $entry = $account->fresh()->openingBalanceEntry();

        $this->assertNotNull($entry, 'Saldo awal tidak menghasilkan baris buku kas.');
        $this->assertSame('in', $entry->type);
        // Format Indonesia: "10.000.000" harus terbaca 10 juta, bukan 10.
        $this->assertEquals(10_000_000, (float) $entry->amount);
        $this->assertSame(10_000_000.0, $account->fresh()->currentBalance());
    }

    /** Menyetel ulang mengganti titik awalnya, bukan menambah baris kedua. */
    public function test_setting_the_opening_balance_twice_replaces_it(): void
    {
        $account = BankAccount::cashAccount();
        $permission = Permission::firstOrCreate(
            ['name' => 'set_opening_balance'],
            ['module_name' => 'Bank Accounts', 'description' => 'Set opening balance'],
        );
        $this->user->permissions()->attach($permission->id);

        foreach (['10.000.000', '7.500.000'] as $amount) {
            Livewire::test(ListBankAccounts::class)
                ->callTableAction('setOpeningBalance', $account, [
                    'transaction_date' => now()->toDateString(),
                    'amount_input' => $amount,
                ]);
        }

        $this->assertSame(1, BankTransaction::where('bank_account_id', $account->id)->count());
        $this->assertSame(7_500_000.0, $account->fresh()->currentBalance());
    }

    /**
     * Rekening yang SUDAH dipakai tetap boleh diberi saldo awal.
     *
     * Ini kondisi normal proyek ini, bukan kasus pinggiran: sistem ini
     * refactor dari aplikasi lama, hutang dan piutang sudah berjalan, jadi
     * pembukuan dimulai dari tengah dan titik awalnya baru dipasang
     * belakangan dengan tanggal mundur.
     *
     * Versi pertama aturan ini salah -- ia mengunci PEMBUATAN, bukan hanya
     * pengubahan, sehingga dua rekening di server tidak bisa diberi saldo
     * awal sama sekali.
     */
    public function test_an_account_that_has_been_used_can_still_receive_its_first_opening_balance(): void
    {
        $account = BankAccount::cashAccount();

        BankTransaction::create([
            'bank_account_id' => $account->id, 'type' => 'out', 'amount' => 100_000,
            'reference_type' => SupplierPayment::class, 'reference_id' => 1,
            'description' => 'DP', 'transaction_date' => now()->toDateString(),
        ]);

        $this->assertTrue(
            $account->fresh()->canSetOpeningBalance(),
            'Rekening yang sudah dipakai justru yang paling butuh saldo awal.',
        );
    }

    /**
     * Tapi begitu saldo awalnya ADA dan sudah ditumpuki mutasi, ia terkunci.
     *
     * Menggesernya akan memindahkan seluruh riwayat di atasnya, dan tidak ada
     * yang akan menyadarinya -- angkanya cuma bergeser.
     */
    public function test_the_opening_balance_locks_once_it_has_entries_on_top_of_it(): void
    {
        $account = BankAccount::cashAccount();

        BankTransaction::create([
            'bank_account_id' => $account->id, 'type' => 'in', 'amount' => 5_000_000,
            'reference_type' => BankAccount::OPENING_BALANCE_REFERENCE,
            'description' => 'Saldo awal', 'transaction_date' => now()->toDateString(),
        ]);

        // Masih boleh diperbaiki: belum ada apa pun di atasnya.
        $this->assertTrue($account->fresh()->canSetOpeningBalance());

        BankTransaction::create([
            'bank_account_id' => $account->id, 'type' => 'out', 'amount' => 100_000,
            'reference_type' => SupplierPayment::class, 'reference_id' => 1,
            'description' => 'DP', 'transaction_date' => now()->toDateString(),
        ]);

        $this->assertFalse($account->fresh()->canSetOpeningBalance());
    }

    /**
     * Koreksi setelah itu lewat Penyesuaian, dan boleh berkali-kali.
     *
     * Padanannya di barang adalah Stock Opname: kalau stok fisik berbeda dari
     * catatan, jawabannya mencatat selisihnya -- bukan mengubah penerimaan
     * barang yang pertama.
     */
    public function test_a_cash_adjustment_can_be_recorded_repeatedly_and_moves_the_balance(): void
    {
        $account = BankAccount::cashAccount();
        $this->givePermission('adjust_cash_balance');

        Livewire::test(ListBankAccounts::class)
            ->callTableAction('adjustBalance', $account, [
                'transaction_date' => now()->toDateString(),
                'direction' => 'in',
                'amount_input' => '1.000.000',
                'description' => 'Selisih rekening koran Agustus',
            ])
            ->callTableAction('adjustBalance', $account, [
                'transaction_date' => now()->toDateString(),
                'direction' => 'out',
                'amount_input' => '250.000',
                'description' => 'Biaya admin bank',
            ]);

        $this->assertSame(750_000.0, $account->fresh()->currentBalance());
        $this->assertSame(2, BankTransaction::where('reference_type', BankAccount::ADJUSTMENT_REFERENCE)->count());

        // Alasannya ikut tersimpan -- itu yang membedakannya dari menulis
        // ulang angka diam-diam.
        $this->assertStringContainsString(
            'Selisih rekening koran Agustus',
            BankTransaction::where('reference_type', BankAccount::ADJUSTMENT_REFERENCE)->first()->description,
        );
    }

    /** Menggeser saldo butuh haknya sendiri, terpisah dari saldo awal. */
    public function test_a_cash_adjustment_requires_its_own_permission(): void
    {
        $account = BankAccount::cashAccount();

        // Bukan programmer: peran itu melewati seluruh pemeriksaan hak akses,
        // jadi memakainya di sini akan membuat test lulus tanpa menguji apa pun.
        $staff = User::create([
            'name' => 'Staff Finance', 'username' => 'staff_adjust', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        foreach (['view_bank_accounts', 'set_opening_balance'] as $name) {
            $staff->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => $name],
                    ['module_name' => 'Bank Accounts', 'description' => $name],
                )->id
            );
        }

        $this->actingAs($staff);

        // Punya hak saldo awal saja tidak cukup untuk menyesuaikan saldo.
        Livewire::test(ListBankAccounts::class)
            ->assertTableActionHidden('adjustBalance', $account)
            ->assertTableActionVisible('setOpeningBalance', $account);
    }

    private function givePermission(string $name): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => $name],
            ['module_name' => 'Bank Accounts', 'description' => $name],
        );

        $this->user->permissions()->attach($permission->id);
        $this->actingAs($this->user->fresh());
    }

    /** Menciptakan uang butuh hak akses tersendiri. */
    public function test_setting_an_opening_balance_requires_its_own_permission(): void
    {
        $account = BankAccount::cashAccount();

        $outsider = User::create([
            'name' => 'Gudang', 'username' => 'gudang_saldo', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);
        $outsider->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'view_bank_accounts'],
                ['module_name' => 'Bank Accounts', 'description' => 'View bank accounts'],
            )->id
        );

        $this->actingAs($outsider);

        Livewire::test(ListBankAccounts::class)
            ->assertTableActionHidden('setOpeningBalance', $account);
    }
}
