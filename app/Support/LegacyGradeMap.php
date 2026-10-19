<?php

namespace App\Support;

use App\Models\Grade;
use App\Models\Warehouse;

/**
 * Terjemahan grade legacy ke pasangan (gudang, grade) swmrf -- SATU RUMAH untuk import master, stok awal,
 * dokumen terbuka, dan riwayat (keputusan Owner, 16 Oktober 2026, issue #535).
 *
 * Grade legacy menggabungkan GUDANG dan KONDISI (J01 = Jonggol + chill); aplikasi baru memisahkannya:
 * grade (id terkunci, masuk barcode 28 digit: 1 CHILL, 2 FROZEN, 3 A, 4 B, 5 R) dan gudang. Karena itu grade
 * legacy TIDAK diimpor sebagai baris; hanya diterjemahkan lewat tabel ini. B dan R tidak punya padanan legacy
 * (dikoreksi per barcode saat opname). Gudang dicocokkan menurut KODE, bukan id. Barcode legacy (18-20 digit)
 * tidak membawa digit grade versi baru, jadi gudang dan grade selalu diambil dari terjemahan ini.
 */
class LegacyGradeMap
{
    /**
     * id legacy => [nama legacy, kode gudang, nama grade baru].
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    public const MAP = [
        1 => ['J01', 'JGL', 'CHILL'],
        2 => ['J02', 'JGL', 'FROZEN'],
        3 => ['P01', 'PRM', 'CHILL'],
        4 => ['P02', 'PRM', 'FROZEN'],
        5 => ['J03', 'JGL', 'A'],
        6 => ['P03', 'PRM', 'A'],
    ];

    /** Id grade baru yang terkunci (masuk barcode): nama => id. */
    public const APP_GRADE_IDS = ['CHILL' => 1, 'FROZEN' => 2, 'A' => 3, 'B' => 4, 'R' => 5];

    public static function knows(int $legacyGradeId): bool
    {
        return isset(self::MAP[$legacyGradeId]);
    }

    /** Nama grade legacy yang diharapkan untuk id itu (J01, ...), atau null bila id tidak dikenal. */
    public static function legacyName(int $legacyGradeId): ?string
    {
        return self::MAP[$legacyGradeId][0] ?? null;
    }

    /**
     * Pasangan (gudang, grade) untuk sebuah grade legacy.
     *
     * @return array{warehouse_id: int, grade_id: int}
     *
     * @throws \InvalidArgumentException bila grade legacy di luar tabel, atau gudang/grade barunya tidak ada
     */
    public static function resolve(int $legacyGradeId): array
    {
        if (! self::knows($legacyGradeId)) {
            throw new \InvalidArgumentException(__('Legacy grade :id is outside the translation table (1-6).', ['id' => $legacyGradeId]));
        }

        [, $warehouseCode, $gradeName] = self::MAP[$legacyGradeId];

        $warehouseId = Warehouse::where('code', $warehouseCode)->value('id');
        $gradeId = Grade::where('name', $gradeName)->value('id');

        if (! $warehouseId) {
            throw new \InvalidArgumentException(__('Warehouse with code :code does not exist yet (needed by legacy grade :id).', ['code' => $warehouseCode, 'id' => $legacyGradeId]));
        }

        if (! $gradeId) {
            throw new \InvalidArgumentException(__('Grade :name does not exist yet (needed by legacy grade :id).', ['name' => $gradeName, 'id' => $legacyGradeId]));
        }

        return ['warehouse_id' => (int) $warehouseId, 'grade_id' => (int) $gradeId];
    }

    /**
     * Masalah di sisi aplikasi yang membuat terjemahan tidak bisa dipakai: gudang yang belum ada dan grade
     * yang belum ada atau id-nya bukan yang terkunci. Kosong = siap.
     *
     * @return array<int, string>
     */
    public static function appSideProblems(): array
    {
        $problems = [];

        foreach (array_unique(array_column(self::MAP, 1)) as $code) {
            if (! Warehouse::where('code', $code)->exists()) {
                $problems[] = __('warehouse code :code does not exist yet', ['code' => $code]);
            }
        }

        foreach (array_unique(array_column(self::MAP, 2)) as $name) {
            $id = Grade::where('name', $name)->value('id');

            if (! $id) {
                $problems[] = __('grade :name does not exist yet', ['name' => $name]);
            } elseif ((int) $id !== self::APP_GRADE_IDS[$name]) {
                $problems[] = __('grade :name has id :id, should be :expected (locked id)', ['name' => $name, 'id' => $id, 'expected' => self::APP_GRADE_IDS[$name]]);
            }
        }

        return $problems;
    }
}
