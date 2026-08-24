<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use Tests\TestCase;

/**
 * Pemisah ribuan pada input angka.
 *
 * Aturan lama di project.md menyatakan masking di dalam Repeater dilarang,
 * karena `RawJs::make('$money(...)')` memasang Alpine `x-mask` pada SETIAP
 * input di dalam baris. Saat sebuah baris dihapus, Livewire menghapusnya di
 * server tapi Morphdom gagal membersihkan elemennya karena tertahan proses
 * teardown Alpine — muncullah baris "zombie".
 *
 * Larangan itu benar untuk `$money()`, tapi keliru bila disimpulkan bahwa
 * pemformatan hidup mustahil. Modul Sales Order sudah lama memakai cara lain:
 * satu listener `x-on:input` pada Section pembungkus, DI LUAR baris Repeater.
 * Alpine tidak pernah menempel ke elemen baris, jadi tidak ada yang perlu
 * di-teardown dan bug itu tidak pernah terjadi.
 *
 * Dua hal yang wajib dijaga bersamaan:
 *   1. Field yang diformat TIDAK BOLEH `->numeric()` — itu membuat
 *      `<input type="number">` yang menolak pemisah ribuan.
 *   2. Nilai berformat WAJIB di-parse sebelum disimpan, kalau tidak
 *      "250.000" akan tersimpan sebagai 250.
 */
class RequisitionNumberFormatTest extends TestCase
{
    protected function resourceSource(): string
    {
        return file_get_contents(app_path('Filament/Admin/Resources/ProductRequisitionResource.php'));
    }

    /** @test */
    public function it_parses_a_thousand_separated_string_back_into_a_real_number()
    {
        $this->assertSame(250000.0, ProductRequisitionResource::parseNumber('250.000'));
        $this->assertSame(1234.5, ProductRequisitionResource::parseNumber('1.234,50'));
        $this->assertSame(15000000.0, ProductRequisitionResource::parseNumber('15.000.000'));
        $this->assertSame(300.0, ProductRequisitionResource::parseNumber('300,00'));
        $this->assertSame(0.0, ProductRequisitionResource::parseNumber(''));
    }

    /**
     * Jebakan paling berbahaya: "250.000" yang disimpan mentah dibaca PHP
     * sebagai 250.0, jadi harga 250 ribu berubah jadi 250 tanpa error apa pun.
     *
     * @test
     */
    public function it_does_not_silently_shrink_a_formatted_price_when_parsed()
    {
        $this->assertNotSame(250.0, ProductRequisitionResource::parseNumber('250.000'));
        $this->assertSame(250000.0, ProductRequisitionResource::parseNumber('250.000'));
    }

    /**
     * Field yang ingin menampilkan pemisah ribuan tidak boleh ->numeric().
     *
     * @test
     */
    public function it_keeps_qty_and_price_out_of_native_number_inputs()
    {
        $source = $this->resourceSource();

        foreach (['qty', 'price'] as $field) {
            $start = strpos($source, "TextInput::make('{$field}')");
            $this->assertNotFalse($start, "Field {$field} tidak ditemukan.");

            $chain = substr($source, $start, 900);

            $this->assertStringNotContainsString(
                '->numeric()',
                $chain,
                "Field {$field} memakai ->numeric(), yang membuat <input type=\"number\"> dan menolak pemisah ribuan."
            );
        }
    }

    /**
     * Listener harus di Section, bukan di dalam baris Repeater. Inilah yang
     * membedakannya dari `$money()` dan yang membuatnya bebas dari bug zombie.
     *
     * @test
     */
    public function it_attaches_the_live_formatter_outside_the_repeater_rows()
    {
        $source = $this->resourceSource();

        $sectionAt = strpos($source, "Section::make(fn() => __('Item Details'))");
        $repeaterAt = strpos($source, "Repeater::make('items')");
        $listenerAt = strpos($source, "'x-on:input' =>");

        $this->assertNotFalse($sectionAt);
        $this->assertNotFalse($repeaterAt);
        $this->assertNotFalse($listenerAt, 'Pemformat hidup tidak ditemukan.');

        $this->assertGreaterThan($sectionAt, $listenerAt, 'Listener harus berada di dalam Section Item Details.');
        $this->assertLessThan($repeaterAt, $listenerAt, 'Listener WAJIB di luar Repeater, bukan di dalam barisnya.');
    }

    /**
     * `$money()` dari RawJs memasang mask per-input di dalam baris — itulah
     * yang memicu bug zombie. Pastikan tidak dipakai di modul ini.
     *
     * @test
     */
    public function it_never_uses_the_rawjs_money_mask_in_this_module()
    {
        $this->assertStringNotContainsString('$money(', $this->resourceSource());
    }

    /**
     * Keempat halaman yang menyimpan item wajib mem-parse nilai berformat.
     *
     * @test
     */
    public function it_parses_item_values_on_every_page_that_saves_them()
    {
        $pages = [
            'CreateProductRequisition',
            'EditProductRequisition',
            'ReviewProductRequisition',
            'ApproveFinanceProductRequisition',
        ];

        foreach ($pages as $page) {
            $source = file_get_contents(
                app_path("Filament/Admin/Resources/ProductRequisitionResource/Pages/{$page}.php")
            );

            $this->assertStringContainsString(
                'parseNumber',
                $source,
                "{$page} menyimpan item tanpa mem-parse nilai berformat, sehingga \"250.000\" akan tersimpan sebagai 250."
            );
        }
    }
}
