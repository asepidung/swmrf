<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Hak akses di form User dikelompokkan ke tab mengikuti grup sidebar.
 *
 * Sebelumnya 46 seksi modul ditumpuk vertikal dalam satu halaman. Itu bukan
 * sekadar tidak nyaman: memilih satu per satu dari 46 seksi melelahkan,
 * sehingga lebih gampang mencentang semuanya -- dan begitulah akun uji
 * berakhir dengan 181 permission. Form yang menyulitkan pemberian hak secara
 * selektif melahirkan pemberian hak yang serampangan.
 */
class UserPermissionFormTest extends TestCase
{
    use RefreshDatabase;

    protected User $programmer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->programmer = User::create([
            'name' => 'Programmer',
            'username' => 'perm_form_programmer',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);
    }

    protected function seedPermissions(array $modules): void
    {
        foreach ($modules as $module => $names) {
            foreach ($names as $name) {
                Permission::create([
                    'name' => $name,
                    'module_name' => $module,
                    'description' => 'View ' . $module,
                ]);
            }
        }
    }

    /**
     * Modul yang belum dipetakan TIDAK BOLEH hilang dari form.
     *
     * Kalau hilang, haknya tidak bisa diberikan sama sekali dan tidak ada
     * yang menyadarinya. Ia harus tetap tampil di tab cadangan supaya
     * kesalahannya terlihat, bukan menghilang diam-diam.
     *
     * @test
     */
    public function an_unmapped_module_still_appears_in_a_fallback_tab()
    {
        $this->seedPermissions([
            'Modul Baru Yang Belum Dipetakan' => ['view_modul_baru'],
        ]);

        $grouped = Permission::groupedByModuleGroup();

        $this->assertArrayHasKey(
            Permission::UNGROUPED,
            $grouped,
            'Modul yang belum dipetakan hilang dari form -- haknya tidak akan bisa diberikan.',
        );

        $this->assertArrayHasKey(
            'Modul Baru Yang Belum Dipetakan',
            $grouped[Permission::UNGROUPED],
        );
    }

