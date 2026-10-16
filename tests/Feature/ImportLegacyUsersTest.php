<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `legacy:import-users`: id legacy, kata sandi legacy DIABAIKAN (semua 1234, wajib diganti saat masuk pertama),
 * tanpa hak akses, tolak bentrok, idempoten. Fixture dikarang sendiri (berkas asli berisi hash dan tidak di-commit).
 */
class ImportLegacyUsersTest extends TestCase
{
    use RefreshDatabase;

    private const CREATE = <<<'SQL'
CREATE TABLE `users` (
  `idusers` int(11) NOT NULL AUTO_INCREMENT,
  `userid` varchar(100) DEFAULT NULL,
  `passuser` varchar(100) DEFAULT NULL,
  `fullname` varchar(30) DEFAULT NULL,
  `status` enum('AKTIF','INAKTIF','','') NOT NULL,
  PRIMARY KEY (`idusers`)
) ENGINE=InnoDB;

SQL;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $legacyHash = password_hash('sandi-lama', PASSWORD_BCRYPT, ['cost' => 4]);
        $c = '(`idusers`,`userid`,`passuser`,`fullname`,`status`)';

        $this->path = tempnam(sys_get_temp_dir(), 'legacy-users-').'.sql';
        file_put_contents($this->path, self::CREATE
            ."INSERT INTO `users` {$c} VALUES ('1','ASEP','{$legacyHash}','Asep','AKTIF');\n"
            ."INSERT INTO `users` {$c} VALUES ('2','rafi','','RAFI  ','AKTIF');\n"
            ."INSERT INTO `users` {$c} VALUES ('16','angga','{$legacyHash}','ANGGA','INAKTIF');\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(bool $apply = true): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('legacy:import-users', ['--source' => $this->path] + ($apply ? ['--apply' => true] : []));
    }

    /** @test */
    public function a_dry_run_writes_nothing(): void
    {
        $this->importer(false)->expectsOutputToContain('DRY-RUN')->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    /** @test */
    public function apply_keeps_legacy_ids_sets_the_default_password_and_gives_no_permissions(): void
    {
        $this->importer()->assertSuccessful();

        $rafi = User::findOrFail(2);
        $this->assertSame('rafi', $rafi->username);
        $this->assertSame('RAFI', $rafi->name, 'Spasi dirapikan.');
        $this->assertSame('employee', $rafi->role);
        $this->assertTrue($rafi->is_active);
        $this->assertSame(0, $rafi->permissions()->count(), 'Hak akses kosong.');
        $this->assertFalse((bool) User::findOrFail(16)->is_active);

        foreach ([1, 2, 16] as $id) {
            $this->assertTrue(Hash::check('1234', DB::table('users')->where('id', $id)->value('password')), "id {$id}: kata sandi bawaan 1234");
        }
    }

    /** @test */
    public function the_legacy_password_is_ignored_and_the_default_one_works_for_an_active_user_only(): void
    {
        $this->importer()->assertSuccessful();

        Livewire::test(Login::class)
            ->fillForm(['username' => 'ASEP', 'password' => 'sandi-lama'])
            ->call('authenticate')
            ->assertHasErrors();
        $this->assertGuest();

        Livewire::test(Login::class)
            ->fillForm(['username' => 'ASEP', 'password' => '1234'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertSame(1, Auth::id());
        Auth::logout();

        Livewire::test(Login::class)
            ->fillForm(['username' => 'angga', 'password' => '1234'])
            ->call('authenticate')
            ->assertHasErrors();
        $this->assertGuest();
    }

    /** @test */
    public function an_imported_user_is_sent_to_change_the_default_password_on_first_login(): void
    {
        $this->importer()->assertSuccessful();

        $this->actingAs(User::findOrFail(1))
            ->get('/admin')
            ->assertRedirect(route('filament.admin.pages.force-change-password'));
    }

    /** @test */
    public function running_twice_is_idempotent(): void
    {
        $this->importer()->assertSuccessful();
        $this->importer()->expectsOutputToContain('sudah ada (dilewati): 3')->assertSuccessful();

        $this->assertSame(3, User::count());
    }

    /** @test */
    public function a_clash_on_id_or_username_is_refused_and_nothing_is_written(): void
    {
        User::factory()->create(['id' => 2, 'username' => 'orang-lain']);
        User::factory()->create(['id' => 100, 'username' => 'ASEP']);

        $this->importer()->assertFailed();

        $this->assertNull(User::find(1), 'Satu penolakan membatalkan seluruh penulisan.');
        $this->assertNull(User::find(16));
        $this->assertSame('orang-lain', User::find(2)->username);
    }

    /** @test */
    public function an_existing_unrelated_user_is_left_untouched(): void
    {
        User::factory()->create(['id' => 100, 'username' => 'saepullrock', 'role' => 'programmer']);
        $before = DB::table('users')->where('id', 100)->first();

        $this->importer()->assertSuccessful();

        $this->assertEquals($before, DB::table('users')->where('id', 100)->first());
    }
}
