<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\CustomerSegment;
use App\Support\PriceListInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Price list ditawarkan lebih awal, bukan setelah Sales Order.
 *
 * Duduk perkaranya. Pelanggan baru yang dibuat tanpa memilih grup akan
 * OTOMATIS dibuatkan grup sendiri bernama sama dengan pelanggannya. Grup
 * baru itu belum punya satu baris harga pun, sehingga setiap Sales Order
 * untuknya terisi Rp 0 di semua barisnya.
 *
 * Rp 0 itu sendiri sengaja dibiarkan -- keputusan Project Owner, 31 Agustus
 * 2026: saat membuat SO user bebas mengubah harga, jadi nol hanyalah titik
 * awal. Yang diperbaiki adalah URUTANNYA, supaya price list sudah siap
 * sebelum SO pertama dibuat.
 */
class PriceListInvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Grup otomatis untuk pelanggan tanpa grup memang ada, dan Create
     * dengan Edit memakai potongan kode yang SAMA.
     *
     * Dulu keduanya memuat salinan masing-masing. Kalau sampai berbeda,
     * pelanggan yang disunting bisa berakhir di grup yang berlainan dengan
     * saat ia dibuat, dan ikut berpindah price list tanpa ada yang meminta.
     */
    public function test_both_customer_pages_share_one_grouping_rule(): void
    {
        foreach (['CreateCustomer.php', 'EditCustomer.php'] as $page) {
            $source = file_get_contents(app_path(
                'Filament/Clusters/CustomersCluster/Resources/CustomerResource/Pages/'.$page
            ));

            $this->assertStringContainsString('KeepsCustomerInAGroup', $source, $page);
            $this->assertStringContainsString('ensureCustomerGroup($data)', $source, $page);
            $this->assertStringNotContainsString(
                'CustomerGroup::firstOrCreate',
                $source,
                $page.': masih memuat salinan aturan pengelompokan sendiri.',
            );
        }
    }

    /** Grup tanpa satu pun harga dianggap belum punya price list. */
    public function test_a_group_without_any_price_row_counts_as_having_none(): void
    {
        $group = CustomerGroup::create(['name' => 'GRUP BARU']);

        $this->assertFalse(PriceListInvitation::hasPrices($group));

        // Baris price list KOSONG tetap dianggap belum punya harga. Baris
        // seperti ini bisa tercipta sebagai efek samping form, dan grup yang
        // memilikinya sama tidak bergunanya dengan grup tanpa price list.
        $priceList = PriceList::create(['customer_group_id' => $group->id]);

        $this->assertFalse(PriceListInvitation::hasPrices($group->fresh()));

        $product = Product::create([
            'name' => 'SIRLOIN',
            'code' => 'B001',
            'category_id' => ProductCategory::create([
                'name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true,
            ])->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        PriceListItem::create([
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'price' => 95000,
        ]);

        $this->assertTrue(PriceListInvitation::hasPrices($group->fresh()));
    }

    /** Ketiga halaman yang bisa melahirkan grup baru sama-sama menawarkan. */
    public function test_every_page_that_can_create_a_group_offers_the_price_list(): void
    {
        foreach ([
            'Filament/Clusters/CustomersCluster/Resources/CustomerResource/Pages/CreateCustomer.php',
            'Filament/Clusters/CustomersCluster/Resources/CustomerResource/Pages/EditCustomer.php',
            'Filament/Clusters/CustomersCluster/Resources/CustomerGroupResource/Pages/CreateCustomerGroup.php',
        ] as $page) {
            $this->assertStringContainsString(
                'PriceListInvitation::offerFor',
                file_get_contents(app_path($page)),
                basename($page).' tidak menawarkan pembuatan price list.',
            );
        }
    }

    /**
     * Tawarannya tidak boleh menghalangi penyimpanan.
     *
     * Yang membuat pelanggan belum tentu orang yang berhak menetapkan harga
     * -- haknya pun terpisah. Menahan penyimpanan hanya akan menghentikan
     * pekerjaan yang sebenarnya sah.
     */
    public function test_the_offer_never_blocks_saving(): void
    {
        $source = file_get_contents(app_path('Support/PriceListInvitation.php'));

        $this->assertStringNotContainsString('halt(', $source);
        $this->assertStringNotContainsString('ValidationException', $source);
        $this->assertStringContainsString('->warning()', $source);

        // Benar-benar berjalan tanpa melempar apa pun, termasuk saat tidak
        // ada pengguna yang sedang masuk.
        $group = CustomerGroup::create(['name' => 'TANPA HARGA']);

        Customer::create([
            'name' => 'TANPA HARGA',
            'customer_group_id' => $group->id,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL'])->id,
            'address' => 'Jalan Uji',
            'top' => 30,
            'invoice_exchange' => false,
            'is_active' => true,
        ]);

        PriceListInvitation::offerFor($group);

        $this->assertTrue(true);
    }

    /** Grup yang tidak diketahui tidak membuat apa pun meledak. */
    public function test_a_missing_group_is_handled_quietly(): void
    {
        PriceListInvitation::offerFor(null);

        $this->assertTrue(true);
    }

    /** Keterangan grup di form tidak lagi menyesatkan. */
    public function test_the_group_helper_text_matches_what_actually_happens(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Clusters/CustomersCluster/Resources/CustomerResource.php'
        ));

        // Kalimat lama berbunyi "Leave empty if Customer does not have a
        // Group", padahal mengosongkannya justru MEMBUAT grup baru.
        $this->assertStringNotContainsString('does not have a Group', $source);
        $this->assertStringContainsString('create a group named after this customer', $source);
    }
}
