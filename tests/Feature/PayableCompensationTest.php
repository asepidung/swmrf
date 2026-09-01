<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kompensasi dari pemasok: potongan TOTAL, dengan alasannya.
 *
 * Latar belakangnya dari lapangan: pesanan datang lengkap, tetapi kualitas
 * sapinya buruk sehingga purchasing menawar kompensasi.
 *
 * Bentuknya potongan total, bukan potongan per kilo -- keputusan Project
 * Owner, 1 September 2026: yang sebenarnya dinegosiasikan memang angka bulat,
 * dan menurunkannya menjadi harga per kilo adalah ketelitian yang dikarang.
 *
 * Harga di Purchase Order TIDAK diubah. PO adalah catatan kesepakatan;
 * menurunkan harganya di belakang menghapus selisih antara yang disepakati
 * dan yang akhirnya dibayar.
 */
class PayableCompensationTest extends TestCase
{
    use RefreshDatabase;

    private function payable(float $amount = 100_000_000, float $paid = 0): Payable
    {
        $user = User::create([
            'name' => 'Purchasing', 'username' => 'purchasing_'.uniqid(),
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        $payable = new Payable();
        // Hutang selalu lahir dari sebuah dokumen; di sini penerimaan sapi,
        // karena itulah jalur yang memakai kompensasi.
        $payable->payableable_type = \App\Models\CattleReceiving::class;
        $payable->payableable_id = 1;
        $payable->supplier_id = Supplier::create([
            'name' => 'PEMASOK '.uniqid(),
            'address' => 'Bogor',
            'pic' => 'Doni',
            'top_days' => 30,
        ])->id;
        $payable->document_number = 'UJI-'.uniqid();
        $payable->amount = $amount;
        $payable->paid_amount = $paid;
        $payable->due_date = now()->addDays(30);
        $payable->created_by = $user->id;
        $payable->recalculate();
        $payable->save();

        return $payable;
    }

    /** Hutang berkurang, nilai aslinya tetap terbaca. */
    public function test_compensation_lowers_the_payable_without_touching_the_agreed_amount(): void
    {
        $payable = $this->payable(100_000_000);

        $payable->applyCompensation(5_000_000, 'Lemaknya terlalu banyak');

        $payable->refresh();

        $this->assertEquals(100_000_000, (float) $payable->amount, 'Nilai asli tidak boleh berubah.');
        $this->assertEquals(5_000_000, (float) $payable->compensation);
        $this->assertEquals(95_000_000, (float) $payable->balance);
        $this->assertSame('unpaid', $payable->status);
    }

    /** Kompensasi menumpuk kalau dicatat lebih dari sekali. */
    public function test_compensation_accumulates(): void
    {
        $payable = $this->payable(10_000_000);

        $payable->applyCompensation(1_000_000);
        $payable->applyCompensation(500_000);

        $this->assertEquals(1_500_000, (float) $payable->fresh()->compensation);
        $this->assertEquals(8_500_000, (float) $payable->fresh()->balance);
    }

    /**
     * Kompensasi yang menutup seluruh tagihan membuatnya lunas.
     *
     * Tanpa satu rupiah pun berpindah -- dan itu memang benar.
     */
    public function test_a_full_compensation_settles_the_payable(): void
    {
        $payable = $this->payable(2_000_000);

        $payable->applyCompensation(2_000_000);

        $this->assertSame('paid', $payable->fresh()->status);
        $this->assertEquals(0, (float) $payable->fresh()->balance);
    }

    /**
     * Tidak boleh melebihi sisa yang belum dibayar.
     *
     * Kompensasi yang lebih besar daripada sisa hutang berarti pemasok
     * berhutang kepada kita, dan itu hal lain yang butuh dokumennya sendiri.
     */
    public function test_compensation_cannot_exceed_the_outstanding_amount(): void
    {
        $payable = $this->payable(10_000_000, paid: 7_000_000);

        $this->expectException(\InvalidArgumentException::class);

        $payable->applyCompensation(4_000_000);
    }

    /** Nol dan angka minus ditolak. */
    public function test_a_zero_compensation_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->payable()->applyCompensation(0);
    }

    /**
     * Sisa hutang sesudah kompensasi ikut dihitung saat membayar.
     *
     * Inilah yang dulu tidak mungkin: rumus saldo dan status disalin di enam
     * tempat, sehingga faktor baru seperti kompensasi hanya berlaku di
     * sebagian -- dan hutang yang sama menunjukkan angka berbeda tergantung
     * jalur mana yang menyentuhnya terakhir.
     */
    public function test_paying_the_reduced_balance_settles_the_payable(): void
    {
        $payable = $this->payable(10_000_000);

        $payable->applyCompensation(2_000_000);

        $payable->paid_amount = 8_000_000;
        $payable->recalculate();
        $payable->save();

        $this->assertSame('paid', $payable->fresh()->status);
        $this->assertEquals(0, (float) $payable->fresh()->balance);
    }

    /**
     * Rumus saldo dan status hanya ada di SATU tempat.
     *
     * Sebelumnya disalin di enam tempat -- lima di model dan satu di halaman
     * pembayaran. Selama semuanya menghitung hal yang sama, salinannya tidak
     * terasa; begitu ada faktor baru, ia hanya berlaku di sebagian.
     */
    public function test_the_balance_rule_lives_in_one_place(): void
    {
        $model = file_get_contents(app_path('Models/Payable.php'));

        $this->assertSame(1, substr_count($model, '$this->balance = '));
        $this->assertStringNotContainsString("\$payable->status = 'partial'", $model);

        $page = file_get_contents(app_path(
            'Filament/Admin/Resources/PayableResource/Pages/ViewPayable.php'
        ));

        $this->assertStringContainsString('$this->record->recalculate();', $page);
        $this->assertStringNotContainsString("\$this->record->status = 'paid'", $page);
    }

    /**
     * Mencatat kompensasi butuh haknya sendiri.
     *
     * Ia MENGURANGI yang harus dibayar perusahaan, jadi ia keputusan uang --
     * terpisah dari melihat daftar hutang, dan terpisah pula dari membayar.
     */
    public function test_recording_compensation_needs_its_own_permission(): void
    {
        $seeder = file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));

