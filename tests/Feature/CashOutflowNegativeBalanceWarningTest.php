<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** KAS-01 (keputusan Owner): uang keluar yang membuat saldo minus tetap tersimpan, tetapi diberi peringatan. */
class CashOutflowNegativeBalanceWarningTest extends TestCase
{
    use RefreshDatabase;

    private function akun(): BankAccount
    {
        return BankAccount::create(['initial' => '0', 'bank_name' => 'Kas Tunai', 'account_number' => '-', 'account_holder' => 'X', 'is_active' => true]);
    }

    private function keluar(BankAccount $a, int $jumlah): void
    {
        BankTransaction::create(['bank_account_id' => $a->id, 'type' => 'out', 'amount' => $jumlah, 'description' => 'uji', 'transaction_date' => now()]);
    }

    private function peringatan(): array
    {
        return collect(session()->get('filament.notifications', []))->pluck('title')->all();
    }

    /** @test */
    public function an_outflow_that_makes_the_balance_negative_is_saved_and_warned(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->akun();
        BankTransaction::create(['bank_account_id' => $a->id, 'type' => 'in', 'amount' => 1000, 'description' => 'uji', 'transaction_date' => now()]);

        $this->keluar($a, 500);
        $this->assertSame([], $this->peringatan(), 'saldo masih positif: tanpa peringatan');

        $this->keluar($a, 800);
        $this->assertSame(2, BankTransaction::where('type', 'out')->count(), 'tetap tersimpan');
        $this->assertCount(1, $this->peringatan());
        $this->assertStringContainsString('Kas Tunai', $this->peringatan()[0]);
    }
}