    /**
     * Seluruh modul yang benar-benar di-seed wajib sudah dipetakan.
     *
     * Ini yang menjaga peta tetap mutakhir: modul baru yang lupa dipetakan
     * akan langsung gagal di sini, bukan diam-diam terdampar di tab cadangan.
     *
     * @test
     */
    public function every_seeded_module_is_mapped_to_a_sidebar_group()
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $unmapped = Permission::query()
            ->get()
            ->pluck('module_name')
            ->unique()
            ->filter(fn (string $module) => Permission::groupFor($module) === Permission::UNGROUPED)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            [],
            $unmapped,
            "Modul berikut belum dipetakan ke grup sidebar mana pun di Permission::moduleGroups():\n"
            . implode("\n", $unmapped),
        );
    }

    /**
     * Urutan tab harus sama dengan urutan grup di sidebar, bukan abjad.
     *
     * @test
     */
    public function the_tab_order_follows_the_sidebar_order()
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $expected = array_keys(Permission::moduleGroups());
        $actual = array_keys(Permission::groupedByModuleGroup());

        // Grup tanpa permission memang tidak ditampilkan, jadi yang
        // dibandingkan hanya urutan relatifnya.
        $this->assertSame(
            array_values(array_intersect($expected, $actual)),
            array_values(array_diff($actual, [Permission::UNGROUPED])),
        );
    }

    /** @test */
    public function no_module_is_listed_in_more_than_one_group()
    {
        $seen = [];
        $duplicates = [];

        foreach (Permission::moduleGroups() as $group => $modules) {
            foreach ($modules as $module) {
                if (isset($seen[$module])) {
                    $duplicates[] = $module . ' (' . $seen[$module] . ' & ' . $group . ')';
                }
                $seen[$module] = $group;
            }
        }

        $this->assertSame([], $duplicates, 'Modul terdaftar di lebih dari satu grup: ' . implode(', ', $duplicates));
    }

    /** @test */
    public function the_create_user_form_renders_with_grouped_permissions()
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        Livewire::actingAs($this->programmer)
            ->test(CreateUser::class)
            ->assertOk();
    }

    /**
     * Hak yang sudah dimiliki tetap tercentang saat halaman Edit dibuka.
     *
     * Pengelompokan ke tab tidak boleh mengubah cara hak dibaca -- kalau
     * centangnya hilang, admin akan mengira haknya belum diberikan lalu
     * memberikannya ulang.
     *
     * @test
     */
    public function existing_permissions_stay_checked_on_the_edit_form()
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $permission = Permission::where('module_name', 'Sales Orders')->firstOrFail();

        $employee = User::create([
            'name' => 'Pegawai',
            'username' => 'perm_form_employee',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);
        $employee->permissions()->attach($permission->id);

        Livewire::actingAs($this->programmer)
            ->test(EditUser::class, ['record' => $employee->getRouteKey()])
            ->assertOk()
            ->assertFormSet([
                'permissions_Sales Orders' => [$permission->id],
            ]);
    }

    /**
     * Penyimpanan membaca kunci `permissions_*` dari state form.
     *
     * Tabs dan Section adalah komponen TATA LETAK -- mereka tidak menyarangkan
     * state, jadi kuncinya tetap datar di tingkat atas. Test ini membuktikan
     * itu, karena kalau salah, hak akses akan gagal tersimpan tanpa error
     * apa pun: form tampak berhasil disimpan, tapi centangnya hilang.
     *
     * @test
     */
    public function permissions_still_save_correctly_from_the_tabbed_form()
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $sales = Permission::where('module_name', 'Sales Orders')->firstOrFail();
        $master = Permission::where('module_name', 'Suppliers')->firstOrFail();

        $employee = User::create([
            'name' => 'Pegawai Simpan',
            'username' => 'perm_form_save',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        // Dua modul dari TAB BERBEDA, supaya terbukti state-nya tidak
        // tersarang per tab.
        Livewire::actingAs($this->programmer)
            ->test(EditUser::class, ['record' => $employee->getRouteKey()])
            ->fillForm([
                'permissions_Sales Orders' => [$sales->id],
                'permissions_Suppliers' => [$master->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $employee->fresh()->permissions->pluck('id')->sort()->values()->all();

        $this->assertSame(
            collect([$sales->id, $master->id])->sort()->values()->all(),
            $saved,
            'Hak akses tidak tersimpan dari form bertab.',
        );
    }

    /**
     * Tab form wajib membungkus di layar sempit, bukan menggulir ke samping.
     *
     * Bawaan Filament menaruh seluruh tab dalam satu baris ber-overflow-x-auto.
     * Dengan 12 tab hak akses, di HP hanya 2-3 yang terlihat -- dan tab yang
     * sedang AKTIF bisa berada di luar layar, sehingga pengguna tidak tahu
     * sedang membuka bagian yang mana. Gejalanya tidak terlihat sama sekali
     * di desktop, jadi gampang hilang tanpa ada yang menyadarinya.
     *
     * @test
     */
    public function form_tabs_wrap_instead_of_scrolling_on_small_screens()
    {
        $source = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

        $this->assertStringContainsString(
            '.fi-fo-tabs .fi-tabs',
            $source,
            'CSS pembungkus tab form hilang -- di HP tab akan menggulir ke samping lagi.',
        );

        $this->assertStringContainsString('flex-wrap: wrap', $source);

        // Dibatasi pada tab FORM saja. Melebarkannya ke seluruh .fi-tabs akan
        // mengubah tata letak halaman lain yang belum diperiksa.
        $this->assertStringNotContainsString(
            '.fi-tabs { flex-wrap',
            $source,
            'Pembungkusan tab diterapkan terlalu luas, bukan hanya pada tab form.',
        );
    }

    /**
     * Tidak boleh ada permission yang di-seed lebih dari sekali.
     *
     * `permissions.name` unique dan seeder memakai updateOrCreate, sehingga
     * entri kedua DIAM-DIAM MENIMPA yang pertama tanpa error apa pun. Itu
     * yang terjadi pada `view_activity_logs`: didaftarkan dua kali dengan
     * module_name berbeda ('Activity Logs' dan 'System'), dan yang belakangan
     * menang -- membuat modul 'Activity Logs' tidak pernah benar-benar ada.
     *
     * Gejalanya cuma satu modul yang hilang diam-diam dari form hak akses.
     *
     * @test
     */
    public function no_permission_is_seeded_more_than_once()
    {
        $seeder = file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));

        preg_match_all("/'name'\s*=>\s*'([a-z_]+)'\s*,\s*'module_name'/", $seeder, $matches);

        $counts = array_count_values($matches[1]);
        $duplicates = [];

        foreach ($counts as $name => $count) {
            if ($count > 1) {
                $duplicates[] = $name . ' (' . $count . 'x)';
            }
        }

        sort($duplicates);

        $this->assertSame(
            [],
            $duplicates,
            "Permission berikut didaftarkan lebih dari sekali di DatabaseSeeder. "
            . "Yang belakangan akan menimpa yang awal tanpa error:\n" . implode("\n", $duplicates),
        );
    }

    /**
     * Menu log wajib menyebut gunanya, bukan sekadar "Log Viewer".
     *
     * Nama lamanya tidak memberi tahu apa-apa, sehingga alat ini nyaris tidak
     * pernah dibuka -- padahal ia satu-satunya tempat kegagalan yang sengaja
     * ditelan try/catch bisa terlihat.
     *
     * @test
     */
    public function the_log_menu_says_what_it_is_for()
    {
        $source = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

        $this->assertStringContainsString("__('System Error Log')", $source);

        foreach (['id', 'en'] as $locale) {
            $strings = json_decode(file_get_contents(lang_path($locale . '.json')), true);
            $this->assertArrayHasKey('System Error Log', $strings, "Belum terdaftar di {$locale}.json.");
        }
    }

    /**
     * Tautan donasi pihak ketiga di dalam Log Viewer dimatikan.
     *
     * Paket opcodesio/log-viewer menampilkan tombol "Buy me a coffee" milik
     * pembuatnya. Di aplikasi internal Wijaya Meat itu membingungkan --
     * pengguna mengira tombol itu bagian dari aplikasi.
     *
     * Dibaca paket lewat config('log-viewer.show_support_link'), jadi cukup
     * satu opsi config. Ada test-nya karena `php artisan config:publish`
     * atau pembaruan paket bisa menimpanya kembali ke bawaan (true) tanpa
     * ada yang menyadarinya.
     *
     * @test
     */
    public function the_log_viewer_hides_the_third_party_donation_link()
    {
        $this->assertFalse(
            config('log-viewer.show_support_link'),
            'Tombol "Buy me a coffee" milik pembuat paket muncul lagi di Log Viewer.',
        );
    }

    /**
     * Tautan ke repositori pembuat paket disembunyikan dari Log Viewer.
     *
     * Berbeda dari tombol "Buy me a coffee" yang punya flag config, ikon
     * GitHub HARDCODE di komponen Vue yang sudah terkompilasi ke app.js.
     * Satu-satunya cara tanpa menyunting berkas vendor/ (yang hilang setiap
     * composer install) adalah menimpa view-nya dan menyembunyikannya lewat
     * CSS.
     *
     * Test ini menjaga dua hal: view timpaannya masih ada -- kalau terhapus,
     * Laravel diam-diam kembali memakai view bawaan paket dan ikonnya muncul
     * lagi -- dan aturan CSS-nya masih di dalamnya.
     *
     * @test
     */
    public function the_log_viewer_hides_the_package_repository_link()
    {
        $override = resource_path('views/vendor/log-viewer/index.blade.php');

        $this->assertFileExists(
            $override,
            'View timpaan Log Viewer hilang -- Laravel akan kembali memakai view bawaan paket.',
        );

        $this->assertSame(
            $override,
            view()->getFinder()->find('log-viewer::index'),
            'Laravel tidak memakai view timpaan, melainkan bawaan paket.',
        );

        $this->assertStringContainsString(
            'github.com/opcodesio',
            file_get_contents($override),
            'Aturan CSS penyembunyi tautan repositori hilang dari view timpaan.',
        );
    }

    /**
     * Halamannya harus benar-benar ter-render dengan view timpaan itu.
     *
     * View timpaan lama ternyata salinan versi paket yang LEBIH TUA -- ia
     * memuat aset dengan path hardcode, bukan logika $assetsPublished yang
     * dipakai paket sekarang. Menyalin ulang dari paket menyelaraskannya,
     * tapi itu mengubah cara aset dimuat, jadi harus dibuktikan tidak rusak.
     *
     * @test
     */
    public function the_log_viewer_page_still_renders()
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->actingAs($this->programmer)
            ->get(route('log-viewer.index'))
            ->assertOk()
            ->assertSee('log-viewer', false);
    }
}
