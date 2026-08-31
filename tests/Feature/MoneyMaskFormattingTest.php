<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Mask uang tidak boleh menerima angka berdesimal.
 *
 * Mask `$money` membuang seluruh karakter non-digit dari nilai yang masuk.
 * Nilai dari kolom `decimal(15,2)` berbentuk "1200000.00", jadi dua nol di
 * belakang titik ikut terbaca sebagai digit dan angkanya tampil SERATUS KALI
 * LIPAT: Rp 1,2 juta menjadi 120 juta.
 *
 * Dilaporkan Project Owner dari halaman Financial Losses, di mana daftar dan
 * halaman detail menampilkan angka yang berbeda seratus kali untuk baris yang
 * sama.
 *
 * Pada field yang hanya dibaca, akibatnya "cuma" angka yang salah di layar.
 * Pada field yang bisa DIEDIT akibatnya jauh lebih jauh: di Sales Order,
 * `down_payment` dimuat sebagai 50.000.000, lalu `stripCharacters('.')`
 * membuang titiknya saat disimpan -- dan yang tersimpan benar-benar 50 juta.
 * Uang muka pelanggan membengkak seratus kali lipat setiap kali form dibuka
 * lalu disimpan ulang, tanpa satu pun error.
 *
 * Aturannya sekarang: setiap field ber-mask uang WAJIB punya
 * `formatStateUsing` yang membuang desimalnya lebih dulu.
 */
class MoneyMaskFormattingTest extends TestCase
{
    /** @return array<string, string> berkas => isinya */
    private function filamentSources(): array
    {
        $sources = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname())]
                    = file_get_contents($file->getPathname());
            }
        }

        return $sources;
    }

    public function test_every_money_masked_field_formats_its_state_first(): void
    {
        $offenders = [];

        foreach ($this->filamentSources() as $name => $source) {
            if (! str_contains($source, '$money($input')) {
                continue;
            }

            // Pisahkan per definisi field, supaya `formatStateUsing` milik
            // field lain di berkas yang sama tidak ikut menutupi pelanggaran.
            $fields = preg_split("/(?=TextInput::make\()/", $source);

            foreach ($fields as $field) {
                if (! str_contains($field, '$money($input')) {
                    continue;
                }

                if (! str_contains($field, 'formatStateUsing')) {
                    preg_match("/TextInput::make\('([^']+)'\)/", $field, $match);
                    $offenders[] = $name.' -> '.($match[1] ?? '?');
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Field berikut memasang mask uang tanpa membuang desimalnya lebih dulu. Nilai "
                ."decimal(15,2) akan tampil SERATUS KALI LIPAT, dan bila fieldnya bisa disimpan, "
                ."angka itulah yang masuk ke database. Tambahkan:\n"
                ."    ->formatStateUsing(fn (\$state) => number_format((float) \$state, 0, ',', '.'))\n\n"
                .implode("\n", $offenders),
        );
    }

    /**
     * Bukti perilakunya, bukan cuma aturannya.
     *
     * Menirukan apa yang dilakukan mask terhadap nilai mentah dari database,
     * supaya alasan aturan di atas tetap terbaca kalau suatu saat ada yang
     * mempertanyakannya.
     */
    public function test_the_mask_would_read_a_decimal_as_a_hundredfold(): void
    {
        $fromDatabase = '1200000.00';

        // Mask membuang karakter non-digit.
        $asMaskReadsIt = (int) preg_replace('/\D/', '', $fromDatabase);

        $this->assertSame(120000000, $asMaskReadsIt);
        $this->assertSame(1200000, (int) (float) $fromDatabase);

        // Dan inilah yang dilakukan formatStateUsing sebelum mask bekerja.
        $this->assertSame('1.200.000', number_format((float) $fromDatabase, 0, ',', '.'));
    }
}
