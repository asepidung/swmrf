<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedCompanyBankAccountsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function dry_run_writes_nothing_and_apply_creates_the_three_accounts_once(): void
    {
        $this->artisan('bank-accounts:seed-company')->assertSuccessful();
        $this->assertSame(0, BankAccount::count());

        $this->artisan('bank-accounts:seed-company', ['--apply' => true])->assertSuccessful();
        $this->artisan('bank-accounts:seed-company', ['--apply' => true])->assertSuccessful();

        $this->assertSame(3, BankAccount::count());
        $this->assertDatabaseHas('bank_accounts', ['account_number' => '7115534882', 'initial' => 'BCA PT', 'is_active' => true]);
    }
}
