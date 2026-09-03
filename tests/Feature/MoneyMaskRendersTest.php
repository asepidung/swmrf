<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mask uang harus benar-benar SAMPAI ke halamannya, utuh.
 *
 * Dilaporkan Project Owner, 4 September 2026: mengetik 123555 di halaman
 * penerimaan pembayaran tidak memunculkan pemisah ribuan sama sekali.
 *
 * Penyebabnya kutip tunggal yang tidak di-escape di dalam string PHP:
 *
 *     ->mask(RawJs::make('$money($input, ',', '.', 0)'))     <- rusak
 *     ->mask(RawJs::make('$money($input, \',\', \'.\', 0)')) <- benar
 *
 * Yang rusak TETAP LOLOS `php -l`, karena PHP membacanya sebagai beberapa
 * argumen yang sah. Yang sampai ke halaman hanya potongan pertamanya,
 * `$money($input,` -- Alpine menerima ekspresi yang tidak lengkap, tidak
 * melakukan apa-apa, dan tidak mengeluh sama sekali.
 *
 * Jadi kerusakannya sempurna diam: kode tersimpan, halaman terbuka, form
 * berfungsi, angka tetap tersimpan benar -- hanya pemisah ribuannya tidak
 * pernah muncul. Satu-satunya cara menemukannya adalah mengetik di layar.
 *
 * Karena itu di sini yang diperiksa adalah HTML YANG SUNGGUHAN, bukan kodenya.
 */
class MoneyMaskRendersTest extends TestCase
{
    use RefreshDatabase;

    /** Bentuk ekspresi yang utuh, apa adanya seperti yang dibaca Alpine. */
    private const EXPECTED = '$money($input, \',\', \'.\', 0)';

    /**
     * Setiap mask uang di kode berbentuk lengkap.
     *
     * Penjaga murah yang menangkap seluruh berkas sekaligus. Pasangannya di
     * bawah membuktikan bahwa bentuk itu memang sampai ke halaman.
     */
    public function test_every_money_mask_is_written_whole(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if (! str_contains($source, '$money($input')) {
                continue;
            }

            // Kutipnya harus di-escape. Yang tidak akan terpotong di koma
            // pertama saat dirender, tanpa satu pun error.
            preg_match_all("/\\\$money\\(\\\$input[^\n]*/", $source, $matches);

            foreach ($matches[0] as $found) {
                if (! str_contains($found, "\\'")) {
                    $offenders[] = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname())
                        .' -> '.trim($found);
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Mask uang berikut memakai kutip tunggal tanpa escape. PHP menerimanya, "
            ."tetapi yang sampai ke halaman hanya potongan sebelum koma pertama -- "
            .'dan pemisah ribuannya diam-diam tidak pernah muncul.',
        );
    }

    /**
     * Dan ekspresinya benar-benar utuh di HTML yang dikirim ke peramban.
     *
     * Inilah yang tidak bisa dibuktikan penjaga kode di atas: bahwa Blade,
     * Filament, dan pembungkus atributnya tidak memotongnya di tengah jalan.
     */
    public function test_the_mask_reaches_the_page_intact(): void
    {
        $user = User::create([
            'name' => 'Kasir', 'username' => 'kasir_mask', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'employee', 'is_active' => true,
        ]);

        foreach (['view_receivables', 'receive_receivables'] as $izin) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => $izin],
                    ['module_name' => 'Receivables', 'description' => $izin],
                )->id
            );
        }

        $this->actingAs($user->fresh());

        $group = CustomerGroup::create(['name' => 'BIDADARI']);

        $customer = Customer::create([
            'name' => 'BIDADARI PUSAT',
            'address' => 'Bogor',
            'top' => 30,
            'customer_group_id' => $group->id,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
        ]);

        $so = SalesOrder::create([
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
            'created_by' => $user->id,
            'status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'sales_order_id' => $so->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 30,
            'status' => 'Belum Dibayar',
            'subtotal' => 11179000,
            'charge' => 0,
            'down_payment' => 0,
            'created_by' => $user->id,
        ]);

        Receivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'customer_group_id' => $group->id,
        ]);

        BankAccount::create([
            'initial' => 'BCA', 'bank_name' => 'BANK CENTRAL ASIA',
            'account_number' => '1234567890', 'account_holder' => 'WIJAYA MEAT',
            'is_active' => true,
        ]);

        $html = $this->get('/admin/receivables/'.$group->id.'/payment')
            ->assertSuccessful()
            ->getContent();

        // Atributnya boleh ter-escape sebagai entitas HTML; yang tidak boleh
        // adalah terpotong.
        $polos = html_entity_decode($html, ENT_QUOTES);

        $this->assertStringContainsString(
            self::EXPECTED,
            $polos,
            'Ekspresi mask tidak sampai utuh ke halaman, jadi pemisah ribuannya '
            .'tidak akan pernah muncul -- tanpa satu pun error.',
        );

        $this->assertStringNotContainsString(
            '$money($input,"',
            $html,
            'Ekspresi mask terpotong di koma pertama.',
        );
    }
}
