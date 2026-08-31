<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Gembok menggambarkan KEADAAN dokumen, bukan aksi tombolnya.
 *
 * Keputusan Project Owner: belum terkunci berarti hijau dengan gembok
 * TERBUKA, sudah terkunci berarti merah dengan gembok TERTUTUP.
 *
 * Menggambarkan aksi justru menyesatkan. Gembok tertutup berwarna merah pada
 * dokumen yang JUSTRU masih terbuka terbaca seolah dokumen itu sudah
 * dikunci -- dan keliru membaca status kunci berarti keliru menyangka sebuah
 * dokumen masih bisa diubah, atau sebaliknya menyangka sudah final padahal
 * belum.
 *
 * GR Beef dan GR Material sejak awal sudah benar dengan satu tombol dinamis.
 * Boning dan Repack memakai dua tombol terpisah, dan keduanya terbalik.
 */
class LockIconMeaningTest extends TestCase
{
    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function twoButtonModules(): array
    {
        return [
            'Boning' => ['Filament/Admin/Resources/BoningResource.php', ''],
            'Repack' => ['Filament/Admin/Resources/RepackResource.php', ''],
        ];
    }

    private function actionBlock(string $file, string $action): string
    {
        $source = file_get_contents(app_path($file));
        $start = strpos($source, "Action::make('".$action."')");

        $this->assertNotFalse($start, "Aksi {$action} tidak ditemukan di {$file}.");

        // Jendelanya cukup lebar untuk menampung komentar penjelas di
        // dalam definisi aksinya.
        return substr($source, $start, 1200);
    }

    /**
     * Tombol yang MENGUNCI hanya muncul saat dokumen masih terbuka, jadi
     * gemboknya harus terbuka dan hijau.
     *
     * @dataProvider twoButtonModules
     */
    public function test_the_lock_button_shows_the_document_is_still_open(string $file): void
    {
        $block = $this->actionBlock($file, 'lock');

        $this->assertStringContainsString('heroicon-o-lock-open', $block);
        $this->assertStringContainsString("->color('success')", $block);
    }

    /**
     * Tombol yang MEMBUKA hanya muncul saat dokumen sudah terkunci, jadi
     * gemboknya harus tertutup dan merah.
     *
     * @dataProvider twoButtonModules
     */
    public function test_the_unlock_button_shows_the_document_is_locked(string $file): void
    {
        $block = $this->actionBlock($file, 'unlock');

        $this->assertStringContainsString('heroicon-o-lock-closed', $block);
        $this->assertStringContainsString("->color('danger')", $block);
    }

    /**
     * Modul yang memakai SATU tombol dinamis tetap harus sepakat dengan
     * aturan yang sama.
     */
    public function test_the_single_button_modules_follow_the_same_meaning(): void
    {
        foreach ([
            'Filament/Admin/Resources/GoodsReceiptProductResource.php',
            'Filament/Admin/Resources/GoodsReceiptMaterialResource.php',
        ] as $file) {
            $block = $this->actionBlock($file, 'lock');

            $this->assertStringContainsString(
                "is_locked ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open'",
                $block,
                basename($file).': ikon gembok tidak mengikuti status kunci.',
            );

            $this->assertStringContainsString(
                "is_locked ? 'danger' : 'success'",
                $block,
                basename($file).': warna gembok tidak mengikuti status kunci.',
            );
        }
    }
}
