<?php

namespace Tests\Feature;

use App\Models\Carcass;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;
use App\Models\Permission;
use App\Models\PurchaseCattle;
use App\Models\QcFinding;
use App\Models\QcReport;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Laporan QC: dokumen PENDAMPING, satu bentuk untuk semua titik.
 *
 * Keputusan Owner, 6 September 2026: "qc/qa ini sebagai pendamping harusnya
 * bukan proses bisnis tersendiri, secara umum akan sama". Titik pertamanya
 * pemotongan (Carcass); titik berikutnya menyusul menurut arahan Owner.
 *
 * Tiga hal yang dijaga berkas ini, dan ketiganya keputusan Owner:
 *
 *  - **Catatan umum WAJIB, temuan tidak.** Proses yang berjalan tanpa masalah
 *    tetap punya laporan; isinya kalimat "berjalan baik", tanpa satu pun
 *    baris temuan.
 *  - **Waktu KEJADIAN terpisah dari waktu input.** Inilah yang membubarkan
 *    persoalan "terbitnya telat atau kedinian" -- kapan formnya muncul cuma
 *    menentukan kapan QC diingatkan.
 *  - **QC tidak menahan apa pun** di titik ini.
 */
class QcReportTest extends TestCase
{
    use RefreshDatabase;

    private User $qc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->qc = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
    }

    private function carcass(): Carcass
    {
        $supplier = Supplier::firstOrCreate(['name' => 'H DONI'], [
            'address' => 'Bogor',
            'pic' => 'Doni',
            'top_days' => 30,
        ]);

        $po = PurchaseCattle::create([
            'supplier_id' => $supplier->id,
            'shipping_date' => now()->toDateString(),
            'created_by' => $this->qc->id,
        ]);

        $receiving = CattleReceiving::create([
            'purchase_cattle_id' => $po->id,
            'supplier_id' => $supplier->id,
            'receive_date' => now()->toDateString(),
            'created_by' => $this->qc->id,
        ]);

        $weighing = CattleWeighing::create([
            'cattle_receiving_id' => $receiving->id,
            'weighing_date' => now()->toDateString(),
            'created_by' => $this->qc->id,
        ]);

        return Carcass::create([
            'cattle_weighing_id' => $weighing->id,
            'kill_date' => now()->toDateString(),
            'created_by' => $this->qc->id,
        ]);
    }

    /**
     * Laporan tugas yang dibukakan sendiri untuk sebuah carcass.
     *
     * Tidak dibuat di sini -- ia sudah ada begitu carcassnya lahir.
     */
    private function tugas(Carcass $carcass): QcReport
    {
        return $carcass->qcReports()->latest('id')->firstOrFail();
    }

    /** Mengisi tugasnya, seperti yang dikerjakan QC di layar. */
    private function laporan(Carcass $carcass, array $ubah = []): QcReport
    {
        $laporan = $this->tugas($carcass);

        $laporan->update(array_merge([
            'occurred_at' => now()->subHours(3),
            'note' => 'Proses killing berjalan dengan baik tanpa ada masalah.',
            'submitted_at' => now(),
            'created_by' => $this->qc->id,
        ], $ubah));

        return $laporan->fresh();
    }

    // =====================================================================
    // Bentuk dokumennya
    // =====================================================================

    /**
     * Laporan LAHIR SENDIRI begitu dokumen pasangannya dibuat.
     *
     * Keputusan Owner, 7 September 2026: "harusnya enggak ada create, kan dia
     * sifatnya seperti draft atau tugas yang muncul otomatis ketika modul
     * pasangannya dibuat". Yang menulis laporan tidak sedang memilih dokumen,
     * ia sedang mengerjakan tugas yang sudah menunggu.
     */
    public function test_a_report_is_opened_by_itself_when_the_document_is_created(): void
    {
        $carcass = $this->carcass();

        $laporan = $this->tugas($carcass);

        $this->assertTrue($laporan->reportable->is($carcass));
        $this->assertFalse($laporan->sudahDiisi());

        // Belum ada yang menulis apa pun, jadi belum ada yang bertanggung
        // jawab atasnya. Mencatat nama pembuat dokumen pasangannya berarti
        // laporan mutu tercatat atas nama orang yang diperiksa.
        $this->assertNull($laporan->created_by);
        $this->assertNull($laporan->note);
        $this->assertNull($laporan->occurred_at);
    }

    /** Mengisinya menyelesaikan tugasnya. */
    public function test_filling_it_in_finishes_the_task(): void
    {
        $laporan = $this->laporan($this->carcass());

        $this->assertTrue($laporan->sudahDiisi());
        $this->assertSame($this->qc->id, $laporan->created_by);
    }

    /**
     * Laporan tanpa satu pun temuan adalah laporan yang SAH.
     *
     * Proses yang berjalan baik tetap wajib dilaporkan -- justru laporan
     * seperti itu yang paling banyak jumlahnya, dan ketiadaannya di suatu
     * hari yang menjadi pertanyaan saat ditelusuri.
     */
    public function test_a_report_with_no_finding_at_all_is_valid(): void
    {
        $laporan = $this->laporan($this->carcass());

        $this->assertSame(0, $laporan->findings()->count());
        $this->assertNotSame('', trim($laporan->note));
    }

    /** Temuan menempel pada laporannya, dan boleh lebih dari satu. */
    public function test_findings_hang_on_the_report(): void
    {
        $laporan = $this->laporan($this->carcass());

        QcFinding::create([
            'qc_report_id' => $laporan->id,
            'description' => 'Ada 1 sapi yang di-stunning 2 kali.',
            'affected_count' => 1,
            'action_taken' => 'Tembakan kedua ditempatkan di posisi berbeda; alat diperiksa.',
        ]);

        QcFinding::create([
            'qc_report_id' => $laporan->id,
            'description' => 'Lantai gang way licin.',
        ]);

        $this->assertSame(2, $laporan->refresh()->findings()->count());
    }

    /**
     * Berapa yang terkena dan tindakannya BOLEH kosong.
     *
     * Keputusan Owner: keduanya kadang memang belum diketahui saat menulis.
     * Yang tidak boleh kosong keterangannya -- temuan tanpa keterangan bukan
     * temuan.
     */
    public function test_a_finding_may_leave_the_count_and_the_action_empty(): void
    {
        $laporan = $this->laporan($this->carcass());

        $temuan = QcFinding::create([
            'qc_report_id' => $laporan->id,
            'description' => 'Bau tidak biasa di ruang penyimpanan.',
        ]);

        $this->assertNull($temuan->affected_count);
        $this->assertNull($temuan->action_taken);
    }

    /**
     * Waktu kejadian TERPISAH dari waktu input.
     *
     * Inilah yang membubarkan persoalan yang disebut Owner: laporan yang
     * terbit saat carcass dibuat sudah telat, yang terbit saat timbang ulang
     * terlalu dini. Begitu keduanya terpisah, kapan formnya muncul cuma
     * menentukan kapan QC diingatkan -- yang tercatat tetap kapan hal itu
     * benar-benar terjadi.
     */
    public function test_the_time_of_the_event_is_not_the_time_of_typing(): void
    {
        $kejadian = now()->subDay()->setTime(8, 15);

        $laporan = $this->laporan($this->carcass(), ['occurred_at' => $kejadian]);

        $this->assertTrue($laporan->occurred_at->equalTo($kejadian));
        $this->assertFalse($laporan->occurred_at->equalTo($laporan->created_at));
    }

    /** Nomornya terbit sendiri lewat `DocumentNumber`. */
    public function test_the_number_is_issued_by_the_shared_helper(): void
    {
        $satu = $this->tugas($this->carcass());
        $dua = $this->tugas($this->carcass());

        $this->assertSame('QC#'.date('y').'0001', $satu->document_number);
        $this->assertSame('QC#'.date('y').'0002', $dua->document_number);
    }

    /** Dokumen yang didampingi bisa dibaca balik, beserta nomornya. */
    public function test_the_accompanied_document_can_be_read_back(): void
    {
        $carcass = $this->carcass();
        $laporan = $this->laporan($carcass);

        $this->assertTrue($laporan->reportable->is($carcass));
        $this->assertSame($carcass->carcass_number, $laporan->nomorDokumen());
    }

    // =====================================================================
    // Jenis dokumen tidak pernah dipercaya dari alamat
    // =====================================================================

    /**
     * Alamat yang menyebut jenis dokumen tak dikenal DITOLAK.
     *
     * Kalau jenisnya diambil mentah dari URL, laporan QC bisa ditempelkan ke
     * model mana pun di aplikasi ini -- pengguna, pembayaran, apa saja -- dan
     * tidak ada satu pun gejala yang menunjukkan itu terjadi.
     */
    public function test_an_unknown_document_type_is_refused(): void
    {
        $this->assertNull(QcReport::kelasUntuk('user'));
        $this->assertNull(QcReport::kelasUntuk('App\\Models\\User'));
        $this->assertNull(QcReport::kelasUntuk(null));

        $this->assertSame(Carcass::class, QcReport::kelasUntuk('carcass'));
    }

    /**
     * Tidak ada lagi halaman BUAT.
     *
     * Laporan yang dibuat manual tidak mendampingi apa pun. Sejak barisnya
     * lahir sendiri sebagai tugas, jalan itu ditutup seluruhnya -- bukan
     * hanya tombolnya disembunyikan.
     */
    public function test_there_is_no_create_page_any_more(): void
    {
        $this->assertFalse(\App\Filament\Admin\Resources\QcReportResource::canCreate());

        $this->assertArrayNotHasKey(
            'create',
            \App\Filament\Admin\Resources\QcReportResource::getPages(),
        );

        $this->assertFalse(
            class_exists(\App\Filament\Admin\Resources\QcReportResource\Pages\CreateQcReport::class),
            'Halaman buat masih ada; laporan QC bisa dibuat tanpa mendampingi apa pun.',
        );
    }

    // =====================================================================
    // QC tidak menahan apa pun
    // =====================================================================

    /**
     * Carcass tetap bisa dibuat tanpa laporan QC.
     *
     * Keputusan Owner: "qc gak nahan apapun". Menahan produksi karena QC
     * belum sempat mengetik akan membuat orang mencari jalan memutar, dan
     * jalan memutar itu yang menghilangkan datanya sama sekali.
     */
    public function test_a_carcass_is_created_even_though_its_report_is_still_waiting(): void
    {
        $carcass = $this->carcass();

        $this->assertNotNull($carcass->carcass_number);

        // Laporannya ADA, tetapi belum diisi -- dan itu tidak menghalangi
        // apa pun. Tugas yang menunggu bukan gerbang.
        $this->assertFalse($this->tugas($carcass)->sudahDiisi());
    }

    // =====================================================================
    // Tugas di Dashboard
    // =====================================================================

    /**
     * Carcass yang belum ada laporannya muncul sebagai TUGAS, bukan
     * notifikasi sekali kirim.
     *
     * Notifikasi hilang begitu dibaca atau terlewat; tugas bertahan sampai
     * dikerjakan -- dan itulah bedanya pengingat dengan pekerjaan.
     */
    public function test_a_carcass_without_a_report_appears_as_a_pending_task(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $carcass = $this->carcass();

        $pemeriksa = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $pemeriksa->permissions()->attach(Permission::where('name', 'create_qc_reports')->firstOrFail()->id);

        $this->actingAs($pemeriksa);

        $this->assertSame(1, $this->tugasQc());

        $this->laporan($carcass);

        $this->assertSame(0, $this->tugasQc(), 'Tugasnya masih terhitung padahal laporannya sudah diisi.');
    }

    /** Yang tidak boleh menulis laporan tidak diberi tugasnya. */
    public function test_someone_who_cannot_write_reports_is_not_given_the_task(): void
    {
        $this->carcass();

        $lain = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        $this->actingAs($lain);

        $this->assertSame(0, $this->tugasQc());
    }

    /**
     * Hitungan tugasnya terlindung, jadi dipanggil lewat pantulan.
     *
     * Satu jalur untuk semua titik QC; yang diuji di sini titik carcass.
     */
    private function tugasQc(string $kelas = Carcass::class): int
    {
        $metode = new \ReflectionMethod(
            \App\Filament\Admin\Widgets\PendingTaskWidget::class,
            'getDocumentsWithoutQcReportCount',
        );

        $metode->setAccessible(true);

        return $metode->invoke(new \App\Filament\Admin\Widgets\PendingTaskWidget, $kelas);
    }

    // =====================================================================
    // Hak akses
    // =====================================================================

    /** Laporan QC dijaga policy, bukan dibiarkan terbuka. */
    public function test_the_report_is_gated_by_a_policy(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $lain = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        $this->assertFalse($lain->can('viewAny', QcReport::class));
        $this->assertFalse($lain->can('create', QcReport::class));

        $lain->permissions()->attach(Permission::where('name', 'view_qc_reports')->firstOrFail()->id);

        $this->assertTrue($lain->fresh()->can('viewAny', QcReport::class));
        $this->assertFalse($lain->fresh()->can('create', QcReport::class));
    }

    /**
     * Ketujuh titik QC punya relasi `qcReports`, pengamat, dan tugasnya.
     *
     * Menambah titik berarti menambah SATU baris di `DOKUMEN`. Uji ini yang
     * memastikan satu baris itu memang cukup -- kalau relasinya lupa dipasang
     * di modelnya, pengamatnya akan gagal justru saat dokumennya dibuat, dan
     * yang menanggung akibatnya orang produksi di tengah kerja.
     *
     * @dataProvider titikQc
     */
    public function test_every_touchpoint_is_wired_end_to_end(string $kunci, string $kelas): void
    {
        $this->assertSame($kelas, QcReport::kelasUntuk($kunci));

        $this->assertTrue(
            method_exists($kelas, 'qcReports'),
            "$kelas tidak punya relasi qcReports, jadi pengamatnya akan gagal saat dokumennya dibuat.",
        );

        // Pengamatnya benar-benar terpasang untuk kelas ini. Tanpa itu,
        // tugasnya tidak pernah lahir dan tidak ada satu pun galat yang
        // memberitahu -- yang terjadi cuma QC tidak pernah tahu ada
        // pekerjaan.
        $this->assertTrue(
            \Illuminate\Support\Facades\Event::hasListeners('eloquent.created: '.$kelas),
            "Pengamat QC tidak terpasang untuk $kelas.",
        );
    }

    /** @return array<string, array{string, string}> */
    public static function titikQc(): array
    {
        $hasil = [];

        foreach (QcReport::DOKUMEN as $kunci => $kelas) {
            $hasil[$kunci] = [$kunci, $kelas];
        }

        return $hasil;
    }

    /** Kelima izinnya benar-benar ada, bukan hanya disebut kode. */
    public function test_all_five_permissions_exist(): void
    {
        foreach ([
            'view_qc_reports',
            'create_qc_reports',
            'edit_qc_reports',
            'delete_qc_reports',
            'view_deleted_qc_reports',
        ] as $izin) {
            $this->assertTrue(
                Permission::where('name', $izin)->exists(),
                "Izin $izin tidak pernah dibuat, jadi tidak bisa dicentang siapa pun.",
            );
        }
    }
}
