<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PurchaseCattleResource;
use App\Filament\Admin\Resources\PurchaseCattleResource\Pages\ListPurchaseCattle;
use App\Models\CattleClass;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * PO Cattle: yang tidak menimbulkan error, tapi tetap salah.
 *
 * Project Owner mencoba modul ini dan tidak menemukan masalah -- memang
 * tidak ada yang gagal di layar. Semua temuannya berupa kegagalan senyap:
 * nomor yang akan berhenti bekerja di dokumen ke-1000, kolom yang tidak
 * pernah punya isi, dan ekspor wajib yang memang tidak pernah dipasang.
 */
class PurchaseCattleNumberingAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Purchasing Sapi',
            'username' => 'purchasing_cattle',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->supplier = Supplier::create([
            'name' => 'FEEDLOT JAYA',
            'address' => 'Bogor',
            'pic' => 'Doni',
            'top_days' => 30,
        ]);
    }

    private function makePo(?string $documentNumber = null): PurchaseCattle
    {
        return PurchaseCattle::create([
            'document_number' => $documentNumber,
            'supplier_id' => $this->supplier->id,
            'shipping_date' => now()->addDays(5)->toDateString(),
            'created_by' => $this->user->id,
        ]);
    }

    /** Format lama tetap dipertahankan supaya nomor yang sudah terbit tidak berubah arti. */
    public function test_the_first_document_keeps_the_three_digit_format(): void
    {
        $this->assertSame('SWM-CPO#'.date('y').'001', $this->makePo()->document_number);
    }

    /**
     * Dokumen ke-1000 dulu MENGHENTIKAN modul ini sama sekali.
     *
     * Urutan dibaca dengan `substr(-3)`, jadi `...1000` terbaca `000`, urutan
     * berikutnya dihitung 1, dan nomor yang sudah ada dicoba lagi -- menabrak
     * unique index dengan error yang tidak menjelaskan apa-apa. 999 PO setahun
     * kira-kira 2,7 per hari; di RPH itu sangat mungkin tercapai, dan
     * kegagalannya akan datang mendadak di tengah hari kerja.
     */
    public function test_numbering_survives_past_the_thousandth_document(): void
    {
        $prefix = 'SWM-CPO#'.date('y');

        $this->makePo($prefix.'999');

        $this->assertSame($prefix.'1000', $this->makePo()->document_number);
        $this->assertSame($prefix.'1001', $this->makePo()->document_number);
    }

    /**
     * Dan urutannya tidak boleh diambil dengan perbandingan string biasa.
     *
     * `...999` dianggap lebih besar daripada `...1000` karena '9' > '1', jadi
     * nomor berikutnya akan berputar kembali ke 1000 yang sudah terpakai.
     */
    public function test_numbering_does_not_regress_when_lengths_differ(): void
    {
        $prefix = 'SWM-CPO#'.date('y');

        $this->makePo($prefix.'999');
        $this->makePo($prefix.'1000');

        $this->assertSame($prefix.'1001', $this->makePo()->document_number);
    }

    /** Ekspor Excel dan PDF wajib ada di halaman Index, bukan cuma di Detail List. */
    public function test_the_index_page_offers_both_exports(): void
    {
        $this->makePo();

        Livewire::test(ListPurchaseCattle::class)
            ->assertTableHeaderActionsExistInOrder(['excel', 'pdf']);
    }

    /** Dan template PDF-nya benar-benar merender, bukan sekadar ada tombolnya. */
    public function test_the_index_pdf_template_renders(): void
    {
        $po = $this->makePo();
        $class = CattleClass::create(['name' => 'BALI', 'is_active' => true]);

        $po->items()->create([
            'cattle_class_id' => $class->id,
            'qty' => 12,
            'price' => 55000,
            'created_by' => $this->user->id,
        ]);

        $html = View::make('exports.purchase-cattles-pdf', [
            'records' => PurchaseCattle::with(['supplier', 'items'])->get(),
            'title' => 'PO Cattles',
        ])->render();

        $this->assertStringContainsString($po->document_number, $html);
        $this->assertStringContainsString('FEEDLOT JAYA', $html);
        $this->assertStringContainsString('12', $html);
    }

    /**
     * Cetak PO tidak boleh memakai nama orang sungguhan sebagai fallback.
     *
     * Dulu `?? 'AYU'`. Kalau relasinya kosong, dokumen yang dikirim ke
     * supplier tercetak atas nama orang yang tidak membuatnya -- dan tidak
     * terbaca sebagai data hilang, melainkan sebagai penandatangan yang sah.
     */
    public function test_the_printed_po_never_falls_back_to_a_real_persons_name(): void
    {
        $blade = file_get_contents(resource_path('views/print/po-cattle.blade.php'));

        $this->assertStringNotContainsString("?? 'AYU'", $blade);
    }

    /**
     * Kolom Subtotal dihapus, bukan ditambal.
     *
     * `PurchaseCattleItem` tidak pernah punya kolom maupun accessor
     * `subtotal`. Menambalnya dengan `qty * price` akan menghasilkan angka
     * yang SALAH tapi terlihat masuk akal: harga di PO ini per KG sementara
     * qty adalah jumlah EKOR. Angka salah yang tampak wajar lebih berbahaya
     * daripada kolom kosong.
     */
    public function test_no_subtotal_is_displayed_because_it_cannot_be_computed_yet(): void
    {
        $this->assertFalse(
            method_exists(\App\Models\PurchaseCattleItem::class, 'getSubtotalAttribute'),
            'Subtotal tidak boleh dihitung di tahap PO: harga per kg, qty per ekor.',
        );

        // Yang diperiksa PEMAKAIANNYA, bukan kata "subtotal" -- supaya komentar
        // yang menjelaskan kenapa ia dihapus tidak ikut tertangkap.
        foreach ([
            app_path('Filament/Admin/Resources/PurchaseCattleResource/Pages/PurchaseCattleDetailList.php'),
            resource_path('views/exports/purchase-cattle-details-pdf.blade.php'),
        ] as $file) {
            $source = file_get_contents($file);

            $this->assertStringNotContainsString('$record->subtotal', $source, basename($file));
            $this->assertStringNotContainsString("make('subtotal')", $source, basename($file));
        }
    }

    /**
     * Tidak ada penghapusan massal.
     *
     * Penjagaan hapus hidup sebagai Exception di model. Tombol per-baris
     * menahannya lebih dulu lewat `->disabled()`, tapi penghapusan massal
     * menembusnya dan Exception-nya sampai ke pengguna sebagai halaman error
     * mentah. PO Beef dan PO Material juga tidak punya bulk delete.
     */
    public function test_the_index_has_no_bulk_delete(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/PurchaseCattleResource.php'));

        $this->assertStringNotContainsString('DeleteBulkAction', $source);
    }

    /** Penjagaan hapusnya sendiri tetap berlaku dan kini bilingual. */
    public function test_a_po_with_arrivals_cannot_be_deleted(): void
    {
        $po = $this->makePo();

        $po->receivings()->create([
            'receiving_number' => 'CR#26001',
            'supplier_id' => $this->supplier->id,
            'receive_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $this->expectException(\Exception::class);

        $po->delete();
    }

    /** Resource ini memakai modelnya sendiri, jadi Policy otomatis tepat sasaran. */
    public function test_the_policy_is_reachable_for_this_resource(): void
    {
        $this->assertSame(PurchaseCattle::class, PurchaseCattleResource::getModel());
    }
}
