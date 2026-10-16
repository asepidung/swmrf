<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Syarat PWA bisa dipasang di Chrome Android dan Safari iPhone, dan ajakan pasangnya tampil di halaman login.
 */
class PwaInstallabilityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        return json_decode(file_get_contents(public_path('manifest.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @test */
    public function the_manifest_meets_the_chrome_installability_requirements(): void
    {
        $m = $this->manifest();

        $this->assertNotEmpty($m['name']);
        $this->assertNotEmpty($m['short_name']);
        $this->assertSame('standalone', $m['display']);
        $this->assertSame('/admin', $m['start_url']);
        $this->assertSame('/admin', $m['id']);
        $this->assertSame('/', $m['scope']);

        $sizes = collect($m['icons'])->pluck('sizes')->all();
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', collect($m['icons'])->pluck('purpose')->all());

        foreach ($m['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')), "Ikon {$icon['src']} harus ada.");
        }
    }

    /** @test */
    public function the_service_worker_exists_and_handles_fetch_which_chrome_requires(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("addEventListener('fetch'", $sw);
        $this->assertFileExists(public_path('offline.html'));
    }

    /** @test */
    public function the_login_page_links_the_manifest_registers_the_worker_and_carries_the_ios_tags(): void
    {
        $html = $this->get('/admin/login')->assertSuccessful()->getContent();

        $this->assertStringContainsString('rel="manifest" href="/manifest.json"', $html);
        $this->assertStringContainsString("navigator.serviceWorker.register('/sw.js')", $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('name="apple-mobile-web-app-capable" content="yes"', $html);
        $this->assertStringContainsString('name="apple-mobile-web-app-title" content="SWM ERP"', $html);
    }

    /** @test */
    public function the_login_page_carries_the_install_prompt_for_android_and_the_iphone_hint(): void
    {
        $html = $this->get('/admin/login')->assertSuccessful()->getContent();

        $this->assertStringContainsString('pwaInstall()', $html);
        $this->assertStringContainsString('beforeinstallprompt', $html, 'Chrome/Android: tombol Pasang.');
        $this->assertStringContainsString('Add to Home Screen', $html, 'Safari iPhone: petunjuk Tambahkan ke Layar Utama.');
        $this->assertStringContainsString('three dots', $html, 'Android tanpa peristiwa install: langkah manual titik tiga.');
        $this->assertStringContainsString('role="dialog"', $html, 'Dicegat di layar penuh.');
        $this->assertStringContainsString('display-mode: standalone', $html, 'Tidak tampil bila sudah terpasang.');
    }
}
