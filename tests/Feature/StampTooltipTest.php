<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StampTooltip;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Tooltip waktu di kolom nama pembuat/penyetuju (Owner, 10 November 2026), terpasang otomatis lewat satu konfigurasi. */
class StampTooltipTest extends TestCase
{
    use RefreshDatabase;

    private function rekam(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['created_at' => Carbon::parse('2026-11-10 10:43:51')])->saveQuietly();

        return $user->fresh();
    }

    public function test_the_creator_column_shows_when_the_document_was_made(): void
    {
        $kolom = TextColumn::make('creator.name')->record($this->rekam());

        $this->assertSame(__('Created on :datetime', ['datetime' => '10 Nov 2026 10:43']), $kolom->getTooltip());
        $this->assertStringContainsString('10 Nov 2026 10:43', $kolom->getTooltip());
    }

    public function test_the_approver_column_says_approved(): void
    {
        $kolom = TextColumn::make('approvedBy.name')->record($this->rekam());

        $this->assertSame(__('Approved on :datetime', ['datetime' => '10 Nov 2026 10:43']), $kolom->getTooltip());
    }

    public function test_every_listed_column_name_is_covered(): void
    {
        foreach (array_keys(StampTooltip::KOLOM) as $nama) {
            $this->assertNotNull(TextColumn::make($nama)->record($this->rekam())->getTooltip(), $nama);
        }
    }

    public function test_other_columns_get_no_tooltip_from_this(): void
    {
        $this->assertNull(TextColumn::make('name')->record($this->rekam())->getTooltip());
    }

    public function test_a_legacy_imported_name_gets_no_misleading_time(): void
    {
        $this->assertNull(StampTooltip::text('Created on :datetime', $this->rekam(), 'Legacy'));
        $this->assertNotNull(StampTooltip::text('Created on :datetime', $this->rekam(), 'Candra'));
    }

    public function test_a_record_without_a_creation_time_gives_nothing(): void
    {
        $this->assertNull(StampTooltip::text('Created on :datetime', new User, 'Candra'));
        $this->assertNull(StampTooltip::text('Created on :datetime', null, 'Candra'));
    }
}
