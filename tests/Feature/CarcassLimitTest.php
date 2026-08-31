<?php

namespace Tests\Feature;

use App\Models\CattleReceivingItem;
use App\Models\CattleWeighingItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batas kewajaran pada hasil pemotongan.
 *
 * Karkas 1, Karkas 2, Hides, dan Tail berasal dari SATU ekor sapi. Dua hal
 * karenanya mustahil, dan keduanya tidak menimbulkan error apa pun kalau
 * tidak diperiksa:
 *
 *  - kedua belahan karkas berbeda jauh, padahal badan sapi dibelah dua;
 *  - jumlah seluruh potongan lebih berat daripada sapinya sendiri.
 *
 * Salah ketik satu digit menghasilkan angka yang lolos begitu saja dan baru
 * terasa jauh kemudian, saat neraca hasil potong tidak masuk akal.
 */
class CarcassLimitTest extends TestCase
{
    use RefreshDatabase;

    private function carcassFieldSource(string $field): string
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/CarcassResource.php'));
        $start = strpos($source, "TextInput::make('".$field."')");
        $next = strpos($source, 'TextInput::make(', $start + 10);

        return substr($source, $start, $next ? $next - $start : 4000);
    }

    /** Tidak ada lagi input bertombol panah di modul ini. */
    public function test_no_carcass_weight_field_uses_spinner_arrows(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/CarcassResource.php'));

        $this->assertStringNotContainsString('->numeric()', $source);
        $this->assertStringNotContainsString('->minValue(', $source);
        $this->assertStringNotContainsString('->maxValue(', $source);

        foreach (['carcass_1', 'carcass_2', 'hides', 'tail'] as $field) {
            $this->assertStringContainsString(
                "'inputmode' => 'decimal'",
                $this->carcassFieldSource($field),
                $field.' masih memakai input angka bawaan.',
            );
        }
    }

    /** Batas selisih kedua belahan tetap 100 kg. */
    public function test_the_two_halves_may_not_differ_by_more_than_a_hundred_kilos(): void
    {
        $field = $this->carcassFieldSource('carcass_2');

        $this->assertStringContainsString('abs($c1 - $c2) > 100', $field);
        $this->assertStringContainsString('differ by more than', $field);
    }

    /** Total potongan dibandingkan dengan bobot sapinya. */
    public function test_the_total_is_checked_against_the_cattle_weight(): void
    {
        $field = $this->carcassFieldSource('tail');

        $this->assertStringContainsString('reference_weight', $field);
        $this->assertStringContainsString('heavier than the cattle itself', $field);
    }

    /**
     * Bobot acuan: hasil penimbangan bila ada, berat penerimaan bila tidak.
     *
     * Catatan Project Owner: kadang ada proses yang tidak melewati
     * penimbangan, dan dalam kasus itu satu-satunya bobot yang pernah
     * tercatat adalah yang diisi saat sapi datang.
     */
    public function test_the_reference_weight_falls_back_to_the_receiving_weight(): void
    {
        $weighed = new CattleWeighingItem(['actual_weight' => 380]);
        $weighed->setRelation('receivingItem', new CattleReceivingItem(['initial_weight' => 400]));

        $this->assertSame(380.0, $weighed->reference_weight);

        // Tidak pernah ditimbang: yang dipakai berat saat penerimaan.
        $unweighed = new CattleWeighingItem(['actual_weight' => 0]);
        $unweighed->setRelation('receivingItem', new CattleReceivingItem(['initial_weight' => 400]));

        $this->assertSame(400.0, $unweighed->reference_weight);
    }

    /**
     * Bobot acuan yang tidak diketahui sama sekali tidak boleh memblokir.
     *
     * Menolak penyimpanan tanpa dasar hanya akan menghentikan pekerjaan di
     * lantai produksi tanpa memberi tahu apa yang harus diperbaiki.
     */
    public function test_an_unknown_reference_weight_does_not_block_the_entry(): void
    {
        $item = new CattleWeighingItem(['actual_weight' => 0]);
        $item->setRelation('receivingItem', null);

        $this->assertSame(0.0, $item->reference_weight);
        $this->assertStringContainsString('$reference <= 0', $this->carcassFieldSource('tail'));
    }

    /** Pesan validasinya bilingual, tidak lagi hardcode. */
    public function test_the_validation_messages_are_translated(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/CarcassResource.php'));

        $this->assertStringNotContainsString("fail('Selisih maksimal", $source);
        $this->assertStringNotContainsString("fail('Carcass 1, 2, dan Hides", $source);

        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);

        $this->assertArrayHasKey('Total :total kg is heavier than the cattle itself (:reference kg).', $id);
        $this->assertArrayHasKey('The two carcass halves differ by more than :max kg; one of them is likely mistyped.', $id);
    }

    /** Ekspor Excel dan PDF wajib ada, dan templatenya benar-benar merender. */
    public function test_the_exports_exist_and_the_pdf_renders(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/CarcassResource.php'));

        $this->assertStringContainsString("Action::make('excel')", $source);
        $this->assertStringContainsString("Action::make('pdf')", $source);

        $html = view('exports.carcasses-pdf', [
            'records' => collect(),
            'title' => 'Karkas',
        ])->render();

        $this->assertStringContainsString('Karkas', $html);
    }
}
