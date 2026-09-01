<?php

namespace Tests\Feature;

use App\Models\DeliveryOrder;
use Tests\TestCase;

/**
 * Yang ditagih adalah berat diterima ATAU berat PO, mana yang lebih kecil.
 *
 * Aturan pelanggan, disampaikan Project Owner 1 September 2026:
 *
 *     berat diterima KURANG dari PO  -> tagihan mengikuti berat diterima
 *     berat diterima LEBIH dari PO   -> tagihan mengikuti PO
 *
 * Sebelumnya invoice selalu memakai berat diterima apa adanya. Sisi kurangnya
 * sudah benar dengan sendirinya, tetapi sisi lebihnya berarti pelanggan
 * ditagih untuk daging yang tidak ia pesan -- dan tidak ada error apa pun yang
 * memberitahu, karena angkanya memang berat yang betul-betul terkirim.
 */
class InvoiceBillableWeightTest extends TestCase
{
    private function invoiceSource(): string
    {
        return file_get_contents(app_path('Filament/Admin/Resources/InvoiceResource.php'));
    }

    /** Batasnya dihitung dengan satu rumus, dipakai keempat tempat. */
    public function test_all_four_calculations_use_the_same_billable_weight(): void
    {
        $source = $this->invoiceSource();

        $this->assertStringContainsString('protected static function billableWeight(', $source);

        $this->assertSame(
            4,
            substr_count($source, '$gross = static::billableWeight($item, $soItem) * $price;'),
            'Keempat tempat perhitungan harus memakai berat yang sama.',
        );

        // Tidak boleh ada lagi yang memakai berat diterima tanpa batas.
        $this->assertStringNotContainsString('$gross = $item->weight * $price;', $source);
    }

    /**
     * Rumusnya benar-benar mengambil yang lebih kecil.
     *
     * Diuji melalui refleksi karena metodenya protected dan tidak butuh
     * basis data sama sekali -- yang diperiksa memang aritmetikanya.
     */
    public function test_the_smaller_of_the_two_weights_is_billed(): void
    {
        $method = new \ReflectionMethod(
            \App\Filament\Admin\Resources\InvoiceResource::class,
            'billableWeight',
        );
        $method->setAccessible(true);

        $receipt = fn (float $weight) => (object) ['weight' => $weight];
        $so = fn (float $weight) => (object) ['weight' => $weight];

        // Kurang dari PO: tagihan mengikuti yang diterima.
        $this->assertSame(48.5, $method->invoke(null, $receipt(48.5), $so(50)));

        // Lebih dari PO: tagihan mengikuti PO.
        $this->assertSame(50.0, $method->invoke(null, $receipt(52.3), $so(50)));

        // Pas: sama saja.
        $this->assertSame(50.0, $method->invoke(null, $receipt(50), $so(50)));
    }

    /**
     * Baris tanpa pasangan di Sales Order ditagih apa adanya.
     *
     * Tidak ada angka PO untuk dibandingkan, dan menolak menagihnya justru
     * menghilangkan barang yang benar-benar dikirim.
     */
    public function test_an_item_without_a_sales_order_line_is_billed_as_delivered(): void
    {
        $method = new \ReflectionMethod(
            \App\Filament\Admin\Resources\InvoiceResource::class,
            'billableWeight',
        );
        $method->setAccessible(true);

        $this->assertSame(
            12.75,
            $method->invoke(null, (object) ['weight' => 12.75], null),
        );
    }

    /**
     * Awalan nomor DO dan nomor resi berasal dari satu tempat.
     *
     * Nomor resi diturunkan dari nomor DO dengan mengganti awalannya. Dulu
     * awalannya diketik ulang sebagai teks di halaman Approve; kalau awalan di
     * model diubah dan salinan itu tertinggal, penggantiannya tidak menemukan
     * apa pun dan nomor resi menjadi SAMA PERSIS dengan nomor DO.
     *
     * Tidak ada yang menahannya: index unique pada `receipt_number` sudah
     * dilepas 1 Juli 2026.
     */
    public function test_the_document_prefixes_have_one_home(): void
    {
        $this->assertSame('SWM-DO#', DeliveryOrder::NUMBER_PREFIX);
        $this->assertSame('SWM-REC#', DeliveryOrder::RECEIPT_NUMBER_PREFIX);

        $approve = file_get_contents(app_path(
            'Filament/Admin/Resources/DeliveryOrderResource/Pages/ApproveDeliveryOrder.php'
        ));

        $this->assertStringContainsString('DeliveryOrder::NUMBER_PREFIX', $approve);
        $this->assertStringContainsString('DeliveryOrder::RECEIPT_NUMBER_PREFIX', $approve);

        // Awalannya tidak boleh diketik ulang sebagai teks di mana pun selain
        // konstantanya sendiri.
        $this->assertStringNotContainsString("'SWM-DO#'", $approve);
        $this->assertStringNotContainsString("'SWM-REC#'", $approve);

        $model = file_get_contents(app_path('Models/DeliveryOrder.php'));

        $this->assertSame(1, substr_count($model, "'SWM-DO#'"));
        $this->assertStringContainsString('self::NUMBER_PREFIX', $model);
    }

    /** Penggantian awalannya menghasilkan nomor resi yang benar. */
    public function test_the_receipt_number_mirrors_the_delivery_order_number(): void
    {
        $doNumber = DeliveryOrder::NUMBER_PREFIX.'260001';

        $this->assertSame(
            'SWM-REC#260001',
            str_replace(
                DeliveryOrder::NUMBER_PREFIX,
                DeliveryOrder::RECEIPT_NUMBER_PREFIX,
                $doNumber,
            ),
        );
    }
}
