<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BankAccountResource;
use App\Filament\Admin\Resources\BankAccountResource\Pages\ListBankAccounts;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran BankAccount, 15 September 2026 -- Temuan 9.
 *
 * Laporan riset menduga `->visible()` pada aksi setOpeningBalance/
 * adjustBalance cuma kosmetik render tombol, dan memanggil
 * `mountTableAction()`/`callMountedTableAction()` langsung (meniru DevTools/
 * replay request) bisa melewatinya. DIVERIFIKASI EMPIRIS di sini, dan dugaan
 * itu TIDAK terbukti: `isDisabled()` Filament (diperiksa `mountTableAction()`
 * sebelum eksekusi apa pun) memanggil `isHidden()`, dan `isHidden()` sungguh
 * memeriksa `isAuthorized()` -- jadi `->visible()`/`->authorize()` memang
 * menggerbangi eksekusinya, bukan cuma tampilan tombol.
 *
 * `->authorize()` dipakai di kode (bukan `->visible()`) supaya niatnya
 * eksplisit sebagai gerbang otorisasi. Test dua arah ini (tanpa izin -> 0
 * baris, dengan izin -> baris lahir) dipertahankan permanen sebagai penjaga
 * -- bukti yang lebih kuat daripada pemindaian string mana pun.
 */
class BankAccountAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function account(): BankAccount
    {
        return BankAccount::create([
            'initial' => 'BCA', 'bank_name' => 'BANK CENTRAL ASIA',
            'account_number' => '1234567890', 'account_holder' => 'WIJAYA MEAT',
            'is_active' => true,
        ]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        foreach ($permissionNames as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Bank Accounts', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    // =====================================================================
    // Temuan 9 -- otorisasi aksi setOpeningBalance ditegakkan, bukan kosmetik
    // =====================================================================

    /** @test */
    public function calling_set_opening_balance_directly_without_the_permission_creates_nothing(): void
    {
        $account = $this->account();
        $user = $this->employee(['view_bank_accounts']); // TANPA set_opening_balance

        $test = Livewire::actingAs($user)->test(ListBankAccounts::class);
        $test->mountTableAction('setOpeningBalance', $account->getKey());
        $test->set('mountedTableActionsData.0.amount_input', '1.000.000');
        $test->set('mountedTableActionsData.0.transaction_date', now()->toDateString());
        $test->callMountedTableAction();

        $this->assertSame(0, BankTransaction::count());
    }

    /** @test */
    public function calling_set_opening_balance_directly_with_the_permission_actually_works(): void
    {
        $account = $this->account();
        $user = $this->employee(['view_bank_accounts', 'set_opening_balance']);

        $test = Livewire::actingAs($user)->test(ListBankAccounts::class);
        $test->mountTableAction('setOpeningBalance', $account->getKey());
        $test->set('mountedTableActionsData.0.amount_input', '1.000.000');
        $test->set('mountedTableActionsData.0.transaction_date', now()->toDateString());
        $test->callMountedTableAction();

        $this->assertSame(1, BankTransaction::count());
        $this->assertSame(1000000.0, (float) BankTransaction::first()->amount);
    }

    /** @test */
    public function calling_set_opening_balance_directly_without_permission_leaves_a_locked_entry_untouched(): void
    {
        $account = $this->account();
        $account->transactions()->create([
            'type' => 'in', 'amount' => 500000,
            'reference_type' => BankAccount::OPENING_BALANCE_REFERENCE,
            'description' => 'Opening', 'transaction_date' => now()->toDateString(),
        ]);
        // Mutasi lain di atasnya -- canSetOpeningBalance() sekarang false.
        $account->transactions()->create([
            'type' => 'in', 'amount' => 100000,
            'reference_type' => null, 'description' => 'Lain', 'transaction_date' => now()->toDateString(),
        ]);

        $user = $this->employee(['view_bank_accounts', 'set_opening_balance']);

        $test = Livewire::actingAs($user)->test(ListBankAccounts::class);
        $test->mountTableAction('setOpeningBalance', $account->getKey());
        $test->set('mountedTableActionsData.0.amount_input', '9.999.999');
        $test->set('mountedTableActionsData.0.transaction_date', now()->toDateString());
        $test->callMountedTableAction();

        $this->assertSame(
            500000.0,
            (float) BankTransaction::where('reference_type', BankAccount::OPENING_BALANCE_REFERENCE)->first()->amount,
            'canSetOpeningBalance() harus tetap menahan penimpaan saldo awal yang sudah terkunci.',
        );
    }

    /** @test */
    public function calling_adjust_balance_directly_without_the_permission_creates_nothing(): void
    {
        $account = $this->account();
        $user = $this->employee(['view_bank_accounts']); // TANPA adjust_cash_balance

        $test = Livewire::actingAs($user)->test(ListBankAccounts::class);
        $test->mountTableAction('adjustBalance', $account->getKey());
        $test->set('mountedTableActionsData.0.direction', 'in');
        $test->set('mountedTableActionsData.0.amount_input', '1.000.000');
        $test->set('mountedTableActionsData.0.transaction_date', now()->toDateString());
        $test->set('mountedTableActionsData.0.description', 'Koreksi');
        $test->callMountedTableAction();

        $this->assertSame(0, BankTransaction::count());
    }

    // =====================================================================
    // Temuan 10 -- race condition saldo awal dikunci
    // =====================================================================

    /** @test */
    public function setting_the_opening_balance_twice_overwrites_instead_of_duplicating(): void
    {
        $account = $this->account();
        $user = $this->employee(['view_bank_accounts', 'set_opening_balance']);

        foreach (['1.000.000', '2.000.000'] as $amount) {
            $test = Livewire::actingAs($user)->test(ListBankAccounts::class);
            $test->mountTableAction('setOpeningBalance', $account->getKey());
            $test->set('mountedTableActionsData.0.amount_input', $amount);
            $test->set('mountedTableActionsData.0.transaction_date', now()->toDateString());
            $test->callMountedTableAction();
        }

        $this->assertSame(
            1,
            BankTransaction::where('reference_type', BankAccount::OPENING_BALANCE_REFERENCE)->count(),
            'Menyetel ulang saldo awal harus menimpa baris yang sama, bukan menambah baris kedua.',
        );
        $this->assertSame(2000000.0, (float) BankTransaction::first()->amount);
    }

    // =====================================================================
    // Temuan 11 -- menu Bank Account disembunyikan dari yang tidak berhak
    // =====================================================================

    /** @test */
    public function the_bank_account_menu_is_hidden_from_a_user_without_view_bank_accounts(): void
    {
        $this->actingAs($this->employee());

        $this->assertFalse(BankAccountResource::shouldRegisterNavigation());
        $this->assertFalse(BankAccountResource::canViewAny());
    }

    /** @test */
    public function the_bank_account_menu_shows_for_a_holder_of_view_bank_accounts(): void
    {
        $this->actingAs($this->employee(['view_bank_accounts']));

        $this->assertTrue(BankAccountResource::shouldRegisterNavigation());
        $this->assertTrue(BankAccountResource::canViewAny());
    }

    // =====================================================================
    // Temuan 12 -- BankAccount tidak punya jejak audit
    // =====================================================================

    /** @test */
    public function creating_a_bank_account_is_logged(): void
    {
        $account = $this->account();

        $this->assertTrue(
            \Spatie\Activitylog\Models\Activity::where('subject_type', BankAccount::class)
                ->where('subject_id', $account->id)
                ->where('event', 'created')
                ->exists(),
        );
    }

    // =====================================================================
    // Temuan 13 -- hapus Bank Account yang masih dipakai: pesan ramah
    // =====================================================================

    /** @test */
    public function deleting_a_bank_account_still_in_use_shows_a_friendly_notification(): void
    {
        $account = $this->account();
        $account->transactions()->create([
            'type' => 'in', 'amount' => 500000,
            'reference_type' => BankAccount::OPENING_BALANCE_REFERENCE,
            'description' => 'Opening', 'transaction_date' => now()->toDateString(),
        ]);

        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        Livewire::actingAs($user)
            ->test(\App\Filament\Admin\Resources\BankAccountResource\Pages\EditBankAccount::class, ['record' => $account->getRouteKey()])
            ->callAction('delete')
            ->assertNotified();

        $this->assertDatabaseHas('bank_accounts', ['id' => $account->id]);
    }

    // =====================================================================
    // Temuan 15 -- kode bank dipaksa uppercase
    // =====================================================================

    /** @test */
    public function the_bank_initial_is_forced_to_uppercase(): void
    {
        $account = BankAccount::create([
            'initial' => 'bca', 'bank_name' => 'BANK CENTRAL ASIA',
            'account_number' => '123', 'account_holder' => 'X', 'is_active' => true,
        ]);

        $this->assertSame('BCA', $account->initial);
    }
}
