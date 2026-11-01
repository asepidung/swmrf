<?php

namespace App\Filament\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter "Dari tanggal - Sampai tanggal" untuk daftar transaksi, selalu diletakkan PALING ATAS di `->filters([...])`
 * (keputusan Ayah, 31 Oktober 2026; "With deleted records" paling bawah).
 *
 * Perilakunya sama dengan filter tanggal yang sudah ada di modul lain (rujukan: CashBookResource): bawaan bulan berjalan
 * ADA di form, lencana hanya tampil kalau pengguna mengubahnya. Modul baru memakai kelas ini supaya tidak menyalin
 * empat puluh baris yang sama.
 */
class DateRangeFilter
{
    public static function make(string $column, ?string $name = null): Filter
    {
        $name ??= $column;

        return Filter::make($name)
            ->form([
                DatePicker::make('date_from')
                    ->label(__('From Date'))
                    ->default(now()->startOfMonth()),
                DatePicker::make('date_until')
                    ->label(__('Until Date'))
                    ->default(now()),
            ])
            ->query(function (Builder $query, array $data) use ($column): Builder {
                $from = $data['date_from'] ?? now()->startOfMonth()->toDateString();
                $until = $data['date_until'] ?? now()->toDateString();

                return $query
                    ->when($from, fn (Builder $query, $date): Builder => $query->whereDate($column, '>=', $date))
                    ->when($until, fn (Builder $query, $date): Builder => $query->whereDate($column, '<=', $date));
            })
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if (($data['date_from'] ?? null) && $data['date_from'] !== now()->startOfMonth()->toDateString()) {
                    $indicators[] = Indicator::make('From: '.\Carbon\Carbon::parse($data['date_from'])->format('d M Y'))->removeField('date_from');
                }
                if (($data['date_until'] ?? null) && $data['date_until'] !== now()->toDateString()) {
                    $indicators[] = Indicator::make('Until: '.\Carbon\Carbon::parse($data['date_until'])->format('d M Y'))->removeField('date_until');
                }

                return $indicators;
            });
    }
}
