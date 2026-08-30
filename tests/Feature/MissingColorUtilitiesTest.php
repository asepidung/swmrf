<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Setiap kelas warna yang dipakai blade harus benar-benar ada CSS-nya.
 *
 * Filament hanya menyertakan kelas utilitas yang dipakai kode Filament
 * SENDIRI. Proyek ini tidak mengompilasi tema Filament kustom, jadi kelas
 * seperti `bg-warning-500` atau `divide-y` tidak menghasilkan apa pun.
 *
 * Yang membuat ini mahal: kegagalannya tidak terlihat seperti kegagalan.
 * Elemennya tetap ada, tetap di posisinya, tetap bisa diklik -- hanya tidak
 * berwarna. Tombol "Damaged Label" tampil polos berbulan-bulan, dan garis
 * pemisah tabel hilang di banyak halaman, tanpa satu pun error.
 *
 * Karena itu yang dijaga di sini adalah POLANYA, bukan 75 kelas yang
 * kebetulan sudah ketahuan. Blade baru yang memakai kelas warna di luar
 * daftar akan langsung menggagalkan test ini, lengkap dengan nama kelas dan
 * berkasnya -- bukan ditemukan berbulan-bulan kemudian oleh Project Owner
 * yang menyadari sebuah tombol terlihat aneh.
 */
class MissingColorUtilitiesTest extends TestCase
{
    private const COLORS = 'primary|success|warning|danger|info|gray';

    private const PROPS = 'bg|text|border|ring|from|via|to|divide|fill|stroke|outline';

    /** CSS yang benar-benar sampai ke browser: bawaan Filament + tambahan kita. */
    private function availableCss(): string
    {
        $css = '';

        foreach ([
            'css/filament/filament/app.css',
            'css/filament/forms/forms.css',
            'css/filament/support/support.css',
        ] as $file) {
            $path = public_path($file);

            if (is_file($path)) {
                $css .= file_get_contents($path);
            }
        }

        return $css.file_get_contents(
            resource_path('views/filament/admin/missing-color-utilities.blade.php')
        );
    }

    /** @return array<string, array<int, string>> kelas => berkas yang memakainya */
    private function colorClassesUsedInBlades(): array
    {
        $pattern = '/(?:[a-z0-9-]+:)*(?:'.self::PROPS.')-(?:'.self::COLORS.')-\d{2,3}(?:\/\d{1,3})?\b'
            .'|(?:[a-z0-9-]+:)*(?:divide-[xy]|bg-gradient-to-[a-z]{1,2})\b/';

        $used = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($files as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            // Berkas definisinya sendiri jelas menyebut semua kelas itu.
            if ($file->getFilename() === 'missing-color-utilities.blade.php') {
                continue;
            }

            if (preg_match_all($pattern, file_get_contents($file->getPathname()), $matches)) {
                foreach (array_unique($matches[0]) as $class) {
                    $used[$class][] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        return $used;
    }

    public function test_every_color_class_used_in_a_blade_has_css_behind_it(): void
    {
        $css = $this->availableCss();
        $missing = [];

        foreach ($this->colorClassesUsedInBlades() as $class => $files) {
            $selector = '.'.str_replace([':', '/'], ['\\:', '\\/'], $class);

            if (! str_contains($css, $selector)) {
                $missing[] = $class.'  ('.implode(', ', array_unique($files)).')';
            }
        }

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Kelas warna berikut dipakai di blade tapi tidak menghasilkan CSS apa pun. Elemennya "
                ."akan tampil TANPA warna, dan tidak akan ada error yang memberitahu. Tambahkan "
                ."definisinya di resources/views/filament/admin/missing-color-utilities.blade.php, "
                ."atau pakai komponen Filament yang sudah punya CSS-nya:\n".implode("\n", $missing),
        );
    }

    /**
     * Aturan kita memakai `rgba(var(--warning-500), ...)`, jadi variabelnya
     * harus benar-benar ada di halaman -- bukan sekadar diasumsikan ada dari
     * membaca kode Filament. Kalau Filament berhenti menyuntikkannya, seluruh
     * berkas ini jadi tidak berefek dan lagi-lagi tanpa gejala.
     */
    public function test_filament_really_injects_the_color_variables_we_depend_on(): void
    {
        $html = $this->get('/admin/login')->getContent();

        foreach (['--warning-500', '--success-500', '--danger-500', '--info-500', '--gray-800'] as $variable) {
            $this->assertStringContainsString(
                $variable,
                $html,
                "Filament tidak lagi menyuntikkan {$variable}; aturan warna kita jadi tidak berefek.",
            );
        }

        // Dan berkas kita ikut terpasang di halaman yang sama.
        $this->assertStringContainsString('.text-warning-600', $html);
    }

    /** Definisinya tidak ada gunanya kalau tidak pernah dimuat ke halaman. */
    public function test_the_stylesheet_is_actually_registered_on_the_panel(): void
    {
        $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

        $this->assertStringContainsString('filament.admin.missing-color-utilities', $provider);
        $this->assertStringContainsString('HEAD_END', $provider);
    }
}
