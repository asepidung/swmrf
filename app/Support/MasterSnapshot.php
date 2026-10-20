<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Salinan master data dari satu basis data ke basis data lain (coba. -> VPS), DENGAN id yang sama.
 *
 * Kenapa salinan, bukan impor ulang dari legacy: Ayah dan tim memperbaiki data di coba. (nama, grup, segmen, BOM dari QC),
 * dan perbaikan itu harus ikut ke produksi apa adanya (keputusan Ayah, 20 Oktober 2026).
 *
 * Aturan:
 *  - Hanya tabel master (TABLES), urut menurut kunci asing. Pengguna, izin, transaksi, riwayat TIDAK ikut.
 *  - Baris dengan id yang sudah ada di tujuan: isi bisnisnya (tanpa created_at/updated_at) sama = dilewati; beda = DITOLAK.
 *  - Satu penolakan membatalkan semuanya (seluruh penulisan satu transaksi). Dry-run menjalankan semuanya lalu membatalkannya,
 *    jadi tabrakan nama/kode unik pun terlihat sebelum ada yang ditulis.
 *  - Penulisan lewat DB::table (tanpa event model), jadi tidak membuat catatan riwayat palsu di tujuan.
 */
class MasterSnapshot
{
    /** Urutan = urutan penulisan (tabel induk sebelum tabel yang menunjuknya). */
    public const TABLES = [
        'warehouses', 'grades', 'cattle_classes', 'drivers', 'vehicles', 'suppliers',
        'material_units', 'material_categories', 'materials',
        'product_categories', 'customer_groups', 'products', 'product_materials',
        'customer_segments', 'customers',
    ];

    private const TIMESTAMPS = ['created_at', 'updated_at'];

    /**
     * @param  array<int, string>  $tables
     * @return array{meta: array<string, mixed>, tables: array<string, array<int, array<string, mixed>>>}
     */
    public static function export(array $tables = self::TABLES): array
    {
        $data = [];

        foreach ($tables as $table) {
            $data[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return [
            'meta' => [
                'format' => 1,
                'created_at' => now()->toDateTimeString(),
                'source' => (string) config('app.url'),
                'columns' => collect($tables)->mapWithKeys(fn ($t) => [$t => Schema::getColumnListing($t)])->all(),
                'counts' => collect($data)->map(fn ($rows) => count($rows))->all(),
                'checksum' => hash('sha256', json_encode($data)),
            ],
            'tables' => $data,
        ];
    }

    /**
     * Menulis snapshot ke basis data ini. Dengan $apply = false semuanya dijalankan lalu dibatalkan (dry-run).
     *
     * @param  array{meta: array<string, mixed>, tables: array<string, array<int, array<string, mixed>>>}  $snapshot
     * @return array{report: array<string, array{created: int, same: int, refused: array<int, string>}>, refused: bool}
     */
    public static function import(array $snapshot, bool $apply): array
    {
        $report = [];
        $problems = self::validate($snapshot);

        if ($problems !== []) {
            return ['report' => ['snapshot' => ['created' => 0, 'same' => 0, 'refused' => $problems]], 'refused' => true];
        }

        DB::beginTransaction();

        try {
            foreach (self::TABLES as $table) {
                if (! array_key_exists($table, $snapshot['tables'])) {
                    continue;
                }

                $report[$table] = self::write($table, $snapshot['tables'][$table]);
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $refused = collect($report)->contains(fn ($r) => $r['refused'] !== []);

        if ($apply && ! $refused) {
            DB::commit();
        } else {
            DB::rollBack();
        }

        return ['report' => $report, 'refused' => $refused];
    }

    /** @return array<int, string> */
    private static function validate(array $snapshot): array
    {
        $problems = [];

        if (($snapshot['meta']['format'] ?? null) !== 1 || ! isset($snapshot['tables'])) {
            return ['Berkas bukan snapshot master yang dikenal.'];
        }

        if (($snapshot['meta']['checksum'] ?? '') !== hash('sha256', json_encode($snapshot['tables']))) {
            $problems[] = 'Checksum tidak cocok: berkas berubah atau rusak sejak dibuat.';
        }

        foreach ($snapshot['tables'] as $table => $rows) {
            if (! in_array($table, self::TABLES, true)) {
                $problems[] = "Tabel \"{$table}\" tidak termasuk daftar master yang boleh disalin.";

                continue;
            }

            if (! Schema::hasTable($table)) {
                $problems[] = "Tabel \"{$table}\" tidak ada di basis data ini (deploy kode terbaru dulu).";

                continue;
            }

            $here = Schema::getColumnListing($table);
            $there = $snapshot['meta']['columns'][$table] ?? [];

            if (array_diff($there, $here) !== [] || array_diff($here, $there) !== []) {
                $problems[] = "Kolom tabel \"{$table}\" berbeda antara sumber dan tujuan (migrasi belum sama).";
            }
        }

        return $problems;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, same: int, refused: array<int, string>}
     */
    private static function write(string $table, array $rows): array
    {
        $result = ['created' => 0, 'same' => 0, 'refused' => []];

        // Induk dulu: baris yang menunjuk baris lain di tabel yang sama (products.parent_id) harus sesudahnya.
        usort($rows, fn ($a, $b) => [isset($a['parent_id']) ? 1 : 0, $a['id']] <=> [isset($b['parent_id']) ? 1 : 0, $b['id']]);

        foreach ($rows as $row) {
            $existing = DB::table($table)->where('id', $row['id'])->first();

            if ($existing) {
                if (self::business((array) $existing) === self::business($row)) {
                    $result['same']++;
                } else {
                    $result['refused'][] = "id {$row['id']}: sudah ada di tujuan dengan isi berbeda";
                }

                continue;
            }

            try {
                DB::table($table)->insert($row);
                $result['created']++;
            } catch (\Throwable $e) {
                $result['refused'][] = "id {$row['id']}: ".strtok($e->getMessage(), "\n");
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $row */
    private static function business(array $row): string
    {
        foreach (self::TIMESTAMPS as $column) {
            unset($row[$column]);
        }

        ksort($row);

        return json_encode(array_map(fn ($v) => is_bool($v) ? (int) $v : (is_numeric($v) ? (string) (0 + $v) : $v), $row));
    }
}
