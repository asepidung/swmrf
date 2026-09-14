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
}
