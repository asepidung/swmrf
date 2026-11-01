<?php

namespace Tests\Feature;

use App\Filament\Support\DateRangeFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Tests\TestCase;

/**
 * Susunan filter daftar dan perilaku pemilih tanggal (permintaan Ayah, 31 Oktober 2026).
 *
 *  - Filter "Dari tanggal - Sampai tanggal" PALING ATAS, "With deleted records" (TrashedFilter) PALING BAWAH, di setiap
 *    daftar. Diperiksa pada sumber Resource: urutan filter adalah susunan di `->filters([...])`.
 *  - Panel kalender DatePicker menutup sendiri setelah tanggal diklik; DateTimePicker yang punya jam tidak (jam dipilih
 *    sesudah tanggal).
 */
class FilterOrderAndPickerCloseTest extends TestCase
{
    /** @return array<int, string> elemen tingkat-atas di dalam `->filters([...])`, atau [] bila tidak ada */
    private function filterElements(string $source): array
    {
        $pos = strpos($source, '->filters([');
        if ($pos === false) {
            return [];
        }

        $open = $pos + strlen('->filters(');
        $n = strlen($source);
        $depth = 0;
        $start = $open + 1;
        $parts = [];

        for ($i = $open; $i < $n; $i++) {
            $c = $source[$i];

            if ($c === "'" || $c === '"') {
                for ($i++; $i < $n; $i++) {
                    if ($source[$i] === '\\') {
                        $i++;

                        continue;
                    }
                    if ($source[$i] === $c) {
                        break;
                    }
                }

                continue;
            }
            if (($c === '/' && ($source[$i + 1] ?? '') === '/') || $c === '#') {
                while ($i < $n && $source[$i] !== "\n") {
                    $i++;
                }

                continue;
            }
            if ($c === '/' && ($source[$i + 1] ?? '') === '*') {
                $i = strpos($source, '*/', $i + 2) + 1;

                continue;
            }

            if ($c === '[' || $c === '(' || $c === '{') {
                $depth++;
            }
            if ($c === ']' || $c === ')' || $c === '}') {
                $depth--;
                if ($depth === 0) {
                    $parts[] = substr($source, $start, $i - $start);

                    break;
                }
            }
            if ($c === ',' && $depth === 1) {
                $parts[] = substr($source, $start, $i - $start);
                $start = $i + 1;
            }
        }

        return array_values(array_filter($parts, fn (string $p) => trim($p) !== ''));
    }

    private function resourceSources(): array
    {
        $sources = [];
        foreach (glob(app_path('Filament/Admin/Resources/*Resource.php')) as $file) {
            $sources[basename($file)] = file_get_contents($file);
        }

        return $sources;
    }

    public function test_deleted_records_filter_is_always_last(): void
    {
        $checked = 0;

        foreach ($this->resourceSources() as $name => $source) {
            $elements = $this->filterElements($source);
            $trashed = array_keys(array_filter($elements, fn ($e) => str_contains($e, 'TrashedFilter')));

            if ($trashed === []) {
                continue;
            }

            $checked++;
            $this->assertSame(count($elements) - 1, end($trashed), "{$name}: TrashedFilter harus filter terakhir.");
        }

        $this->assertGreaterThan(10, $checked, 'Terlalu sedikit modul yang diperiksa; pemeriksa urutan mungkin rusak.');
    }

    public function test_date_range_filter_is_always_first_when_present(): void
    {
        $checked = 0;

        foreach ($this->resourceSources() as $name => $source) {
            $elements = $this->filterElements($source);
            $dates = array_keys(array_filter($elements, fn ($e) => substr_count($e, 'DatePicker::make') >= 2 || str_contains($e, 'DateRangeFilter::make')));

            if ($dates === []) {
                continue;
            }

            $checked++;
            $this->assertSame(0, $dates[0], "{$name}: filter tanggal harus yang paling atas.");
        }

        $this->assertGreaterThan(20, $checked, 'Terlalu sedikit modul yang diperiksa; pemeriksa urutan mungkin rusak.');
    }

    public function test_transaction_lists_that_had_no_date_filter_now_have_one(): void
    {
        foreach (['Carcass', 'Mutation', 'QcReport', 'SalesReturnPlan'] as $module) {
            $source = file_get_contents(app_path("Filament/Admin/Resources/{$module}Resource.php"));

            $this->assertStringContainsString('DateRangeFilter::make', $source, "{$module}: filter tanggal belum dipasang.");
        }
    }

    public function test_date_range_filter_has_from_and_until_pickers(): void
    {
        $filter = DateRangeFilter::make('kill_date');

        $this->assertSame('kill_date', $filter->getName());

        $fields = collect($filter->getFormSchema())->map(fn ($c) => $c->getName())->all();
        $this->assertSame(['date_from', 'date_until'], $fields);
    }

    public function test_date_picker_closes_after_a_day_is_picked_but_date_time_picker_does_not(): void
    {
        $this->assertTrue(DatePicker::make('tanggal')->shouldCloseOnDateSelection());
        $this->assertFalse(DateTimePicker::make('waktu')->shouldCloseOnDateSelection());
    }
}
