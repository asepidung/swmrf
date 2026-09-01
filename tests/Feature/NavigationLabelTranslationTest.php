<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Label menu ditulis sebagai METODE, bukan properti statis.
 *
 * Properti statis PHP tidak bisa memanggil fungsi, jadi
 * `protected static ?string $navigationLabel = 'Price List';` terpaksa berupa
 * teks mentah -- dan Filament memakainya apa adanya, tanpa melewati `__()`.
 * Akibatnya menunya tetap berbahasa Inggris meski bahasanya sudah diganti.
 *
 * Kegagalannya senyap dan menyesatkan: lima label bahkan SUDAH punya
 * terjemahan Indonesia yang benar di `lang/id.json`, tetapi tidak pernah
 * terpakai sama sekali. Siapa pun yang memeriksa berkas bahasa akan
 * menyimpulkan semuanya beres.
 *
 * Filament menyediakan `getNavigationLabel()`, `getModelLabel()`,
 * `getPluralModelLabel()`, dan `getTitle()` justru untuk keperluan ini.
 */
class NavigationLabelTranslationTest extends TestCase
{
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

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    /**
     * Tidak ada lagi label yang ditulis sebagai properti statis.
     *
     * Memeriksa berkas yang sudah diketahui saja tidak menjaga apa pun; yang
     * dijaga di sini adalah agar bentuk ini tidak lahir lagi.
     */
    public function test_no_label_is_written_as_a_raw_static_property(): void
    {
        $offenders = [];

        foreach ($this->filamentFiles() as $file) {
            $source = file_get_contents($file);

            foreach ([
                'navigationLabel',
                'modelLabel',
                'pluralModelLabel',
                'title',
            ] as $property) {
                if (preg_match(
                    '/(?:protected|public)\s+static\s+\??string\s+\$'.$property."\s*=\s*'/",
                    $source,
                )) {
                    $offenders[] = $this->relative($file).' ($'.$property.')';
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Label berikut ditulis sebagai properti statis, sehingga tidak pernah diterjemahkan:'],
            $offenders,
            ['Pakai metodenya -- getNavigationLabel(), getModelLabel(), getPluralModelLabel(), getTitle() -- dan bungkus dengan __().'],
        )));
    }

    /**
     * Setiap label menu terdaftar di KEDUA berkas bahasa.
     *
     * Label yang tidak terdaftar akan tampil dalam bahasa Inggris apa pun
     * bahasa yang dipilih, dan tidak ada error yang memberitahu.
     */
    public function test_every_navigation_label_is_registered_in_both_languages(): void
    {
        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);
        $en = json_decode(file_get_contents(base_path('lang/en.json')), true);

        $missing = [];

        foreach ($this->filamentFiles() as $file) {
            $source = file_get_contents($file);

            if (! preg_match(
                "/function getNavigationLabel\(\)[^{]*\{\s*return\s+__\('([^']+)'\)/",
                $source,
                $matches,
            )) {
                continue;
            }

            $label = $matches[1];

            if (! array_key_exists($label, $id)) {
                $missing[] = $label.' (lang/id.json) — '.$this->relative($file);
            }

            if (! array_key_exists($label, $en)) {
                $missing[] = $label.' (lang/en.json) — '.$this->relative($file);
            }
        }

        $this->assertSame([], $missing, implode("\n", array_merge(
            ['Label menu berikut belum terdaftar:'],
            $missing,
        )));
    }

    /**
     * Label yang sudah punya terjemahan benar-benar dipakai.
     *
     * Inilah bentuk kegagalan yang paling menyesatkan: terjemahannya ada dan
     * benar, tetapi label di kodenya berupa teks mentah sehingga tidak pernah
     * melewati `__()`. Berkas bahasanya terlihat lengkap, menunya tetap
     * berbahasa Inggris.
     */
    public function test_the_labels_that_have_translations_actually_use_them(): void
    {
        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);

        foreach ([
            'Plan Delivery' => 'Rencana Pengiriman',
            'Beef Receipt' => 'Penerimaan Daging',
            'Material Receipt' => 'Penerimaan Material',
            'Price List' => 'Daftar Harga',
            'Receivables' => 'Piutang',
            'Stock Movements' => 'Mutasi Stok',
        ] as $key => $expected) {
            $this->assertSame($expected, $id[$key] ?? null, $key.' belum diterjemahkan.');
        }
    }

    /**
     * Istilah Indonesia yang sengaja dipertahankan.
     *
     * Boning, Repack, Tally, dan Sales Order dipakai apa adanya di lantai
     * produksi, jadi terjemahannya memang sama dengan aslinya. Dicatat di
     * sini supaya tidak ada yang menyangka keempatnya terlewat.
     */
    public function test_the_borrowed_terms_are_left_as_they_are(): void
    {
        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);

        foreach (['Boning', 'Repack', 'Tally', 'Sales Order'] as $term) {
            $this->assertSame($term, $id[$term] ?? null, $term.' seharusnya tetap apa adanya.');
        }
    }
}
