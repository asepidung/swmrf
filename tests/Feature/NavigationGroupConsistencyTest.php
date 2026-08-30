<?php

namespace Tests\Feature;

use App\Models\Permission;
use Tests\TestCase;

/**
 * Tiga daftar grup navigasi harus selalu sepakat.
 *
 * Nama grup hidup di TIGA tempat yang saling bebas:
 *
 *   1. `navigationGroups()` di AdminPanelProvider -- urutan resmi di sidebar
 *   2. `getNavigationGroup()` tiap Resource       -- penempatan sebenarnya
 *   3. `Permission::moduleGroups()`               -- tab di form hak akses
 *
 * Tidak ada apa pun di framework yang memaksa ketiganya cocok. Filament
 * menerima begitu saja Resource yang menunjuk grup tak terdaftar: menunya
 * tetap tampil, hanya kehilangan posisi urutannya. Tidak ada error, tidak ada
 * peringatan.
 *
 * Itulah yang terjadi pada ACCOUNTING: empat Resource memakainya sementara
 * panel hanya mendeklarasikan FINANCE, dan form hak akses menaruh keempatnya
 * di FINANCE. Sidebar menampilkan "Akuntansi", form menampilkan "Keuangan",
 * dan tidak ada yang menyadarinya sampai Project Owner kebetulan melihat.
 *
 * Test ini membuat penyimpangan itu GAGAL SEKETIKA, di commit yang
 * mengubahnya -- bukan berbulan-bulan kemudian saat ada yang sadar. Dengan
 * begitu tidak perlu ada ritual "nanti dicek ulang semua di akhir".
 */
class NavigationGroupConsistencyTest extends TestCase
{
    /**
     * Grup yang resmi dideklarasikan panel, apa adanya dari kodenya.
     *
     * Dibaca dari berkas, bukan dari Filament, supaya test ini tidak perlu
     * membangun seluruh panel hanya untuk membaca daftar nama.
     *
     * @return array<int, string>
     */
    protected function declaredGroups(): array
    {
        $source = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

        preg_match_all("/NavigationGroup::make\('([^']+)'\)/", $source, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Grup yang benar-benar dipakai Resource.
     *
     * @return array<string, array<int, string>> grup => daftar Resource
     */
    protected function groupsUsedByResources(): array
    {
        $used = [];

        foreach (glob(app_path('Filament/Admin/Resources/*Resource.php')) as $file) {
            $source = file_get_contents($file);

            // Menangkap kedua bentuk yang dipakai di proyek ini:
            //   protected static ?string $navigationGroup = 'X';
            //   public static function getNavigationGroup(): ?string { return __('X'); }
            preg_match_all("/navigationGroup\s*=\s*'([^']+)'|getNavigationGroup[^}]*?__\('([^']+)'\)/s", $source, $matches);

            foreach (array_merge($matches[1], $matches[2]) as $group) {
                if ($group === '') {
                    continue;
                }

                $used[$group][] = basename($file, '.php');
            }
        }

        return $used;
    }

    /** @test */
    public function it_finds_groups_to_check()
    {
        $this->assertNotEmpty($this->declaredGroups(), 'Tidak menemukan satu pun NavigationGroup -- pemindainya rusak.');
        $this->assertNotEmpty($this->groupsUsedByResources(), 'Tidak menemukan satu pun grup di Resource -- pemindainya rusak.');
    }

    /**
     * Setiap grup yang dipakai Resource wajib terdaftar di panel.
     *
     * Grup tak terdaftar tetap tampil di sidebar, tapi tanpa posisi urutan --
     * ia terlempar ke bawah sendiri dan tidak ikut aturan susunan mana pun.
     *
     * @test
     */
    public function every_group_used_by_a_resource_is_declared_in_the_panel()
    {
        $declared = $this->declaredGroups();
        $offenders = [];

        foreach ($this->groupsUsedByResources() as $group => $resources) {
            if (! in_array($group, $declared, true)) {
                $offenders[] = $group . ' (dipakai: ' . implode(', ', $resources) . ')';
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Grup berikut dipakai Resource tapi TIDAK didaftarkan di navigationGroups() "
            . "pada AdminPanelProvider, sehingga kehilangan posisi urutannya di sidebar:\n"
            . implode("\n", $offenders),
        );
    }

    /**
     * Setiap grup di form hak akses wajib grup yang benar-benar ada.
     *
     * Kalau sebuah grup diganti namanya di sidebar tapi peta permission tidak
     * ikut diubah, tab hak aksesnya akan menampilkan nama grup yang sudah
     * tidak ada -- dan modul di dalamnya seolah berada di tempat yang salah.
     *
     * @test
     */
    public function every_group_in_the_permission_map_is_a_real_navigation_group()
    {
        $declared = $this->declaredGroups();

        $unknown = array_values(array_filter(
            array_keys(Permission::moduleGroups()),
            fn (string $group) => ! in_array($group, $declared, true),
        ));

        sort($unknown);

        $this->assertSame(
            [],
            $unknown,
            "Grup berikut ada di Permission::moduleGroups() tapi bukan grup navigasi mana pun. "
            . "Kemungkinan grupnya diganti nama di sidebar tanpa ikut mengubah peta ini:\n"
            . implode("\n", $unknown),
        );
    }

    /**
     * Modul harus dipetakan ke grup yang sama dengan Resource-nya.
     *
     * Ini yang paling mudah menyimpang: Resource dipindah ke grup lain, tapi
     * peta permission tertinggal. Sidebar dan form hak akses lalu menampilkan
     * modul yang sama di dua grup berbeda.
     *
     * Pemetaan modul -> Resource sengaja ditulis eksplisit, karena namanya
     * memang tidak selalu sejalan (`Logistic Items` -> `MaterialResource`,
     * `GR Beef` -> `GoodsReceiptProductResource`). Yang tidak terdaftar di
     * sini dilewati -- test ini menjaga yang sudah diketahui, bukan menebak.
     *
     * @test
     */
    public function finance_modules_sit_in_the_same_group_as_their_resource()
    {
        $moduleToResource = [
            'Invoices' => 'InvoiceResource',
            'Payables' => 'PayableResource',
            'Receivables' => 'ReceivableResource',
            'Bank Accounts' => 'BankAccountResource',
            'Financial Loss' => 'FinancialLossResource',
            'Sales Orders' => 'SalesOrderResource',
            'Price Lists' => 'PriceListResource',
        ];

        $resourceGroup = [];
        foreach ($this->groupsUsedByResources() as $group => $resources) {
            foreach ($resources as $resource) {
                $resourceGroup[$resource] = $group;
            }
        }

        $mapped = [];
        foreach (Permission::moduleGroups() as $group => $modules) {
            foreach ($modules as $module) {
                $mapped[$module] = $group;
            }
        }

        $mismatches = [];

        foreach ($moduleToResource as $module => $resource) {
            if (! isset($resourceGroup[$resource], $mapped[$module])) {
                continue;
            }

            if ($resourceGroup[$resource] !== $mapped[$module]) {
                $mismatches[] = sprintf(
                    '%s: Resource di grup %s, tapi peta permission menaruhnya di %s',
                    $module,
                    $resourceGroup[$resource],
                    $mapped[$module],
                );
            }
        }

        sort($mismatches);

        $this->assertSame([], $mismatches, implode("\n", $mismatches));
    }
}
