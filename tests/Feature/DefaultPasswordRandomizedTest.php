<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** Keputusan Ayah: tidak ada lagi kata sandi 1234 (akun lama diacak lewat migrasi, akun baru dapat angka acak). */
class DefaultPasswordRandomizedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_temporary_password_is_a_random_eight_digit_number(): void
    {
        $a = TemporaryPassword::generate();

        $this->assertMatchesRegularExpression('/^\d{8}$/', $a);
        $this->assertNotSame($a, TemporaryPassword::generate().'x');
    }

    /** @test */
    public function the_migration_randomizes_only_accounts_still_on_1234(): void
    {
        $default = User::factory()->create(['password' => Hash::make('1234'), 'is_active' => false]);
        $aktifBelumGanti = User::factory()->create(['password' => Hash::make('1234'), 'is_active' => true]);
        $sudahGanti = User::factory()->create(['password' => Hash::make('sandi-pribadi-77'), 'is_active' => true]);
        $hashLama = $sudahGanti->password;

        (require database_path('migrations/2026_11_18_500000_randomize_default_1234_passwords.php'))->up();

        foreach ([$default, $aktifBelumGanti] as $u) {
            $this->assertFalse(Hash::check('1234', $u->fresh()->password), '1234 tidak berlaku lagi');
            $this->assertTrue($u->fresh()->must_change_password);
        }
        $this->assertSame($hashLama, $sudahGanti->fresh()->password, 'yang sudah punya sandi sendiri tidak disentuh');
        $this->assertTrue(Hash::check('sandi-pribadi-77', $sudahGanti->fresh()->password));
    }

    /** @test */
    public function a_new_account_gets_a_random_temporary_password_and_must_change_it(): void
    {
        $admin = User::create(['name' => 'A', 'username' => 'adm_baru', 'password' => 'secret-password', 'gender' => 'L', 'role' => 'programmer', 'is_active' => true]);

        Livewire::actingAs($admin)->test(CreateUser::class)
            ->fillForm(['name' => 'Karyawan Baru', 'username' => 'karyawan_baru', 'gender' => 'L', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $baru = User::where('username', 'karyawan_baru')->firstOrFail();
        $this->assertFalse(Hash::check('1234', $baru->password), 'tidak lagi 1234');
        $this->assertTrue($baru->must_change_password);

        $isi = collect(session()->get('filament.notifications', []))->pluck('body')->implode(' ');
        $this->assertMatchesRegularExpression('/karyawan_baru.*\d{8}/', $isi, 'sandi sementara ditampilkan ke admin');
    }
}
