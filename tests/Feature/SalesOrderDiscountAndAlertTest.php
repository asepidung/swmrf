<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\TaskAlert;
use App\Support\TaskNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Diskon Sales Order: persen BULAT, dan tidak lagi dirusak saat disimpan.
 *
 * Penyimpanan SO dulu membuang titik dari nilai diskon, sama seperti yang
 * dilakukannya pada berat dan harga. Untuk berat dan harga itu benar --
 * JavaScript di form memang memasang titik sebagai pemisah ribuan. Untuk
 * diskon itu keliru: form sengaja TIDAK memformat kolom itu, sehingga titik
 * di sana hanya mungkin berarti koma desimal.
 *
 * Akibatnya bukan pembulatan melainkan perubahan arti:
 *
 *     2,5%   tersimpan menjadi 25%
 *     12,75% tersimpan menjadi 1275%
 *
 * Validasi tidak menangkapnya karena perusakannya terjadi SESUDAH validasi
 * berjalan -- validator melihat 12,75 dan meloloskannya.
 *
 * Keputusan Project Owner, 31 Agustus 2026: persen bulat saja. Kolom
 * `sales_order_items.discount` memang bilangan bulat, dan
 * `customers.default_discount` disamakan dengannya. Kalau suatu saat diskon
 * berkoma dibutuhkan, KEDUA kolom harus dilebarkan bersamaan.
 */
class SalesOrderDiscountAndAlertTest extends TestCase
{
    use RefreshDatabase;

    /** Inilah bentuk kerusakan yang dulu terjadi. */
    public function test_stripping_the_dot_changes_the_meaning_of_a_discount(): void
    {
        // Cara lama, dipertahankan di sini supaya alasannya tetap terlihat.
        $this->assertSame(25, (int) str_replace('.', '', '2.5'));
        $this->assertSame(1275, (int) str_replace('.', '', '12.75'));

        // Cara sekarang membulatkan ke bawah, tidak mengubah artinya.
        $this->assertSame(2, (int) '2.5');
        $this->assertSame(12, (int) '12.75');
    }

    /** Penyimpanan SO tidak lagi membuang titik dari diskon. */
    public function test_neither_save_path_strips_dots_from_the_discount(): void
    {
        foreach (['CreateSalesOrder.php', 'EditSalesOrder.php'] as $page) {
            $source = file_get_contents(app_path(
                'Filament/Admin/Resources/SalesOrderResource/Pages/'.$page
            ));

            $this->assertStringNotContainsString(
                "'discount' => (int) str_replace('.', '', \$item['discount'] ?? 0)",
                $source,
                $page.': masih membuang titik dari diskon.',
            );

            $this->assertStringContainsString(
                "'discount' => (int) (\$item['discount'] ?? 0)",
                $source,
                $page.': diskon tidak dibaca apa adanya.',
            );

            // Berat dan harga MEMANG berpemisah ribuan, jadi titiknya tetap
            // dibuang di sana. Kalau ini ikut terhapus, angkanya justru
            // menyusut seribu kali.
            $this->assertStringContainsString(
                "'price' => (int) str_replace('.', '', \$item['price'] ?? 0)",
                $source,
                $page.': harga kehilangan pembersihan pemisah ribuannya.',
            );
        }
    }

    /** Aturannya persen bulat di kedua tempat, bukan sekadar angka. */
    public function test_both_discount_fields_require_a_whole_percent(): void
    {
        foreach ([
            'Filament/Admin/Resources/SalesOrderResource.php',
            'Filament/Clusters/CustomersCluster/Resources/CustomerResource.php',
        ] as $file) {
            $source = file_get_contents(app_path($file));
            $field = substr($source, strpos($source, 'discount'), strlen($source));

            $this->assertStringContainsString("'integer'", $field, basename($file));
            $this->assertStringContainsString("'max:100'", $field, basename($file));
        }

        $this->assertFalse(
            Validator::make(['discount' => '2.5'], ['discount' => ['integer', 'min:0', 'max:100']])->passes(),
            'Diskon berkoma seharusnya ditolak sejak validasi.',
        );
    }

