<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\ListMaterialRequisitions;
use App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ListProductRequisitions;
use App\Models\MaterialRequisition;
use App\Models\ProductRequisition;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Request yang masih menggantung tetap terlihat walau dibuat di bulan lain (Owner, 3 November 2026): filter tanggal
 * bawaan (bulan berjalan) hanya menyaring yang sudah selesai.
 */
class RequisitionPendingAcrossMonthsTest extends TestCase
{
    use RefreshDatabase;

    private function buat(string $kelas, string $nomor, string $status, string $dibuat): \Illuminate\Database\Eloquent\Model
    {
        $supplier = Supplier::firstOrCreate(['name' => 'PEMASOK UJI'], ['address' => 'Bogor', 'pic' => 'A', 'top_days' => 0]);
        $user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);

        $baris = $kelas::forceCreate([
            'document_number' => $nomor, 'user_id' => $user->id, 'supplier_id' => $supplier->id,
            'due_date' => $dibuat, 'terms_of_payment' => '0', 'tax_amount' => 0, 'total_amount' => 1000, 'status' => $status,
        ]);
        $baris->forceFill(['created_at' => $dibuat.' 09:00:00'])->saveQuietly();

        return $baris;
    }

    public function test_pending_material_requests_from_other_months_stay_visible(): void
    {
        $lamaMenunggu = $this->buat(MaterialRequisition::class, 'REQ-LAMA-1', 'Pending Finance', now()->subMonths(2)->toDateString());
        $lamaSelesai = $this->buat(MaterialRequisition::class, 'REQ-LAMA-2', 'PO Created', now()->subMonths(2)->toDateString());
        $baruSelesai = $this->buat(MaterialRequisition::class, 'REQ-BARU-1', 'PO Created', now()->toDateString());

        Livewire::actingAs(User::first())
            ->test(ListMaterialRequisitions::class)
            ->assertCanSeeTableRecords([$lamaMenunggu, $baruSelesai])
            ->assertCanNotSeeTableRecords([$lamaSelesai]);
    }

    public function test_pending_beef_requests_from_other_months_stay_visible(): void
    {
        $lamaMenunggu = $this->buat(ProductRequisition::class, 'REQB-LAMA-1', 'Requested', now()->subMonths(2)->toDateString());
        $lamaSelesai = $this->buat(ProductRequisition::class, 'REQB-LAMA-2', 'Rejected', now()->subMonths(2)->toDateString());

        Livewire::actingAs(User::first())
            ->test(ListProductRequisitions::class)
            ->assertCanSeeTableRecords([$lamaMenunggu])
            ->assertCanNotSeeTableRecords([$lamaSelesai]);
    }

    public function test_clearing_the_dates_shows_everything(): void
    {
        $lama = $this->buat(MaterialRequisition::class, 'REQ-LAMA-3', 'PO Created', now()->subMonths(2)->toDateString());

        Livewire::actingAs(User::first())
            ->test(ListMaterialRequisitions::class)
            ->filterTable('created_at', ['created_from' => null, 'created_until' => null])
            ->assertCanSeeTableRecords([$lama]);
    }
}
