<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Neraca bahan lawan hasil repack: PERINGATAN, bukan penolakan.
 *
 * Keputusan Project Owner, 31 Agustus 2026, dan alasannya datang dari
 * lapangan: dalam praktiknya sering ada barang lain yang ikut masuk, jadi
 * menolak penyimpanan akan menghentikan pekerjaan yang sebenarnya sah.
 *
 * Yang dibandingkan hanya TOTAL bahan lawan TOTAL hasil, bukan per item.
 * Repack daging tidak berpasangan satu-satu -- beberapa item bisa menjadi
 * satu, dan satu item bisa menjadi beberapa.
 *
 * Ambang persennya sengaja BELUM ditentukan: Owner belum tahu berapa susut
 * yang masih wajar. Karena itu persentasenya ditampilkan apa adanya supaya
 * angkanya bisa diamati dulu, dan peringatan keras hanya diberikan untuk
 * kasus yang tidak butuh ambang sama sekali -- hasil yang lebih berat
 * daripada bahannya, yang mustahil secara fisik.
 */
class RepackBalanceWarningTest extends TestCase
{
    private function balancePartial(): string
    {
        return file_get_contents(
            resource_path('views/filament/resources/repack-resource/partials/balance.blade.php')
        );
    }

    /** Peringatannya menjelaskan, dan menegaskan bahwa penyimpanan tidak dihalangi. */
    public function test_the_warning_says_it_does_not_block_saving(): void
    {
        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);

        $key = 'Source :source kg, result :result kg. Please check whether something was recorded twice or a weight was mistyped. This does not block saving.';

        $this->assertArrayHasKey($key, $id);
        $this->assertStringContainsString('tidak menghalangi penyimpanan', $id[$key]);
    }

    /** Yang memicu peringatan hanya hasil yang lebih berat daripada bahan. */
    public function test_only_a_heavier_result_raises_the_warning(): void
    {
        $partial = $this->balancePartial();

        $this->assertStringContainsString('$balance > 0.001', $partial);
        $this->assertStringContainsString('Result is heavier than the source', $partial);
    }

    /**
     * Warna neraca tidak boleh terbalik lagi.
     *
     * Sebelumnya hasil yang lebih berat daripada bahan diwarnai HIJAU dan
     * susut biasa diwarnai MERAH -- kebalikan dari maknanya. Susut kecil itu
     * wajar; hasil yang melebihi bahan justru yang mustahil.
     */
    public function test_the_balance_colour_is_not_inverted(): void
    {
        foreach ([
            'views/filament/resources/repack-resource/partials/balance.blade.php',
            'views/filament/resources/repack-resource/pages/edit-summary.blade.php',
        ] as $file) {
            $source = file_get_contents(resource_path($file));

            $this->assertStringNotContainsString(
                "\$balance < 0 ? 'bg-red",
                $source,
                basename($file).': susut biasa masih diwarnai merah.',
            );
            $this->assertStringNotContainsString(
                '$balance < 0 ? \'background-color: rgba(239, 68, 68',
                $source,
                basename($file).': susut biasa masih diwarnai merah.',
            );
        }
    }

    /** Persentase susut ditampilkan, supaya ambangnya bisa ditentukan nanti. */
    public function test_the_shrink_percentage_is_shown_so_a_threshold_can_be_decided_later(): void
    {
        $this->assertStringContainsString('$shrinkPercent', $this->balancePartial());
    }

    /**
     * Ketiga halaman memakai perhitungan yang SAMA.
     *
     * Dua halaman input memakai partial yang sama; halaman ringkasan punya
     * gaya sendiri karena ia dipakai untuk cetak, tetapi rumus dan ambangnya
     * harus tetap sama -- kalau berbeda, angka yang sama bisa memicu
     * peringatan di satu layar dan tidak di layar lainnya.
     */
    public function test_all_three_screens_use_the_same_rule(): void
    {
        foreach ([
            'views/filament/resources/repack-resource/pages/input-hasil-repack.blade.php',
            'views/filament/resources/repack-resource/pages/input-bahan-repack.blade.php',
        ] as $file) {
            $this->assertStringContainsString(
                'partials.balance',
                file_get_contents(resource_path($file)),
                basename($file).' tidak memakai partial neraca bersama.',
            );
        }

        $summary = file_get_contents(
            resource_path('views/filament/resources/repack-resource/pages/edit-summary.blade.php')
        );

        $this->assertStringContainsString('$balance > 0.001', $summary);
        $this->assertStringContainsString('Result is heavier than the source', $summary);
    }

    /** Halaman-halamannya benar-benar bisa dirender. */
    public function test_the_partial_renders(): void
    {
        $html = view('filament.resources.repack-resource.partials.balance', [
            'totalBahanQty' => 278.76,
            'totalHasilQty' => 300.00,
        ])->render();

        $this->assertStringContainsString('278', $html);
        $this->assertStringContainsString('300', $html);
    }
}
