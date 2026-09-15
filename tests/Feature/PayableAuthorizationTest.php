<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Susulan penyisiran Payable, 15 September 2026.
 *
 * Halaman ini sebelumnya tidak punya satu pun test. Dua aksi uang (Pay,
 * Compensate) hanya dijaga `->visible()` dan, yang lebih penting, tidak
 * pernah mengunci baris utangnya -- dua permintaan Pay yang bersamaan
 * (klik ganda, atau dua tab) bisa sama-sama lolos validasi "tidak melebihi
 * sisa tagihan" (yang membaca saldo dari state Livewire, bukan basis data
 * yang dikunci) dan sama-sama membuat SupplierPayment: uang keluar dobel
 * untuk utang yang cuma perlu dibayar sekali.
 */
class PayableAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): Supplier
    {
        return Supplier::create([
            'name' => 'CV FIXTURE', 'address' => 'Bogor', 'pic' => 'X', 'top_days' => 30,
        ]);
    }

    private function payable(float $amount = 1000000): Payable
    {
        $supplier = $this->supplier();

        return Payable::create([
            'payableable_type' => Supplier::class,
            'payableable_id' => $supplier->id,
            'supplier_id' => $supplier->id,
            'document_number' => 'GR-FIXTURE-'.uniqid(),
            'amount' => $amount,
            'compensation' => 0,
            'paid_amount' => 0,
            'balance' => $amount,
            'status' => 'unpaid',
            'due_date' => now()->addDays(30),
        ]);
    }

    private function employee(array $permissionNames = []): User
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        // `view_payables` selalu disertakan: itu gerbang Policy `view()`
        // yang membuka HALAMANNYA (dipakai ViewRecord::mount() sendiri).
        // Yang diuji di sini gerbang AKSI-nya (pay_payables /
        // record_payable_compensations), bukan gerbang halamannya.
        foreach (array_unique([...$permissionNames, 'view_payables']) as $name) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['module_name' => 'Payables', 'description' => $name])->id
            );
        }

        return $user->fresh();
    }

    private function payAction(Payable $payable, User $user, string $amount): void
    {
        Livewire::actingAs($user)
            ->test(\App\Filament\Admin\Resources\PayableResource\Pages\ViewPayable::class, ['record' => $payable->getRouteKey()])
            ->mountAction('pay')
            ->setActionData([
                'payment_date' => now()->toDateString(),
                'method' => SupplierPayment::METHOD_CASH,
                'amount_input' => $amount,
            ])
            ->callMountedAction();
    }

    // =====================================================================
    // Race: klik ganda tidak boleh membuat dua pembayaran
    // =====================================================================

    /** @test */
    public function double_clicking_pay_creates_only_one_supplier_payment(): void
    {
        $payable = $this->payable(1000000);
        $user = $this->employee(['pay_payables']);

        // Dua permintaan berurutan dengan jumlah yang SAMA -- persis skenario
        // klik ganda: yang pertama menghabiskan saldo, yang kedua (kalau
        // tidak dikunci) akan lolos validasi yang sama dan membuat baris
        // kedua.
        $this->payAction($payable, $user, '1.000.000');
        $this->payAction($payable, $user, '1.000.000');

        $this->assertSame(1, SupplierPayment::where('source_id', $payable->id)->count());

        $payable->refresh();
        $this->assertSame(1000000.0, (float) $payable->paid_amount);
        $this->assertSame(0.0, (float) $payable->balance);
        $this->assertSame('paid', $payable->status);
    }

    /** @test */
    public function paying_without_the_permission_creates_nothing(): void
    {
        $payable = $this->payable(1000000);
        $user = $this->employee(); // tanpa pay_payables

        Livewire::actingAs($user)
            ->test(\App\Filament\Admin\Resources\PayableResource\Pages\ViewPayable::class, ['record' => $payable->getRouteKey()])
            ->mountAction('pay')
            ->setActionData([
                'payment_date' => now()->toDateString(),
                'method' => SupplierPayment::METHOD_CASH,
                'amount_input' => '1.000.000',
            ])
            ->callMountedAction();

        $this->assertSame(0, SupplierPayment::where('source_id', $payable->id)->count());
        $this->assertSame(0.0, (float) $payable->fresh()->paid_amount);
    }

    // =====================================================================
    // Compensation: izin, dan tidak boleh melebihi sisa tagihan meski dikunci
    // =====================================================================

    /** @test */
    public function compensating_without_the_permission_changes_nothing(): void
    {
        $payable = $this->payable(1000000);
        $user = $this->employee(); // tanpa record_payable_compensations

        Livewire::actingAs($user)
            ->test(\App\Filament\Admin\Resources\PayableResource\Pages\ViewPayable::class, ['record' => $payable->getRouteKey()])
            ->mountAction('compensation')
            ->setActionData(['amount' => '100.000', 'note' => 'Uji'])
            ->callMountedAction();

        $payable->refresh();
        $this->assertSame(0.0, (float) $payable->compensation);
        $this->assertSame(1000000.0, (float) $payable->balance);
    }

    /** @test */
    public function compensating_with_the_permission_reduces_the_balance(): void
    {
        $payable = $this->payable(1000000);
        $user = $this->employee(['record_payable_compensations']);

        Livewire::actingAs($user)
            ->test(\App\Filament\Admin\Resources\PayableResource\Pages\ViewPayable::class, ['record' => $payable->getRouteKey()])
            ->mountAction('compensation')
            ->setActionData(['amount' => '100.000', 'note' => 'Uji'])
            ->callMountedAction();

        $payable->refresh();
        $this->assertSame(100000.0, (float) $payable->compensation);
        $this->assertSame(900000.0, (float) $payable->balance);
    }

    /** @test */
    public function double_clicking_compensate_does_not_exceed_the_outstanding_balance(): void
    {
        $payable = $this->payable(1000000);
        $user = $this->employee(['record_payable_compensations']);

        // Dua kompensasi berurutan yang masing-masing SENDIRI valid
        // (600rb + 600rb > 1jt), meniru klik ganda pada form yang sama:
        // yang kedua harus ditolak oleh applyCompensation() karena baris
        // yang dibacanya sudah dikunci dan diperbarui oleh yang pertama.
        Livewire::actingAs($user)
            ->test(\App\Filament\Admin\Resources\PayableResource\Pages\ViewPayable::class, ['record' => $payable->getRouteKey()])
            ->mountAction('compensation')
            ->setActionData(['amount' => '600.000', 'note' => 'Pertama'])
            ->callMountedAction();

        Livewire::actingAs($user)
            ->test(\App\Filament\Admin\Resources\PayableResource\Pages\ViewPayable::class, ['record' => $payable->getRouteKey()])
            ->mountAction('compensation')
            ->setActionData(['amount' => '600.000', 'note' => 'Kedua'])
            ->callMountedAction();

        $payable->refresh();
        $this->assertSame(600000.0, (float) $payable->compensation);
        $this->assertSame(400000.0, (float) $payable->balance);
    }
}
