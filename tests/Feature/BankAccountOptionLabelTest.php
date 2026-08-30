<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Rekening dipilih lewat `initial`, bukan `bank_name`.
 *
 * `bank_name` TIDAK unique -- dua rekening di bank yang sama tampil identik di
 * dropdown, dan finance tidak punya cara membedakannya. Kesalahannya tidak
 * menimbulkan error: uang tetap tercatat, hanya keluar dari kas yang salah,
 * dan baru ketahuan saat saldo bank direkonsiliasi.
 *
 * `initial` unique di database sejak migrasi awal, jadi itu satu-satunya
 * label yang menjamin setiap baris bisa dibedakan.
 *
 * Dipindai ke seluruh aplikasi, bukan cuma empat form yang kebetulan sudah
 * ketahuan, karena form pembayaran berikutnya akan disalin dari salah satunya.
 */
class BankAccountOptionLabelTest extends TestCase
{
    public function test_no_form_offers_bank_accounts_by_their_non_unique_name(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (str_contains($contents, "pluck('bank_name'")) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Dropdown rekening wajib memakai `initial`, bukan `bank_name` yang tidak unique -- "
                ."dua rekening di bank yang sama akan tampil identik. Yang melanggar:\n"
                .implode("\n", $offenders),
        );
    }
}