    /** Diskon bawaan pelanggan tersimpan sebagai bilangan bulat. */
    public function test_the_customer_default_discount_is_a_whole_percent(): void
    {
        $customer = Customer::create([
            'name' => 'LION DCA SUPERINDO',
            'customer_group_id' => CustomerGroup::create(['name' => 'LION'])->id,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL'])->id,
            'address' => 'Jalan Uji',
            'top' => 30,
            'default_discount' => 2,
            'invoice_exchange' => false,
            'is_active' => true,
        ]);

        $this->assertSame(2, $customer->fresh()->default_discount);
    }

    /**
     * Pemegang hak create tally diberi tahu saat Sales Order dibuat.
     *
     * SO baru lahir dengan status 'waiting', dan itu persis keadaan yang
     * ditunggu halaman Draft Tally -- jadi begitu SO tersimpan, pekerjaannya
     * memang sudah menganggur di meja orang lain.
     */
    public function test_the_tally_team_is_told_when_a_sales_order_is_created(): void
    {
        Notification::fake();

        $permission = Permission::create([
            'name' => 'create_tallies',
            'module_name' => 'Tallies',
            'description' => 'Create tallies',
        ]);

        // Sengaja BUKAN programmer: hasPermission() selalu mengembalikan true
        // untuk programmer, sehingga test dengan peran itu lolos tanpa
        // benar-benar menguji hak aksesnya.
        $tallyStaff = User::create([
            'name' => 'Petugas Tally', 'username' => 'tally_staff',
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);
        $tallyStaff->permissions()->attach($permission->id);
        $tallyStaff->pushSubscriptions()->create([
            'endpoint' => 'https://example.test/tally', 'public_key' => 'k', 'auth_token' => 't',
        ]);

        $outsider = User::create([
            'name' => 'Orang Lain', 'username' => 'orang_lain',
            'password' => 'secret-password', 'gender' => 'P',
            'role' => 'employee', 'is_active' => true,
        ]);
        $outsider->pushSubscriptions()->create([
            'endpoint' => 'https://example.test/lain', 'public_key' => 'k', 'auth_token' => 't',
        ]);

        $sent = TaskNotifier::notifyPermissionHolders(
            'create_tallies',
            __('New Sales Order'),
            __('Ready to be tallied.'),
            '/admin/tallies/draft',
            'sales-order-1',
        );

        $this->assertSame(1, $sent);
        Notification::assertSentTo($tallyStaff, TaskAlert::class);
        Notification::assertNotSentTo($outsider, TaskAlert::class);
    }

    /** Notifikasinya dipasang saat DIBUAT, bukan saat disunting. */
    public function test_editing_a_sales_order_does_not_notify_again(): void
    {
        $create = file_get_contents(app_path(
            'Filament/Admin/Resources/SalesOrderResource/Pages/CreateSalesOrder.php'
        ));
        $edit = file_get_contents(app_path(
            'Filament/Admin/Resources/SalesOrderResource/Pages/EditSalesOrder.php'
        ));

        $this->assertStringContainsString("'create_tallies'", $create);
        $this->assertStringContainsString('TallyResource::getUrl', $create);

        // Menyunting SO yang sama beberapa kali tidak melahirkan pekerjaan
        // baru; mengirim notifikasi tiap kali hanya membuat orang berhenti
        // membacanya.
        $this->assertStringNotContainsString('notifyPermissionHolders', $edit);
    }

    /** Pemicunya tidak ikut diberi tahu pekerjaannya sendiri. */
    public function test_the_creator_is_left_out(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Admin/Resources/SalesOrderResource/Pages/CreateSalesOrder.php'
        ));

        $this->assertStringContainsString('auth()->id(),', $source);
    }
}
