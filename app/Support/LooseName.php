<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Pencocokan nama LONGGAR untuk master data: huruf besar/kecil, spasi, dan tanda baca diabaikan, sehingga
 * "CV. X", "CV.X", dan "cv x" dianggap nama yang sama. Hanya untuk PERINGATAN di formulir (kemungkinan data kembar);
 * penolakan nama yang persis sama tetap tugas aturan unik formulir dan database.
 *
 * Keputusan Ayah, 19 Oktober 2026: peringatan "mirip" ada di semua modul master data.
 */
class LooseName
{
    /** Kunci pencocokan: huruf dan angka saja, huruf besar. */
    public static function key(?string $name): string
    {
        return (string) preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(trim((string) $name)));
    }

    /**
     * Nama yang sudah ada dan MIRIP (kuncinya sama) tetapi tidak persis sama dengan yang diketik.
     *
     * @param  class-string<Model>  $model
     * @return array<int, string>
     */
    public static function similarTo(string $model, ?string $typed, ?int $ignoreId = null, string $column = 'name'): array
    {
        $key = self::key($typed);

        if ($key === '') {
            return [];
        }

        $exact = mb_strtoupper(trim((string) $typed));

        return $model::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['id', $column])
            ->map(fn (Model $row): string => (string) $row->getAttribute($column))
            ->filter(fn (string $existing): bool => self::key($existing) === $key && mb_strtoupper(trim($existing)) !== $exact)
            ->unique()
            ->take(3)
            ->values()
            ->all();
    }
}
