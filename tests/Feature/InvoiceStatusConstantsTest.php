<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Tests\TestCase;

/**
 * Status invoice hanya boleh ditulis di SATU tempat.
 *
 * Statusnya kolom teks berbahasa Indonesia, dan "sudah dibayar atau belum"
 * ditentukan dengan membandingkannya ke teks itu. Satu salah ketik berarti
 * invoice yang sudah lunas ikut terhitung sebagai piutang -- tanpa satu pun
 * gejala, karena perbandingan string yang meleset tidak menghasilkan error,
 * hanya jawaban yang salah.
 *
 * Sampai 4 September 2026 teksnya ditulis ulang di dua puluh tujuh tempat dan
 * hanya 'Lunas' yang punya konstanta. Test ini yang menahannya tidak kembali.
 */
class InvoiceStatusConstantsTest extends TestCase
{
    /** Berkas yang memang BOLEH memuat teksnya. */
    private const DIKECUALIKAN = [
        'app/Models/Invoice.php',
        'tests/Feature/InvoiceStatusConstantsTest.php',
    ];

    public function test_no_file_writes_an_invoice_status_as_a_bare_string(): void
    {
        $status = [
            Invoice::STATUS_UNPAID,
            Invoice::STATUS_PAID,
            Invoice::STATUS_EXCHANGE_PENDING,
            Invoice::STATUS_EXCHANGED,
        ];

        $pelanggar = [];

        foreach ($this->berkasYangDisisir() as $berkas) {
            $isi = file_get_contents($berkas);

            // Komentar dibuang lebih dulu: menyebut nilainya di dalam
            // penjelasan justru membantu orang berikutnya, dan menahannya di
            // sana hanya membuat catatannya jadi kabur.
            $isi = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $isi);

            foreach ($status as $teks) {
                if (str_contains($isi, "'".$teks."'")) {
                    $pelanggar[] = $this->jalurRelatif($berkas).' -> '.$teks;
                }
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Status invoice ditulis sebagai teks mentah. Pakai konstanta Invoice::STATUS_*:\n"
            .implode("\n", $pelanggar)
        );
    }

    public function test_every_status_the_form_offers_has_a_constant(): void
    {
        $this->assertSame(
            [
                Invoice::STATUS_UNPAID,
                Invoice::STATUS_EXCHANGE_PENDING,
                Invoice::STATUS_EXCHANGED,
                Invoice::STATUS_PAID,
            ],
            array_keys(Invoice::statuses()),
        );
    }

    /** @return \Generator<string> */
    private function berkasYangDisisir(): \Generator
    {
        $dikecualikan = array_map(
            fn (string $jalur): string => str_replace('/', DIRECTORY_SEPARATOR, base_path($jalur)),
            self::DIKECUALIKAN,
        );

        foreach (['app', 'resources/views'] as $akar) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($akar), \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $berkas) {
                if (! $berkas->isFile() || ! in_array($berkas->getExtension(), ['php'], true)) {
                    continue;
                }

                if (in_array($berkas->getPathname(), $dikecualikan, true)) {
                    continue;
                }

                yield $berkas->getPathname();
            }
        }
    }

    private function jalurRelatif(string $jalur): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $jalur);
    }
}
