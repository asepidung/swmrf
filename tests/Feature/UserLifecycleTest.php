<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pengguna dinonaktifkan, tidak dihapus.
 *
 * Keputusan Project Owner, 5 September 2026: *"user mah jangan ada hapus aktif
 * non aktif aja"*.
 *
 * Alasannya bukan sekadar kehati-hatian. Ada 37 kunci asing yang menunjuk ke
 * tabel `users`, dan tiga di antaranya dulu memakai CASCADE -- menghapus satu
 * pengguna akan ikut menghapus permintaan bahan dan permintaan produk yang
 * pernah ia buat. Lima belas lainnya `nullOnDelete()`, yang diam-diam
 * menghapus jejak "siapa yang mengerjakan". Dan `users` tidak memakai hapus
 * lunak, jadi tidak ada yang bisa dipulihkan.
 */
class UserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $programmer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->programmer = User::create([
            'name' => 'Superuser', 'username' => 'super_lifecycle',
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'programmer', 'is_active' => true,
        ]);
    }

    private function pegawai(string $username = 'pegawai'): User
    {
        return User::create([
            'name' => 'Pegawai', 'username' => $username,
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);
    }

    // =====================================================================
    // Menghapus
    // =====================================================================

    /**
     * Bahkan superuser pun tidak bisa menghapus pengguna.
     *
     * Ini penting: hampir semua penjagaan lain di aplikasi ini memberi jalan
     * kepada programmer. Yang ini tidak, karena yang ditahan bukan kewenangan
     * melainkan akibatnya -- dan akibatnya sama saja siapa pun yang menekan.
     */
    public function test_no_one_can_delete_a_user_not_even_a_programmer(): void
    {
        $korban = $this->pegawai();

        $this->assertFalse(Gate::forUser($this->programmer)->allows('delete', $korban));
        $this->assertFalse(Gate::forUser($this->programmer)->allows('restore', $korban));
        $this->assertFalse(Gate::forUser($this->programmer)->allows('forceDelete', $korban));
    }

    /** Termasuk menghapus dirinya sendiri. */
    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $pegawai = $this->pegawai();

        $this->assertFalse(Gate::forUser($pegawai)->allows('delete', $pegawai));
    }

    /**
     * Tombolnya pun tidak ditawarkan.
     *
     * Membiarkannya berdiri sambil ditolak policy hanya menawarkan sesuatu
     * yang tidak akan pernah berhasil.
     */
    public function test_the_user_list_offers_no_delete_action_at_all(): void
    {
        $this->pegawai();

        $berkas = file_get_contents(base_path('app/Filament/Admin/Resources/UserResource.php'));

        $this->assertStringNotContainsString('DeleteBulkAction', $berkas);
        $this->assertStringNotContainsString('DeleteAction::make()', $berkas);

        Livewire::actingAs($this->programmer)
            ->test(ListUsers::class)
            ->assertOk();
    }

    /**
     * Yang menggantikannya sudah ada sejak awal: menonaktifkan.
     *
     * Pengguna nonaktif tidak bisa masuk lagi, sementara seluruh jejaknya di
     * dokumen lama tetap utuh.
     */
    public function test_deactivating_is_what_replaces_deleting(): void
    {
        $pegawai = $this->pegawai();
        $panel = filament()->getPanel('admin');

        $this->assertTrue($pegawai->canAccessPanel($panel));

        $pegawai->update(['is_active' => false]);

        $this->assertFalse($pegawai->fresh()->canAccessPanel($panel));

        // Dan barisnya masih ada, beserta seluruh riwayatnya.
        $this->assertDatabaseHas('users', ['id' => $pegawai->id]);
    }

    // =====================================================================
    // Izin
    // =====================================================================

    /**
     * Izin ditanyakan berkali-kali, tetapi dibaca dari basis data SEKALI.
     *
     * Sebelumnya tiap pemanggilan `hasPermission()` menembakkan kueri sendiri,
     * dan pemanggilnya banyak: hampir tiap tombol yang menggerakkan angka
     * sungguhan dijaga izin. Satu halaman daftar dua puluh baris beraksi bisa
     * menembakkan puluhan kueri untuk pertanyaan yang jawabannya sama persis.
     */
    public function test_asking_for_permissions_many_times_reads_the_database_once(): void
    {
        $pegawai = $this->pegawai();

        foreach (['view_invoices', 'edit_invoices'] as $nama) {
            $pegawai->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => $nama],
                    ['module_name' => 'Invoices', 'description' => $nama],
                )->id
            );
        }

        $pegawai = $pegawai->fresh();

        $kueri = 0;
        \DB::listen(function () use (&$kueri): void {
            $kueri++;
        });

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($pegawai->hasPermission('view_invoices'));
            $this->assertTrue($pegawai->hasPermission('edit_invoices'));
            $this->assertFalse($pegawai->hasPermission('delete_invoices'));
        }

        $this->assertSame(1, $kueri, 'Izin seharusnya dibaca sekali saja, bukan tiap kali ditanya.');
    }

    /** Programmer tidak perlu ditanyakan ke basis data sama sekali. */
    public function test_a_programmer_never_needs_the_database_to_answer(): void
    {
        $kueri = 0;
        \DB::listen(function () use (&$kueri): void {
            $kueri++;
        });

        $this->assertTrue($this->programmer->hasPermission('apa_pun_yang_tidak_ada'));

        $this->assertSame(0, $kueri);
    }

    /**
     * Ingatannya bisa dilupakan, untuk izin yang berubah di permintaan yang
     * sama.
     */
    public function test_the_remembered_answer_can_be_forgotten(): void
    {
        $pegawai = $this->pegawai();

        $this->assertFalse($pegawai->hasPermission('view_invoices'));

        $pegawai->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'view_invoices'],
                ['module_name' => 'Invoices', 'description' => 'view_invoices'],
            )->id
        );

        // Masih memakai jawaban lama...
        $this->assertFalse($pegawai->hasPermission('view_invoices'));

        // ...sampai diminta melupakannya.
        $this->assertTrue($pegawai->forgetCachedPermissions()->hasPermission('view_invoices'));
    }

    // =====================================================================
    // Kunci asing yang dulu menyeret dokumen ikut terhapus
    // =====================================================================

    /**
     * Permintaan bahan TIDAK ikut terhapus bersama pembuatnya.
     *
     * Dulu kuncinya CASCADE. Penjagaan di policy sudah menutup jalannya, tapi
     * penjagaan itu bisa dilepas orang berikutnya yang tidak tahu apa yang
     * menunggu di baliknya -- sementara kunci asing menolak dengan sendirinya,
     * di lapisan yang tidak bisa dilewati kode aplikasi mana pun.
     *
     * Di SQLite kuncinya tidak ditegakkan sama seperti MySQL, jadi yang diuji
     * di sini niat migrasinya: tidak ada satu pun kunci ke `users` yang masih
     * memakai cascade.
     */
    public function test_no_foreign_key_to_users_cascades_any_more(): void
    {
        $pelanggar = [];

        foreach (glob(database_path('migrations/*.php')) as $berkas) {
            $isi = file_get_contents($berkas);

            if (str_contains($isi, "constrained('users')->cascadeOnDelete()")
                || str_contains($isi, "constrained('users')->onDelete('cascade')")) {
                // Migrasi yang MEMPERBAIKI keadaan itu tentu menyebutnya --
                // ia memuat kata `cascade` di bagian `down()`-nya.
                if (str_contains(basename($berkas), 'a_user_must_never_take_documents_down_with_them')) {
                    continue;
                }

                $pelanggar[] = basename($berkas);
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Kunci asing ke `users` masih cascade. Menghapus pengguna akan menyeret dokumennya:\n"
            .implode("\n", $pelanggar)
        );
    }
}
