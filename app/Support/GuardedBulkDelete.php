<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Hapus massal yang mengikuti aturan hapus satuan (BD-01).
 *
 * DeleteBulkAction bawaan Filament hanya memeriksa izin hapus modul lalu menghapus tiap baris; syarat status yang
 * dipasang pada tombol hapus satuan tidak ikut berlaku. Di sini tiap baris dibaca ULANG dari database dan hanya yang
 * lolos `$allowed` yang dihapus; sisanya dilewati dan dilaporkan.
 */
class GuardedBulkDelete
{
    /**
     * @param  callable(\Illuminate\Database\Eloquent\Model): bool  $allowed  dipanggil dengan baris yang BARU dibaca
     */
    public static function action(callable $allowed): \Closure
    {
        return function (Collection $records) use ($allowed): void {
            $deleted = 0;
            $skipped = 0;

            foreach ($records as $record) {
                $fresh = $record::query()->find($record->getKey());

                if ($fresh && $allowed($fresh)) {
                    $fresh->delete();
                    $deleted++;
                } else {
                    $skipped++;
                }
            }

            if ($skipped > 0) {
                Notification::make()
                    ->title(__(':count documents were skipped because their status no longer allows deletion.', ['count' => $skipped]))
                    ->warning()
                    ->send();
            }

            if ($deleted > 0) {
                Notification::make()->title(__('Deleted'))->success()->send();
            }
        };
    }
}
