<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CattleWeighingResource\Pages\ListCattleWeighings;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;
use App\Models\FinancialLoss;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Penimbangan ulang sapi, dan susut yang lahir darinya.
 *
 * Yang paling mahal di modul ini bukan salah hitung, melainkan berat yang
 * TIDAK diisi. Baris penimbangan terisi otomatis dengan 0 saat draft dibuka,
 * dan perhitungan susut menghitung selisih setiap kali berat aktual lebih
 * kecil dari berat terima. Satu ekor yang terlewat -- masih bernilai 0 --
 * tercatat sebagai kerugian sebesar SELURUH bobot sapi itu dikali harga,
 * tanpa error dan tanpa gejala apa pun di layar.
 */
class CattleWeighingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected CattleClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Petugas Timbang',
            'username' => 'petugas_timbang',
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->supplier = Supplier::create([
            'name' => 'FEEDLOT JAYA', 'address' => 'Bogor', 'pic' => 'Doni', 'top_days' => 30,
        ]);

        $this->class = CattleClass::create(['name' => 'BALI', 'is_active' => true]);
    }

    private function weighing(array $cattle, int $pricePerKg = 55000): CattleWeighing
    {
        $po = PurchaseCattle::create([
            'supplier_id' => $this->supplier->id,
            'shipping_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $po->items()->create([
            'cattle_class_id' => $this->class->id,
            'qty' => 10,
            'price' => $pricePerKg,
            'created_by' => $this->user->id,
        ]);

        $receiving = CattleReceiving::create([
            'purchase_cattle_id' => $po->id,
            'supplier_id' => $this->supplier->id,
            'receive_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $weighing = CattleWeighing::create([
            'cattle_receiving_id' => $receiving->id,
            'weighing_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        foreach ($cattle as $eartag => $weights) {
            $item = $receiving->items()->create([
                'cattle_class_id' => $this->class->id,
                'eartag' => $eartag,
                'initial_weight' => $weights['initial'],
            ]);

            $weighing->items()->create([
                'cattle_receiving_item_id' => $item->id,
                'cattle_class_id' => $this->class->id,
                'eartag' => $eartag,
                'initial_weight' => $weights['initial'],
                'actual_weight' => $weights['actual'],
            ]);
        }

        return $weighing->fresh('items');
    }

    /** Susut dihitung dari selisih berat dikali harga kesepakatan di PO. */
    public function test_shrinkage_is_recorded_as_a_financial_loss(): void
    {
        $weighing = $this->weighing([
            'ID-2001' => ['initial' => 400, 'actual' => 390],
            'ID-2002' => ['initial' => 350, 'actual' => 350],
        ]);

        $weighing->calculateAndSaveFinancialLoss();

        $loss = FinancialLoss::first();

        $this->assertNotNull($loss);
        $this->assertEquals(10 * 55000, $loss->amount);
        $this->assertSame('Cattle Weighing', $loss->transaction_type);

        // Beratnya ikut disimpan, bukan dibuang sesudah dikalikan. Rupiahnya
        // saja tidak menjawab "berapa kilo yang hilang bulan ini", dan
        // kolomnya dipakai bersama dengan susut kirim.
        $this->assertEquals(10.0, (float) $loss->quantity);
        $this->assertSame('Kg', $loss->unit);

        // Yang ini SUDAH dinilai, jadi tidak boleh dianggap menunggu harga.
        $this->assertFalse($loss->isNotPricedYet());
    }

    /**
     * Berat yang susut tetap tercatat meski harganya belum ketemu.
     *
     * Syarat lamanya `$totalLoss > 0` -- RUPIAHNYA. Ketika harga sapinya nol
     * di ketiga sumbernya (PO, histori pemasok, rata-rata PO), pengalinya nol
     * dan rupiahnya nol, sehingga kerugiannya masuk ke cabang `else` dan
     * DIHAPUS. Kilogram yang benar-benar hilang lenyap dari laporan tanpa
     * galat apa pun.
     *
     * Susut kirim di Surat Jalan sudah lama menyimpan beratnya dengan
     * `amount` nol sambil menunggu HPP. Dua tempat yang menghitung hal yang
     * sama harus menjawab sama.
     */
    public function test_shrinkage_is_still_recorded_when_the_price_is_unknown(): void
    {
        $weighing = $this->weighing([
            'ID-3001' => ['initial' => 400, 'actual' => 385],
        ], pricePerKg: 0);

        $weighing->calculateAndSaveFinancialLoss();

        $loss = FinancialLoss::first();

        $this->assertNotNull($loss, 'Susut 15 Kg hilang dari laporan hanya karena harganya belum ketemu.');
        $this->assertEqualsWithDelta(15.0, (float) $loss->quantity, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $loss->amount, 0.001);

        // Nol yang berarti "belum dinilai", bukan nol yang berarti "tidak rugi".
        $this->assertTrue($loss->isNotPricedYet());
    }

    /** Sapi yang justru bertambah berat tidak menghasilkan kerugian. */
public function test_no_loss_when_nothing_shrank(): void
    {
        $weighing = $this->weighing([
            'ID-2003' => ['initial' => 400, 'actual' => 405],
        ]);

        $weighing->calculateAndSaveFinancialLoss();

        $this->assertSame(0, FinancialLoss::count());
    }

    /**
     * INILAH yang dijaga paling ketat.
     *
     * Berat aktual 0 berarti seluruh bobot sapi dihitung sebagai susut. Kalau
     * itu boleh tersimpan, satu ekor yang terlewat menjadi kerugian jutaan
     * rupiah di laporan tanpa ada yang menyadarinya. Karena itu batas
     * bawahnya 1, bukan 0.
     */
    public function test_a_cattle_left_at_zero_would_be_recorded_as_a_total_loss(): void
    {
        $weighing = $this->weighing([
            'ID-2004' => ['initial' => 400, 'actual' => 0],
        ]);

        $weighing->calculateAndSaveFinancialLoss();

        // Membuktikan kenapa nol harus ditolak di form: inilah akibatnya.
        $this->assertEquals(400 * 55000, FinancialLoss::first()->amount);

        $field = $this->weightFieldSource();

        $this->assertStringContainsString("'min:1'", $field, 'Berat aktual masih boleh nol.');
        $this->assertStringNotContainsString('->minValue(0)', $field);
    }

    /**
     * Input berat tidak boleh berupa <input type="number">.
     *
     * Tombol panahnya gampang tertekan tanpa sengaja, dan berat sapi berubah
     * tanpa ada yang menyadarinya. `inputmode` tetap memunculkan papan ketik
     * angka di ponsel tanpa memberi tombol panah.
     */
    public function test_the_weight_input_has_no_spinner_arrows(): void
    {
        $field = $this->weightFieldSource();

        $this->assertStringNotContainsString('->numeric()', $field);
        $this->assertStringNotContainsString('->minValue(', $field);
        $this->assertStringNotContainsString('->maxValue(', $field);
        $this->assertStringContainsString("'inputmode' => 'decimal'", $field);
    }

    /** Berat aktual juga dibatasi 800 kg, sama seperti berat terima. */
    public function test_the_actual_weight_shares_the_same_upper_limit_as_receiving(): void
    {
        $this->assertStringContainsString("'max:800'", $this->weightFieldSource());
    }

    /** Ekspor Excel dan PDF wajib ada. */
    public function test_the_index_offers_both_exports(): void
    {
        Livewire::test(ListCattleWeighings::class)
            ->assertTableHeaderActionsExistInOrder(['excel', 'pdf']);
    }

    /** Dan template PDF-nya benar-benar merender. */
    public function test_the_pdf_template_renders_with_its_totals(): void
    {
        $weighing = $this->weighing([
            'ID-2005' => ['initial' => 400, 'actual' => 380],
        ]);

        $html = View::make('exports.cattle-weighings-pdf', [
            'records' => CattleWeighing::with(['items', 'receiving.supplier'])->get(),
            'title' => 'Penimbangan Sapi',
        ])->render();

        $this->assertStringContainsString($weighing->weighing_number, $html);
        $this->assertStringContainsString('FEEDLOT JAYA', $html);
        // Susut 20 kg harus muncul, bukan cuma berat mentahnya.
        $this->assertStringContainsString('20', $html);
    }

    /** Catatan kerugian ikut bilingual, tidak lagi hardcode. */
    public function test_the_loss_note_goes_through_translation(): void
    {
        $source = file_get_contents(app_path('Models/CattleWeighing.php'));

        $this->assertStringNotContainsString("'note' => 'Susut Timbang Ulang Sapi'", $source);
        $this->assertStringContainsString("__('Cattle re-weighing shrinkage')", $source);

        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);
        $this->assertSame('Susut Timbang Ulang Sapi', $id['Cattle re-weighing shrinkage']);
    }

    private function weightFieldSource(): string
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/CattleWeighingResource.php'));
        $field = substr($source, strpos($source, "TextInput::make('actual_weight')"));

        return substr($field, 0, strpos($field, "TextInput::make('notes')") ?: 4000);
    }

    // =====================================================================
    // Penimbangan yang dilewati
    // =====================================================================

    /**
     * Seluruh baris kosong berarti penimbangannya DILEWATI, dan tidak ada
     * kerugian apa pun yang dicatat.
     *
     * Kelonggaran Owner untuk hari yang sangat repot: dokumennya dibuat
     * supaya carcass bisa jalan, penimbangannya tidak.
     *
     * SEBELUM ini kelonggaran itu justru menghasilkan kebalikannya. Form
     * mengisi tiap baris dengan `actual_weight = 0`, dan hitungan susut
     * membaca 0 < berat terima untuk SETIAP ekor -- satu dokumen yang
     * "dikosongkan" tercatat sebagai kerugian sebesar seluruh bobot satu
     * batch, tanpa galat dan tanpa gejala.
     */
    public function test_a_weighing_with_every_row_empty_records_no_loss(): void
    {
        $weighing = $this->weighing([
            'ID-4001' => ['initial' => 400, 'actual' => null],
            'ID-4002' => ['initial' => 350, 'actual' => null],
        ]);

        $this->assertTrue($weighing->weighingWasSkipped());

        $weighing->calculateAndSaveFinancialLoss();

        $this->assertSame(
            0,
            FinancialLoss::count(),
            'Dokumen yang penimbangannya dilewati tercatat sebagai kerugian.',
        );
    }

    /**
     * Sebagian kosong BUKAN kelonggaran.
     *
     * Kelonggarannya berlaku untuk satu dokumen utuh -- "kalo semua sapi gak
     * ada actual weight". Satu baris kosong di antara yang terisi adalah
     * kelupaan, dan yang terisi tetap dihitung.
     */
    public function test_a_partly_filled_weighing_is_not_a_skip(): void
    {
        $weighing = $this->weighing([
            'ID-5001' => ['initial' => 400, 'actual' => 390],
            'ID-5002' => ['initial' => 350, 'actual' => null],
        ]);

        $this->assertFalse($weighing->weighingWasSkipped());

        $weighing->calculateAndSaveFinancialLoss();

        // Hanya 10 kg dari sapi yang BENAR-BENAR ditimbang. Kalau yang kosong
        // ikut terhitung sebagai nol, angkanya menjadi 360.
        $this->assertEqualsWithDelta(10.0, (float) FinancialLoss::first()->quantity, 0.001);
    }

    /**
     * Penjaga lama TIDAK ikut mati: nol yang benar-benar diketik tetap
     * kerugian penuh.
     *
     * Inilah bedanya kosong dan nol. Kosong berarti belum diukur; nol berarti
     * diukur dan hasilnya nol, dan yang kedua memang mustahil kecuali ada
     * yang keliru -- karena itu tetap ditandai sebesar seluruh bobot sapinya.
     */
    public function test_a_zero_that_was_actually_typed_is_still_a_total_loss(): void
    {
        $weighing = $this->weighing([
            'ID-6001' => ['initial' => 400, 'actual' => 0],
        ]);

        $this->assertFalse($weighing->weighingWasSkipped());

        $weighing->calculateAndSaveFinancialLoss();

        $this->assertEqualsWithDelta(400.0, (float) FinancialLoss::first()->quantity, 0.001);
    }

    /**
     * Kerugian yang sudah terlanjur tercatat ikut dicabut saat dokumennya
     * dikosongkan.
     *
     * Timbang ulang bisa disunting. Kalau angkanya dihapus tetapi baris
     * kerugiannya tinggal, laporan kerugian memuat angka yang tidak lagi
     * punya dasar di dokumen mana pun.
     */
    public function test_an_existing_loss_is_withdrawn_when_the_weights_are_cleared(): void
    {
        $weighing = $this->weighing([
            'ID-7001' => ['initial' => 400, 'actual' => 390],
        ]);

        $weighing->calculateAndSaveFinancialLoss();

        $this->assertSame(1, FinancialLoss::count());

        $weighing->items()->update(['actual_weight' => null]);

        $weighing->refresh()->calculateAndSaveFinancialLoss();

        $this->assertSame(0, FinancialLoss::count());
    }

    /** Kolom beratnya memang boleh kosong. */
    public function test_the_weight_column_may_be_empty(): void
    {
        $nullable = null;

        foreach (\Illuminate\Support\Facades\Schema::getColumns('cattle_weighing_items') as $kolom) {
            if ($kolom['name'] === 'actual_weight') {
                $nullable = $kolom['nullable'];
            }
        }

        $this->assertTrue(
            $nullable,
            'Kolom berat aktual tidak boleh kosong, jadi "belum ditimbang" dan "ditimbang, hasilnya nol" '
            .'tidak bisa dibedakan sama sekali.',
        );
    }

    /**
     * Form menolak dokumen yang HANYA SEBAGIAN terisi.
     *
     * Ini pasangan wajib dari aturan di atas. Tanpa penolakan ini, satu ekor
     * yang terlewat berbaur dengan kelonggaran yang disengaja, dan tidak ada
     * satu pun cara membedakannya lagi sesudah dokumennya tersimpan.
     */
    public function test_the_form_refuses_a_half_filled_weighing(): void
    {
        $sumber = file_get_contents(
            app_path('Filament/Admin/Resources/CattleWeighingResource.php')
        );

        $this->assertStringContainsString(
            'Fill in every weight, or leave all of them empty.',
            $sumber,
            'Aturan semua-atau-tidak-sama-sekali hilang dari form timbang ulang.',
        );
    }
}
