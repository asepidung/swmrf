<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Temuan pelatihan 28 Oktober 2026 (Arif): halaman login memuat komponen yang menembak server tiap 10 detik; di peramban
 * yang sesinya tidak terbawa tembakan itu 419 dan halaman dimuat ulang di tengah orang mengetik sandi.
 */
class LoginPageNoPollingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_login_page_has_no_background_polling_component(): void
    {
        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('global-task-poller', $html);
        $this->assertStringNotContainsString('wire:poll', $html);
    }
}
