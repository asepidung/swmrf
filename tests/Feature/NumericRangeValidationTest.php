<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Batas angka yang tidak membatasi apa pun.
 *
 * `->minValue(0)->maxValue(100)` di Filament hanya menghasilkan aturan
 * "min:0" dan "max:100". Tanpa aturan numerik yang menyertainya, Laravel
 * memeriksa PANJANG KARAKTER, bukan nilainya -- sehingga "500" lolos karena
 * cuma tiga huruf, dan "999999" pun lolos.
 *
 * Diskon di Sales Order dijaga persis seperti itu, dan angkanya dipakai
 * langsung oleh Invoice sebagai `gross * (discount / 100)`. Diskon 500%
 * menghasilkan baris tagihan MINUS tanpa satu pun error di sepanjang
 * jalannya.
 *
 * Perbaikannya tidak boleh memakai `->numeric()`: pemanggilan itu membuat
 * `getType()` mengembalikan "number", lengkap dengan tombol panah yang sudah
 * dilarang untuk kolom uang dan berat karena gampang tergeser.
 */
class NumericRangeValidationTest extends TestCase
{
    /** Inilah sebabnya batas tanpa aturan numerik itu berbahaya. */
    public function test_a_range_without_a_numeric_rule_checks_length_not_value(): void
    {
        $this->assertTrue(
            Validator::make(['discount' => '500'], ['discount' => ['min:0', 'max:100']])->passes(),
            'Dugaan dasar test ini keliru: batas tanpa aturan numerik ternyata menolak 500.',
        );

        $this->assertFalse(
            Validator::make(['discount' => '500'], ['discount' => ['numeric', 'min:0', 'max:100']])->passes(),
        );
    }

    /** Diskon Sales Order sekarang benar-benar dibatasi 0-100. */
    public function test_the_sales_order_discount_is_really_capped(): void
    {
        $field = $this->fieldBlock(
            app_path('Filament/Admin/Resources/SalesOrderResource.php'),
            'discount',
        );

        $this->assertStringContainsString("'numeric'", $field);
        $this->assertStringContainsString("'max:100'", $field);

        // Tombol panah tetap dilarang: ->numeric() membuat type=number.
        $this->assertStringNotContainsString('->numeric()', $field);
        $this->assertStringNotContainsString('->minValue(', $field);
        $this->assertStringNotContainsString('->maxValue(', $field);
    }

    /**
     * Pemindaian seluruh aplikasi.
     *
     * Memeriksa berkas yang sudah diketahui bermasalah saja tidak menjaga
     * apa-apa; yang dijaga di sini adalah agar bentuk seperti ini tidak
     * lahir lagi di modul mana pun.
     */
    public function test_no_field_anywhere_has_a_range_without_a_numeric_rule(): void
    {
        $offenders = [];

        foreach ($this->filamentFiles() as $file) {
            $source = file_get_contents($file);
            $offset = 0;

            while (($start = strpos($source, 'TextInput::make(', $offset)) !== false) {
                $offset = $start + 10;
                $block = substr($source, $start, $this->blockLength($source, $start));

                if (! preg_match('/->minValue\(|->maxValue\(/', $block)) {
                    continue;
                }

                // Aturan validasi yang sungguhan. Perhatikan bahwa
                // 'inputmode' => 'numeric' TIDAK dihitung: itu cuma petunjuk
                // papan ketik untuk peramban, bukan aturan validasi.
                $guarded = preg_match('/->numeric\(|->integer\(/', $block)
                    || preg_match('/->rules?\([^)]*(numeric|integer)/s', $block);

                if ($guarded) {
                    continue;
                }

                preg_match('/TextInput::make\(.([a-z_0-9]+)./i', $block, $matches);

                $offenders[] = sprintf(
                    '%s baris %d (%s)',
                    str_replace(base_path().DIRECTORY_SEPARATOR, '', $file),
                    substr_count(substr($source, 0, $start), "\n") + 1,
                    $matches[1] ?? '?',
                );
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Batas angka tanpa aturan numerik hanya memeriksa panjang karakter:'],
            $offenders,
            ["Pakai ->rules(['numeric', 'min:x', 'max:y']), bukan ->numeric() yang memunculkan tombol panah."],
        )));
    }

    /** @return array<int, string> */
    private function filamentFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /** Panjang definisi satu field, sampai field berikutnya dimulai. */
    private function blockLength(string $source, int $start): int
    {
        $ends = array_filter([
            strpos($source, 'TextInput::make(', $start + 10),
            strpos($source, 'Select::make(', $start + 10),
            strpos($source, 'Textarea::make(', $start + 10),
        ], fn ($position) => $position !== false);

        return ($ends ? min($ends) : strlen($source)) - $start;
    }

    private function fieldBlock(string $file, string $field): string
    {
        $source = file_get_contents($file);
        $start = strpos($source, "TextInput::make('".$field."')");

        $this->assertNotFalse($start, "Field {$field} tidak ditemukan.");

        return substr($source, $start, $this->blockLength($source, $start));
    }
}
