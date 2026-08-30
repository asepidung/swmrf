<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\PurchaseProduct;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nama relasi yang salah tidak menghasilkan error -- ia menghasilkan teks yang
 * tampak wajar.
 *
 * Tiga bug sekaligus ditemukan Project Owner pada 30 Agustus 2026, semuanya
 * berbentuk sama:
 *
 *   - Cetak PO memakai `$item->Beef->name`, padahal relasinya `product()`.
 *     Karena ada `?? '-'`, kolom nama produk tercetak sebagai strip.
 *   - Cetak PO memakai `$record->approvedBy->name`, padahal relasi di
 *     PurchaseProduct bernama `approver()`. Karena ada `?? 'FINANCE'`, setiap
 *     PO tercetak dengan penandatangan bernama "FINANCE" -- terbaca seolah
 *     memang begitu formatnya, bukan seolah ada yang rusak.
 *   - Halaman View memakai `TextInput::make('productRequisition.document_number')`,
 *     padahal form diisi dari `attributesToArray()` yang hanya memuat kolom
 *     tabel tanpa relasi. Fieldnya selalu kosong.
 *
 * Ketiganya lolos berbulan-bulan karena operator `??` mengubah kegagalan
 * menjadi tampilan yang masuk akal. Itu sebabnya test ini tidak cukup
 * memeriksa "halamannya terbuka" -- ia memeriksa NILAI yang seharusnya muncul
 * benar-benar muncul.
 */
class PurchaseOrderRelationDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function buildPurchaseOrder(): PurchaseProduct
    {
        $requester = User::create([
            'name' => 'Rafi Pemohon',
            'username' => 'rafi_po_display',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        $approver = User::create([
            'name' => 'Idung Finance',
            'username' => 'idung_po_display',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'name' => 'TUKANG DAGING',
            'address' => 'Bogor',
            'pic' => 'Doni',
            'top_days' => 30,
        ]);

        $category = ProductCategory::create(['name' => 'PRIMARY CUTS', 'prefix' => 1]);

        $product = Product::create([
            'name' => 'CUBEROLL',
            'code' => '100100',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        $requisition = ProductRequisition::create([
            'user_id' => $requester->id,
            'document_number' => 'SWM-RQB#26007',
            'request_date' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'due_date' => now()->toDateString(),
            'status' => 'PO Created',
            'reviewed_by' => $requester->id,
        ]);

        $po = PurchaseProduct::create([
            'po_number' => 'SWM-BPO#26001',
            'product_requisition_id' => $requisition->id,
            'supplier_id' => $supplier->id,
            'approved_by' => $approver->id,
            'po_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $po->items()->create([
            'product_id' => $product->id,
            'qty' => 10,
            'price' => 215000,
            'subtotal' => 2150000,
        ]);

        return $po->fresh(['items.product', 'approver', 'productRequisition.user']);
    }

    public function test_printed_purchase_order_shows_the_product_name_and_the_real_approver(): void
    {
        $po = $this->buildPurchaseOrder();

        $html = View::make('print.po-product', ['record' => $po])->render();

        // Nama produk: dulu selalu '-' karena relasi `Beef` tidak ada.
        $this->assertStringContainsString('CUBEROLL', $html);

        // Penandatangan: dulu selalu kata "FINANCE" karena relasinya `approver`,
        // bukan `approvedBy`. Kata itu tidak boleh muncul sebagai nama orang.
        $this->assertStringContainsString('Idung Finance', $html);
        $this->assertStringNotContainsString('>FINANCE<', $html);

        // Label kolom mengikuti keputusan Owner: "Product Name", bukan "Beef Name".
        $this->assertStringContainsString('Product Name', $html);
        $this->assertStringNotContainsString('Beef Name', $html);
    }

    public function test_the_view_page_header_actually_shows_the_request_number_and_requester(): void
    {
        $po = $this->buildPurchaseOrder();

        // Programmer supaya penjagaan hak akses tidak ikut diuji di sini.
        $this->actingAs(User::where('username', 'idung_po_display')->first());

        Livewire::test(
            \App\Filament\Admin\Resources\PurchaseProductResource\Pages\ViewPurchaseProduct::class,
            ['record' => $po->id],
        )
            ->assertFormSet([
                // Nomornya dibaca dari record, bukan ditulis ulang di sini:
                // ProductRequisition menomori dirinya sendiri saat dibuat.
                'request_number' => $po->productRequisition->document_number,
                'requester_name' => 'Rafi Pemohon',
            ]);

        // Dan nomor itu memang ada isinya -- assertion di atas akan lolos
        // dengan sendirinya kalau keduanya sama-sama null.
        $this->assertNotEmpty($po->productRequisition->document_number);
    }

    public function test_no_form_field_is_named_after_a_relation_path(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (preg_match_all(
                "/(TextInput|DatePicker|DateTimePicker|Textarea|Select|Toggle)::make\('([a-zA-Z_]+\.[a-zA-Z_.]+)'\)/",
                $contents,
                $matches,
                PREG_SET_ORDER,
            )) {
                foreach ($matches as $match) {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).' -> '.$match[2];
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Field form tidak boleh dinamai jalur relasi. Halaman View mengisi form dari "
                ."`attributesToArray()`, yang hanya memuat kolom tabel -- jadi field seperti ini "
                ."SELALU kosong tanpa error apa pun. Pakai nama datar plus "
                ."`->formatStateUsing(fn (\$record) => data_get(\$record, 'relasi.kolom'))`. "
                ."Yang melanggar:\n".implode("\n", $offenders),
        );
    }
}
