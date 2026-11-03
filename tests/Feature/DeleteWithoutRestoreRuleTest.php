<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Aturan Owner (3 November 2026): dokumen yang dihapus (soft delete) TIDAK bisa dipulihkan. Mau dokumen yang sama
 * lagi, user membuat dokumen baru; nomor lama dibiarkan melompat.
 *
 * Penjaga ini membaca kode (bukan menjalankannya) supaya modul baru yang menyalin pola lama langsung merah:
 *  - tidak ada tombol/aksi pulihkan di layar mana pun;
 *  - setiap kebijakan menolak restore dan restoreAny;
 *  - setiap pembuatan nomor dokumen ikut menghitung dokumen terhapus.
 */
class DeleteWithoutRestoreRuleTest extends TestCase
{
    /** @test */
    public function no_screen_offers_a_restore_action(): void
    {
        $ada = [];

        foreach (File::allFiles(app_path('Filament')) as $file) {
            if (preg_match('/Restore(Bulk)?Action\b/', $file->getContents())) {
                $ada[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $ada, 'Dokumen terhapus tidak boleh punya tombol pulihkan: '.implode(', ', $ada));
    }

    /** @test */
    public function every_policy_refuses_restore_and_restore_any(): void
    {
        $salah = [];

        foreach (File::files(app_path('Policies')) as $file) {
            $kode = $file->getContents();

            foreach (['restore', 'restoreAny'] as $metode) {
                if (! preg_match('/function '.$metode.'\([^)]*\)[^{]*\{(.*?)\n?\s*\}/s', $kode, $cocok)) {
                    continue;
                }

                if (trim($cocok[1]) !== 'return false;') {
                    $salah[] = $file->getFilename().'::'.$metode;
                }
            }
        }

        $this->assertSame([], $salah, 'Kebijakan ini masih mengizinkan pulihkan: '.implode(', ', $salah));
    }

    /** @test */
    public function every_document_number_generator_counts_deleted_documents(): void
    {
        $salah = [];

        foreach (File::allFiles(app_path()) as $file) {
            $baris = explode("\n", $file->getContents());

            foreach ($baris as $i => $isi) {
                if (! str_contains($isi, 'DocumentNumber::next(') || preg_match('#^\s*(//|\*)#', $isi)) {
                    continue;
                }

                if (! str_contains(implode("\n", array_slice($baris, $i, 6)), 'withTrashed')) {
                    $salah[] = $file->getRelativePathname().':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $salah, 'Nomor dokumen yang sudah terhapus harus tetap dihitung: '.implode(', ', $salah));
    }
}
