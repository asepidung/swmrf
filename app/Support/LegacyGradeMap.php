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
        1 => ['J01', 'JONGGOL', 'CHILL'],
        2 => ['J02', 'JONGGOL', 'FROZEN'],
        3 => ['P01', 'PERUM', 'CHILL'],
        4 => ['P02', 'PERUM', 'FROZEN'],
        5 => ['J03', 'JONGGOL', 'A'],
        6 => ['P03', 'PERUM', 'A'],
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
            throw new \InvalidArgumentException("Grade legacy {$legacyGradeId} di luar tabel terjemahan (1-6).");
        }

        [, $warehouseCode, $gradeName] = self::MAP[$legacyGradeId];

        $warehouseId = Warehouse::where('code', $warehouseCode)->value('id');
        $gradeId = Grade::where('name', $gradeName)->value('id');

        if (! $warehouseId) {
            throw new \InvalidArgumentException("Gudang dengan kode {$warehouseCode} belum ada (dibutuhkan grade legacy {$legacyGradeId}).");
        }

        if (! $gradeId) {
            throw new \InvalidArgumentException("Grade {$gradeName} belum ada (dibutuhkan grade legacy {$legacyGradeId}).");
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
                $problems[] = "gudang kode {$code} belum ada";
            }
        }

        foreach (array_unique(array_column(self::MAP, 2)) as $name) {
            $id = Grade::where('name', $name)->value('id');

            if (! $id) {
                $problems[] = "grade {$name} belum ada";
            } elseif ((int) $id !== self::APP_GRADE_IDS[$name]) {
                $problems[] = "grade {$name} berid {$id}, seharusnya ".self::APP_GRADE_IDS[$name].' (id terkunci)';
            }
        }

        return $problems;
    }
}
