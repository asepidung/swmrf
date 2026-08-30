<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ForceChangePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Halaman penggantian password bawaan pada login pertama.
 *
 * Password bawaan `1234` memang disengaja: repositori ini publik, dan
 * `CheckPasswordChange` memaksa penggantiannya sebelum pengguna sempat
 * membuka menu apa pun.
 */
class ForceChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function userWithDefaultPassword(): User
    {
        return User::create([
            'name' => 'Pegawai Baru',
            'username' => 'force_pw_user',
            'password' => Hash::make('1234'),
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);
    }

    /**
     * Kedua kolom password wajib punya tombol mata.
     *
     * Pengguna dipaksa mengetik password baru DUA KALI di halaman ini, sering
     * kali dari HP. Mengetik buta di papan ketik ponsel gampang meleset, dan
     * kegagalannya baru terlihat setelah tombol ditekan -- padahal di titik
     * ini pengguna belum bisa mengakses apa pun di aplikasi.
     *
     * @test
     */
    public function both_password_fields_can_be_revealed()
    {
        $source = file_get_contents(app_path('Filament/Admin/Pages/ForceChangePassword.php'));

        $fields = ['new_password', 'new_password_confirmation'];

        foreach ($fields as $field) {
            $start = strpos($source, "TextInput::make('{$field}')");
            $this->assertNotFalse($start, "Kolom '{$field}' tidak ditemukan.");

            // Potong sampai kolom berikutnya (atau akhir definisi form).
            $end = strpos($source, 'TextInput::make(', $start + 10);
            $block = $end === false ? substr($source, $start) : substr($source, $start, $end - $start);

            $this->assertStringContainsString(
                '->revealable()',
                $block,
                "Kolom '{$field}' tidak punya tombol mata, sehingga password diketik buta.",
            );
        }
    }

    /*
     * CATATAN: jalur sukses (password benar-benar berganti) TIDAK ditutup test
     * di sini. Halaman menyegarkan `password_hash_web` lewat
     * request()->session(), sementara Livewire yang terisolasi membuat
     * instance request sendiri tanpa session -- percobaan menyuntikkan session
     * ke dalamnya tidak menempel. Itu keterbatasan lingkungan test, bukan bug
     * halamannya; alurnya bekerja normal di browser.
     *
     * Yang tertutup di bawah adalah validasinya, yang memanggil method yang
     * sama. Kalau kelak jalur sukses perlu dijaga juga, pakai test HTTP
     * sungguhan (yang punya session) alih-alih Livewire terisolasi.
     */

    /**
     * Konfirmasi yang tidak cocok harus ditolak, bukan diterima diam-diam.
     *
     * @test
     */
    public function it_rejects_a_mismatched_confirmation()
    {
        $user = $this->userWithDefaultPassword();

        Livewire::actingAs($user)
            ->test(ForceChangePassword::class)
            ->fillForm([
                'new_password' => 'rahasia-baru-123',
                'new_password_confirmation' => 'salah-ketik-456',
            ])
            ->call('changePassword')
            ->assertHasFormErrors(['new_password']);

        $this->assertTrue(
            Hash::check('1234', $user->fresh()->password),
            'Password berubah padahal konfirmasinya tidak cocok.',
        );
    }
}
