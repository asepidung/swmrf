<?php

namespace App\Support;

/**
 * Versi aplikasi dan riwayat perubahannya, dibaca dari `config/changelog.php` (entri teratas = versi sekarang).
 */
class AppVersion
{
    /** @return array<int, array{version: string, date: string, kind: string, title: string, items: array<int, string>}> terbaru lebih dulu */
    public static function entries(): array
    {
        $entries = config('changelog.entries', []);

        usort($entries, fn (array $a, array $b): int => version_compare($b['version'], $a['version']));

        return $entries;
    }

    public static function current(): string
    {
        return self::entries()[0]['version'] ?? '0.0.0';
    }

    /**
     * Entri yang BELUM dilihat pengguna: versinya lebih baru dari yang terakhir ia lihat, sampai versi sekarang.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function entriesAfter(?string $seen): array
    {
        if ($seen === null || $seen === '') {
            return [];
        }

        return array_values(array_filter(
            self::entries(),
            fn (array $entry): bool => version_compare($entry['version'], $seen, '>'),
        ));
    }

    /**
     * Entri yang BOLEH diumumkan lewat jendela "Yang baru": belum dilihat pengguna DAN tanggalnya sebelum hari ini.
     * Perubahan hari ini baru diumumkan pada pembukaan pertama besok, jadi paling banyak sekali sehari
     * (permintaan Ayah, 2 November 2026). Footer dan riwayat lengkap tetap langsung menunjukkan versi terbaru.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function entriesToAnnounce(?string $seen): array
    {
        $hariIni = now()->toDateString();

        return array_values(array_filter(
            self::entriesAfter($seen),
            fn (array $entry): bool => $entry['date'] < $hariIni,
        ));
    }
}
