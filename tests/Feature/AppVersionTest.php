<?php

namespace Tests\Feature;

use App\Livewire\VersionNotice;
use App\Models\User;
use App\Support\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Versi aplikasi (permintaan Ayah, 31 Oktober 2026): nomor di footer, riwayat lengkap saat diklik, dan jendela "Yang baru"
 * SEKALI per pengguna per versi.
 */
class AppVersionTest extends TestCase
{
    use RefreshDatabase;

    private function user(?string $seen = null): User
    {
        $user = User::create(['name' => 'Petugas', 'username' => 'u_'.uniqid(), 'password' => 'secret-password', 'gender' => 'L', 'role' => 'employee', 'is_active' => true]);
        $user->forceFill(['seen_version' => $seen])->saveQuietly();

        return $user->fresh();
    }

    /** Dua entri uji: 2.0.0 (rilis) dan 2.1.0 (modul baru), dengan 2.1.0 sebagai versi sekarang. */
    private function withNewerVersion(): void
    {
        config(['changelog.entries' => [
            ['version' => '2.0.0', 'date' => '2026-11-02', 'kind' => 'release', 'title' => 'Aplikasi baru', 'items' => ['Pindah dari legacy.']],
            ['version' => '2.1.0', 'date' => '2026-11-10', 'kind' => 'modul', 'title' => 'Modul Pengiriman', 'items' => ['Jadwal kirim harian.']],
        ]]);
    }

    public function test_the_current_version_is_the_newest_entry_whatever_the_file_order(): void
    {
        $this->withNewerVersion();

        $this->assertSame('2.1.0', AppVersion::current());
        $this->assertSame(['2.1.0', '2.0.0'], array_column(AppVersion::entries(), 'version'));
    }

    public function test_versions_are_compared_as_numbers_not_text(): void
    {
        config(['changelog.entries' => [
            ['version' => '2.9.0', 'date' => '2026-11-02', 'kind' => 'modul', 'title' => 'a', 'items' => []],
            ['version' => '2.10.0', 'date' => '2026-11-03', 'kind' => 'modul', 'title' => 'b', 'items' => []],
        ]]);

        $this->assertSame('2.10.0', AppVersion::current());
    }

    public function test_the_shipped_changelog_starts_at_2_0_0_and_is_well_formed(): void
    {
        $entries = AppVersion::entries();

        $this->assertNotEmpty($entries);
        $this->assertSame('2.0.0', end($entries)['version']);

        foreach ($entries as $entry) {
            $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $entry['version']);
            $this->assertContains($entry['kind'], ['release', 'modul', 'fix']);
            $this->assertNotEmpty($entry['items'], "Ver {$entry['version']}: catatan tidak boleh kosong.");
        }
    }

    public function test_the_footer_shows_the_version_from_the_changelog_and_opens_the_history(): void
    {
        $this->withNewerVersion();

        $html = view('filament.admin.footer')->render();

        $this->assertStringContainsString('Ver 2.1.0', $html);
        $this->assertStringContainsString('open-version-history', $html);
    }

    public function test_a_user_never_seen_before_is_not_flooded_and_is_marked_as_up_to_date(): void
    {
        $this->withNewerVersion();
        $user = $this->user(null);

        Livewire::actingAs($user)->test(VersionNotice::class)->assertSet('unseen', []);

        $this->assertSame('2.1.0', $user->fresh()->seen_version);
    }

    public function test_a_user_who_saw_an_older_version_gets_the_news_once(): void
    {
        $this->withNewerVersion();
        $user = $this->user('2.0.0');

        Livewire::actingAs($user)->test(VersionNotice::class)
            ->assertSee('Modul Pengiriman')
            ->assertSee('Jadwal kirim harian.')
            ->call('acknowledge')
            ->assertSet('unseen', []);

        $this->assertSame('2.1.0', $user->fresh()->seen_version);

        // Dibuka lagi (halaman berikutnya): tidak muncul lagi.
        Livewire::actingAs($user->fresh())->test(VersionNotice::class)->assertSet('unseen', []);
    }

    public function test_a_user_who_is_up_to_date_sees_no_notice(): void
    {
        $this->withNewerVersion();

        Livewire::actingAs($this->user('2.1.0'))->test(VersionNotice::class)->assertSet('unseen', []);
    }

    public function test_only_the_versions_after_the_last_seen_one_are_listed(): void
    {
        config(['changelog.entries' => [
            ['version' => '2.0.0', 'date' => '2026-11-02', 'kind' => 'release', 'title' => 'Rilis', 'items' => ['a']],
            ['version' => '2.1.0', 'date' => '2026-11-10', 'kind' => 'modul', 'title' => 'Modul satu', 'items' => ['b']],
            ['version' => '2.1.1', 'date' => '2026-11-11', 'kind' => 'fix', 'title' => 'Perbaikan satu', 'items' => ['c']],
        ]]);

        $this->assertSame(['2.1.1', '2.1.0'], array_column(AppVersion::entriesAfter('2.0.0'), 'version'));
        $this->assertSame(['2.1.1'], array_column(AppVersion::entriesAfter('2.1.0'), 'version'));
        $this->assertSame([], AppVersion::entriesAfter('2.1.1'));
    }

    public function test_the_full_history_is_always_available_in_the_component(): void
    {
        $this->withNewerVersion();

        Livewire::actingAs($this->user('2.1.0'))->test(VersionNotice::class)
            ->assertSee('Aplikasi baru')
            ->assertSee('Modul Pengiriman');
    }

    public function test_the_login_page_does_not_load_the_version_component(): void
    {
        $this->get('/admin/login')->assertOk()->assertDontSee('version-whats-new', false);
    }
}
