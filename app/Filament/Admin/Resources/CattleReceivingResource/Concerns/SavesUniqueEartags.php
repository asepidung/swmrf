<?php

namespace App\Filament\Admin\Resources\CattleReceivingResource\Concerns;

use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan penerimaan sapi dengan eartag yang benar-benar terjaga.
 *
 * Validasi di form sudah memeriksa duplikat -- di dalam dokumen maupun
 * lintas riwayat -- dan itu dipertahankan karena ia yang memberi pesan
 * spesifik saat operator masih mengetik. Tapi pemeriksaan itu terjadi
 * SEBELUM penyimpanan, dan di antara keduanya ada celah: dua operator yang
 * menyimpan bersamaan bisa sama-sama lolos pemeriksaan lalu sama-sama
 * menyimpan eartag yang sama.
 *
 * Yang mengikat adalah unique index di database. Yang dikerjakan di sini
 * hanya menerjemahkan penolakannya menjadi kalimat yang bisa ditindaklanjuti
 * -- kalau tidak, operator gudang menerima halaman error mentah dan
 * kehilangan seluruh isian yang sudah diketiknya.
 */
trait SavesUniqueEartags
{
    protected function saveGuardingEartags(\Closure $save): mixed
    {
        try {
            return DB::transaction($save);
        } catch (UniqueConstraintViolationException $e) {
            Notification::make()
                ->danger()
                ->title(__('Eartag already recorded'))
                ->body(__('One of these eartags is already recorded in another receiving document. Please check the tags again.'))
                ->persistent()
                ->send();

            // Halt menahan pengguna di halaman ini bersama isiannya, alih-alih
            // melemparnya ke halaman error dan menghapus seluruh ketikannya.
            throw new Halt();
        }
    }
}
