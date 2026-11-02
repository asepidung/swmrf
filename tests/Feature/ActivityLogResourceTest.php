<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Susulan dari penyisiran modul Activity Log, 14 September 2026. Dua
 * temuan kategori [A] yang disetujui Hafizh.
 */
class ActivityLogResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $this->actingAs($this->user);
    }

    /**
     * Silent date filter, pola CashBookResource: default bulan berjalan ADA
     * di form, badge cuma tampil kalau user mengubahnya. Sebelumnya filter
     * ini tidak punya `indicateUsing()` sama sekali, jadi Filament memakai
     * perilaku bawaannya sendiri -- badge SELALU tampil begitu form punya
     * nilai apa pun, termasuk nilai default yang belum disentuh user.
     */
    public function test_the_date_filter_stays_silent_at_its_default_and_shows_a_chip_once_changed(): void
    {
        $livewire = Livewire::test(ListActivityLogs::class);

        $filter = $livewire->instance()->getTable()->getFilter('created_at');
        $this->assertSame(
            [],
            $filter->getIndicators(),
            'default bulan berjalan tidak boleh terlihat seperti filter yang sudah dipilih user'
        );

        $livewire->filterTable('created_at', [
            'created_from' => now()->subMonths(2)->toDateString(),
            'created_until' => now()->toDateString(),
        ]);

        $filter = $livewire->instance()->getTable()->getFilter('created_at');
        $this->assertNotEmpty(
            $filter->getIndicators(),
            'begitu user benar-benar mengubah tanggal, badge harus muncul'
        );
    }

    /**
     * Kolom `subject_label` membaca `$record->subject` (morphTo) per baris.
     * Tanpa eager load, jumlah query ikut naik seiring jumlah baris --
     * jejak N+1 klasik. Dibandingkan bukan dengan angka tetap (gampang basi
     * begitu kolom lain ditambah), melainkan dengan jumlah query pada SATU
     * baris: kalau keduanya sama, berarti query tidak bertambah per baris.
     */
    public function test_the_table_does_not_run_a_query_per_row_for_subject_and_causer(): void
    {
        $queriesForOneRow = $this->hitungQuerySaatMerenderNBaris(1);
        $queriesForFiveRows = $this->hitungQuerySaatMerenderNBaris(5);

        $this->assertSame(
            $queriesForOneRow,
            $queriesForFiveRows,
            'jumlah query tidak boleh naik seiring jumlah baris (N+1 pada subject/causer)'
        );
    }

    private function hitungQuerySaatMerenderNBaris(int $n): int
    {
        Activity::query()->forceDelete();

        for ($i = 0; $i < $n; $i++) {
            $target = User::factory()->create();

            Activity::create([
                'log_name' => 'default',
                'description' => 'updated',
                'subject_type' => User::class,
                'subject_id' => $target->id,
                'causer_type' => User::class,
                'causer_id' => $this->user->id,
                'properties' => [],
            ]);
        }

        DB::enableQueryLog();
        Livewire::test(ListActivityLogs::class);
        $jumlah = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        return $jumlah;
    }

    /**
     * Kolom Target / Document No memakai nomor dokumen, bukan id (Owner, 2 November 2026).
     */
    public function test_the_target_column_shows_readable_document_numbers_instead_of_ids(): void
    {
        $so = \App\Models\SalesOrder::forceCreate([
            'so_number' => 'SWM-SO#260077', 'customer_id' => \App\Models\Customer::create([
                'name' => 'PELANGGAN UJI', 'customer_segment_id' => \App\Models\CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
                'address' => 'Jakarta', 'pic' => 'A', 'phone' => '0812', 'top' => 0, 'invoice_exchange' => false,
            ])->id, 'delivery_date' => now()->toDateString(), 'status' => 'ready', 'created_by' => $this->user->id,
        ]);

        $log = fn (string $type, int $id, array $properties = []) => Activity::create([
            'log_name' => 'default', 'description' => 'updated', 'subject_type' => $type, 'subject_id' => $id, 'properties' => $properties,
        ]);

        $this->assertSame('SWM-SO#260077', \App\Filament\Admin\Resources\ActivityLogResource::getSubjectLabelFromRecord($log(\App\Models\SalesOrder::class, $so->id)));

        // Stok yang sudah terhapus permanen: nomornya diambil dari isi log, bukan id.
        $this->assertSame(
            'BC-HAPUS-1',
            \App\Filament\Admin\Resources\ActivityLogResource::getSubjectLabelFromRecord($log(\App\Models\BeefStock::class, 829, ['old' => ['barcode' => 'BC-HAPUS-1']])),
        );

        // Rencana kirim: tanggalnya string dari basis data (tanpa cast), jangan memanggil format() langsung.
        $rencana = \App\Models\DeliveryPlan::forceCreate([
            'customer_id' => $so->customer_id, 'delivery_date' => '2026-11-03', 'created_by' => $this->user->id,
        ]);

        $this->assertSame(
            'PELANGGAN UJI · 03 Nov 2026',
            \App\Filament\Admin\Resources\ActivityLogResource::getSubjectLabelFromRecord($log(\App\Models\DeliveryPlan::class, $rencana->id)),
        );
    }
}
