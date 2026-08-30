<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Melihat dokumen dan MENGGERAKKAN UANG adalah dua tingkat wewenang berbeda.
 *
 * Sebelumnya keempat aksi yang memindahkan uang -- bayar utang supplier, DP di
 * PO Beef, DP di PO Material, dan terima pembayaran piutang -- tidak satu pun
 * memeriksa hak akses. Siapa pun yang diberi hak MELIHAT sebuah PO atau utang
 * otomatis bisa mengeluarkan uang perusahaan.
 *
 * Yang menyesatkan: `ViewPurchaseProduct` memang punya satu pemeriksaan hak
 * akses, tapi itu untuk tombol Print -- bukan untuk pembayarannya. Sekilas
 * halamannya tampak sudah dijaga.
 */
class MoneyActionPermissionTest extends TestCase
{
    use RefreshDatabase;

    /** Halaman yang memindahkan uang, beserta permission yang wajib menjaganya. */
    protected function moneyActions(): array
    {
        return [
            'Bayar utang supplier' => [
                'Filament/Admin/Resources/PayableResource/Pages/ViewPayable.php',
                'pay_payables',
            ],
            'DP di PO Beef' => [
                'Filament/Admin/Resources/PurchaseProductResource/Pages/ViewPurchaseProduct.php',
                'pay_purchase_products',
            ],
            'DP di PO Material' => [
                'Filament/Admin/Resources/PurchaseMaterialResource/Pages/ViewPurchaseMaterial.php',
                'pay_purchase_materials',
            ],
            'Terima pembayaran piutang' => [
                'Filament/Admin/Resources/ReceivableResource/Pages/ReceivePayment.php',
                'receive_receivables',
            ],
        ];
    }

    /** @test */
    public function every_money_moving_page_checks_its_own_permission()
    {
        $missing = [];

        foreach ($this->moneyActions() as $label => [$file, $permission]) {
            $source = file_get_contents(app_path($file));

            if (! str_contains($source, "'" . $permission . "'")) {
                $missing[] = $label . ' -> ' . $permission;
            }
        }

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Halaman berikut memindahkan uang tapi tidak memeriksa hak aksesnya:\n" . implode("\n", $missing),
        );
    }

    /** @test */
    public function the_payment_permissions_are_seeded()
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        foreach ($this->moneyActions() as $label => [$file, $permission]) {
            $this->assertNotNull(
                Permission::where('name', $permission)->first(),
                "Permission '{$permission}' belum di-seed, jadi tidak bisa diberikan ke siapa pun.",
            );
        }
    }

    /**
     * Halaman terima pembayaran dijaga di TINGKAT HALAMAN, bukan cuma tombolnya.
     *
     * Menyembunyikan tombol tidak cukup: rutenya bisa dicapai dengan mengetik
     * URL langsung. Ini pelajaran yang sama dengan halaman keputusan Request.
     *
     * @test
     */
    public function the_receive_payment_page_denies_access_without_the_permission()
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $tanpaHak = User::create([
            'name' => 'Tanpa Hak', 'username' => 'money_no_perm', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);
        $tanpaHak->permissions()->attach(Permission::where('name', 'view_receivables')->firstOrFail()->id);

        $this->actingAs($tanpaHak);
        $this->assertFalse(
            \App\Filament\Admin\Resources\ReceivableResource\Pages\ReceivePayment::canAccess(),
            'Hak melihat piutang saja sudah membuka halaman penerimaan pembayaran.',
        );

        $denganHak = User::create([
            'name' => 'Kasir', 'username' => 'money_with_perm', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);
        $denganHak->permissions()->attach(Permission::where('name', 'receive_receivables')->firstOrFail()->id);

        $this->actingAs($denganHak);
        $this->assertTrue(\App\Filament\Admin\Resources\ReceivableResource\Pages\ReceivePayment::canAccess());
    }

    /**
     * Penjagaan POLA: aksi baru yang membuat pembayaran tidak boleh lolos.
     *
     * Menjaga empat halaman yang sudah diketahui saja tidak cukup -- yang
     * berikutnya akan lolos dengan cara yang persis sama. Test ini memindai
     * SELURUH halaman Filament: yang membuat SupplierPayment atau Payment
     * wajib menyebut sebuah permission di berkas yang sama.
     *
     * @test
     */
    public function no_page_creates_a_payment_without_checking_a_permission()
    {
        $offenders = [];

        foreach ($this->filamentPageFiles() as $file) {
            $source = file_get_contents($file);

            $createsPayment = str_contains($source, 'SupplierPayment::create')
                || str_contains($source, 'Payment::create(');

            if (! $createsPayment) {
                continue;
            }

            if (! str_contains($source, 'hasPermission(')) {
                $offenders[] = basename($file);
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Halaman berikut membuat pembayaran tanpa memeriksa hak akses sama sekali:\n"
            . implode("\n", $offenders),
        );
    }

    /** @return array<int, string> */
    protected function filamentPageFiles(): array
    {
        $files = [];

        $directory = new \RecursiveDirectoryIterator(app_path('Filament'));
        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