        $this->assertStringContainsString("'record_payable_compensations'", $seeder);

        $page = file_get_contents(app_path(
            'Filament/Admin/Resources/PayableResource/Pages/ViewPayable.php'
        ));

        $this->assertStringContainsString(
            "hasPermission('record_payable_compensations')",
            $page,
        );
    }

    /**
     * Ketiga tombol berdampingan, dengan label sependek mungkin.
     *
     * Menyembunyikan sebagiannya ke dalam tombol titik tiga memang merapikan
     * barisnya, tetapi menukar kerapian dengan satu ketukan tambahan -- dan
     * yang ikut tersembunyi justru Kembali, yang paling sering dicari.
     */
    public function test_the_actions_stand_side_by_side_with_short_labels(): void
    {
        $page = file_get_contents(app_path(
            'Filament/Admin/Resources/PayableResource/Pages/ViewPayable.php'
        ));

        $this->assertStringNotContainsString('ActionGroup::make([', $page);

        foreach (["__('Pay')", "__('Compensate')", "__('Back')"] as $label) {
            $this->assertStringContainsString($label, $page);
        }
    }

    /**
     * Kompensasi TIDAK PERNAH menyentuh kerugian.
     *
     * Rancangan sebelumnya membedakan kompensasi karena berat dan karena
     * kualitas, dan yang karena berat ikut mengurangi kerugian susut.
     * Penjelasan Owner membatalkan dasarnya: komplainnya selalu soal mutu --
     * lemaknya terlalu banyak, hasil dagingnya sedikit. Pemasok tidak pernah
     * mengganti karena beratnya kurang.
     *
     * Pembedaan itu membedakan sesuatu yang tidak dibedakan di lapangan, dan
     * justru pembedaan itulah bagian yang berbahaya: salah memilih alasan
     * menghapus kerugian susut yang nyata, tanpa satu pun gejala.
     */
    public function test_compensation_never_touches_a_recorded_loss(): void
    {
        $model = file_get_contents(app_path('Models/Payable.php'));

        $this->assertStringNotContainsString('recoverWeightLoss', $model);
        $this->assertStringNotContainsString('COMPENSATION_FOR_WEIGHT', $model);
        $this->assertStringNotContainsString('recovered_amount', $model);

        $page = file_get_contents(app_path(
            'Filament/Admin/Resources/PayableResource/Pages/ViewPayable.php'
        ));

        $this->assertStringNotContainsString("Radio::make('reason')", $page);
    }

    /**
     * Kompensasi yang lebih besar daripada kerugian tidak perlu dibatasi.
     *
     * Pertanyaan Owner: bagaimana kalau kompensasinya 1 juta sementara
     * kerugian susutnya hanya 500 ribu? Dengan aturan yang disederhanakan,
     * pertanyaan itu hilang sendiri -- kompensasi memang tidak pernah
     * menyentuh kerugian, jadi tidak ada yang perlu dibatasi.
     *
     * Satu-satunya batas yang tersisa adalah sisa tagihan.
     */
    public function test_a_compensation_larger_than_any_loss_is_fine(): void
    {
        $payable = $this->payable(5_000_000);

        $payable->applyCompensation(1_000_000, 'Hasil dagingnya sedikit');

        $this->assertEquals(4_000_000, (float) $payable->fresh()->balance);

        // Tidak ada lagi pembatas yang membandingkan kompensasi dengan
        // kerugian; satu-satunya batas adalah sisa tagihan.
        $model = file_get_contents(app_path('Models/Payable.php'));

        $this->assertStringNotContainsString('bisaDipulihkan', $model);
        $this->assertStringContainsString('Compensation cannot be more than the outstanding amount.', $model);
    }
}
