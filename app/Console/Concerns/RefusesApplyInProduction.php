<?php

namespace App\Console\Concerns;

/**
 * CMD-01 (keputusan Owner): perintah impor sekali pakai untuk go-live tidak boleh menulis di produksi lagi.
 * Aplikasi sudah dipakai sungguhan; menjalankannya ulang berisiko menimpa atau menggandakan data hidup.
 * Dry-run (tanpa `--apply`) tetap boleh. Di lingkungan lokal dan pengujian, `--apply` berjalan seperti biasa.
 */
trait RefusesApplyInProduction
{
    protected function tolakApplyDiProduksi(): bool
    {
        if (! $this->option('apply') || ! app()->isProduction()) {
            return false;
        }

        $this->error('Ditolak: perintah impor go-live ini tidak boleh menulis di produksi (aplikasi sudah dipakai). Dry-run tanpa --apply tetap boleh.');

        return true;
    }
}
