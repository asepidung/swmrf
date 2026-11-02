<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\SalesReport;
use App\Filament\Admin\Resources\BankAccountResource;
use App\Filament\Admin\Resources\CashBookResource;
use App\Filament\Admin\Resources\PayableResource;
use App\Filament\Admin\Resources\ReceivableResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Modul keuangan ditutup untuk semua orang, termasuk yang punya izinnya (Owner, 2 November 2026). */
class ModulKeuanganDitutupTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_finance_modules_are_closed_even_for_an_admin(): void
    {
        config(['modul.keuangan_ditutup' => true]);
        $this->actingAs(User::factory()->create(['role' => 'programmer']));

        $this->assertFalse(BankAccountResource::canViewAny());
        $this->assertFalse(BankAccountResource::shouldRegisterNavigation());
        $this->assertFalse(PayableResource::canViewAny());
        $this->assertFalse(PayableResource::shouldRegisterNavigation());
        $this->assertFalse(ReceivableResource::canViewAny());
        $this->assertFalse(CashBookResource::canViewAny());
        $this->assertFalse(SalesReport::canAccess());
    }

    public function test_the_payable_reminder_stays_silent_while_closed(): void
    {
        config(['modul.keuangan_ditutup' => true]);

        $this->artisan('payables:notify-due')->expectsOutputToContain('ditutup')->assertSuccessful();
    }
}
