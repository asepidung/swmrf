<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\InvoiceResource\Pages\EditInvoice;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Apa yang terjadi pada pembayaran pelanggan ketika invoicenya disunting.
 *
 * Penerimaan piutang dulu MENIMPA `invoices.balance` dengan sisa tagihan.
 * Angka yang semula "berapa yang ditagihkan" berubah makna menjadi "berapa
 * yang masih kurang", dan jumlah aslinya tidak disimpan di mana pun.
 *
 * Sementara itu form Invoice menghitung ulang kolom yang SAMA dari barang,
 * biaya, dan uang muka -- tanpa tahu apa-apa tentang pembayaran. Cukup
 * mengubah satu angka di form dan sisa tagihan melompat kembali ke jumlah
 * penuh: pembayaran yang sudah diterima lenyap dari tagihan, sementara catatan
 * alokasinya tetap ada. Tanpa error, tanpa peringatan.
 *
 * Sekarang keduanya kolom yang berbeda, mengikuti Payable: form menyentuh apa
 * yang DITAGIHKAN, pembayaran menyentuh apa yang SUDAH DIBAYAR, dan sisanya
 * diturunkan Invoice::recalculate() dari keduanya.
 */
class ReceivablePaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceYangSudahDicicil(): Invoice
    {
        $user = User::create([
            'name' => 'Penagih', 'username' => 'penagih_piutang', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'programmer', 'is_active' => true,
        ]);

        $this->actingAs($user);

        $customer = Customer::create([
            'name' => 'PELANGGAN UJI',
            'address' => 'Bogor',
            'top' => 30,
            'customer_segment_id' => CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true])->id,
        ]);

        $product = Product::create([
            'name' => 'TENDERLOIN',
            'code' => 'MT00100',
            'category_id' => ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true])->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);

        $so = SalesOrder::create([
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
            'created_by' => $user->id,
            'status' => 'ready',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'sales_order_id' => $so->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 30,
            'status' => 'Belum Dibayar',
            'total_weight' => 10,
            'subtotal' => 1000000,
            'charge' => 0,
            'down_payment' => 0,
            'balance' => 1000000,
            'created_by' => $user->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'box' => 1,
            'weight' => 10,
            'price' => 100000,
            'discount_percent' => 0,
            'discount_rp' => 0,
            'amount' => 1000000,
        ]);

        // Pelanggan membayar 600.000, lewat jalur yang sama dengan halaman
        // Receive Payment.
        $invoice->applyPayment(600000);

        return $invoice->fresh();
    }

    /**
     * Menyunting invoice yang sudah dicicil TIDAK menghapus pembayarannya.
     *
     * Ini pengujian yang paling penting di berkas ini, karena ia meniru bug
     * yang benar-benar terjadi: cukup mengubah satu angka di form -- harga,
     * uang muka, apa saja yang memicu penghitungan ulang -- dan sisa tagihan
     * melompat kembali ke jumlah penuh.
     */
    public function test_editing_a_partly_paid_invoice_keeps_the_payment(): void
    {
        $invoice = $this->invoiceYangSudahDicicil();

        $this->assertSame(400000.0, (float) $invoice->balance, 'Prasyarat: sisa tagihan sudah berkurang.');

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->fillForm(['down_payment' => 0])
            ->call('save');

        $segar = $invoice->fresh();

        $this->assertSame(
            400000.0,
            (float) $segar->balance,
            'Menyunting invoice mengembalikan sisa tagihan ke jumlah penuh, '
            .'sehingga pembayaran yang sudah diterima hilang dari tagihan.',
        );

        $this->assertSame(600000.0, (float) $segar->paid_amount);
    }

    /** Sisa tagihan tidak pernah negatif, sebesar apa pun yang dibayarkan. */
    public function test_an_overpayment_does_not_push_the_bill_below_zero(): void
    {
        $invoice = $this->invoiceYangSudahDicicil();

        $invoice->applyPayment(999999999);

        $segar = $invoice->fresh();

        $this->assertSame(0.0, (float) $segar->balance);
        $this->assertSame('Lunas', $segar->status);
    }

    /**
     * Menaikkan uang muka mengurangi tagihan, dan pembayarannya TETAP.
     *
     * Inilah bedanya sekarang: form boleh mengubah apa yang ditagihkan, tetapi
     * tidak bisa menyentuh apa yang sudah dibayar -- keduanya bukan kolom yang
     * sama lagi.
     */
    public function test_changing_the_bill_keeps_the_payment_intact(): void
    {
        $invoice = $this->invoiceYangSudahDicicil();

        $invoice->down_payment = 250000;
        $invoice->save();

        $segar = $invoice->fresh();

        $this->assertSame(600000.0, (float) $segar->paid_amount);
        $this->assertSame(150000.0, (float) $segar->balance, '1.000.000 - 250.000 uang muka - 600.000 dibayar');
    }

    /**
     * Sisa tagihan hanya boleh ditulis dari SATU tempat.
     *
     * Seluruh kerusakan ini berasal dari dua pihak yang menulis kolom yang
     * sama tanpa saling tahu. Penjaga ini menahan pihak kedua kembali muncul.
     */
    public function test_the_bill_is_only_ever_written_by_the_model(): void
    {
        $penulis = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $nama = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

            // Modelnya sendiri memang pemiliknya.
            if ($nama === 'Models'.DIRECTORY_SEPARATOR.'Invoice.php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if (preg_match('/\$invoice->balance\s*=[^=]/', $source)) {
                $penulis[] = $nama;
            }
        }

        sort($penulis);

        $this->assertSame(
            [],
            $penulis,
            'Berkas berikut menulis sisa tagihan langsung. Kolom itu milik '
            .'Invoice::recalculate() sepenuhnya, karena ia juga menampung '
            .'pembayaran pelanggan. Pakai applyPayment().',
        );
    }

    /** Dan form Invoice tidak ikut mengirimkannya ke basis data. */
    public function test_the_invoice_form_does_not_submit_the_bill(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/InvoiceResource.php'));

        $blok = substr($source, strpos($source, "static::money('balance')"));
        $blok = substr($blok, 0, strpos($blok, '->columnStart('));

        $this->assertStringContainsString(
            '->dehydrated(false)',
            $blok,
            'Selama form ikut menulis balance, menyunting invoice yang sudah '
            .'dicicil akan menghapus pembayarannya lagi.',
        );
    }

    /**
     * Invoice yang tagihannya sudah habis ditandai lunas, dan yang ternyata
     * belum, dikembalikan.
     */
    public function test_the_status_follows_the_bill_in_both_directions(): void
    {
        $invoice = $this->invoiceYangSudahDicicil();

        $invoice->applyPayment(400000);
        $this->assertSame('Lunas', $invoice->fresh()->status);

        // Tagihannya bertambah karena uang mukanya dibatalkan.
        $invoice->subtotal = 1500000;
        $invoice->save();

        $segar = $invoice->fresh();

        $this->assertSame(500000.0, (float) $segar->balance);
        $this->assertNotSame('Lunas', $segar->status, 'Tagihan yang kembali bersisa tidak boleh tetap berstatus lunas.');
    }
}
