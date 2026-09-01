<?php

namespace Tests\Feature;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Tests\TestCase;

/**
 * Semua isian tanggal memakai pemilih Filament dan format hari/bulan/tahun.
 *
 * Dua hal yang diperbaiki sekaligus, dan keduanya berlaku untuk 124 isian
 * tanggal di seluruh aplikasi.
 *
 * Isian tanggal bawaan browser hanya bisa dibuka lewat ikon kalender di
 * kanan; mengklik teksnya cuma memindahkan kursor antar bagian tanggal.
 *
 * Yang lebih penting: isian bawaan menampilkan tanggal mengikuti bahasa
 * BROWSER, sehingga di mesin berbahasa Inggris ia tampil `mm/dd/yyyy`.
 * Artinya "03/09" bisa berarti 3 September ATAU 9 Maret tergantung siapa yang
 * membukanya -- pada tanggal kirim dan jatuh tempo, itu ambiguitas yang tidak
 * pernah memunculkan error.
 *
 * Dipasang di SATU tempat lewat configureUsing, bukan disalin ke 124
 * pemanggilan, sehingga isian tanggal yang dibuat nanti ikut mendapatkannya
 * tanpa perlu diingat.
 */
class DatePickerDefaultsTest extends TestCase
{
    /** Tanggal biasa: bisa diklik, dan hari/bulan/tahun. */
    public function test_a_date_picker_is_clickable_and_reads_day_month_year(): void
    {
        $picker = DatePicker::make('tanggal');

        $this->assertFalse($picker->isNative(), 'Masih memakai isian tanggal bawaan browser.');
        $this->assertSame('d/m/Y', $picker->getDisplayFormat());
    }

    /**
     * Tanggal berjam TIDAK ikut kehilangan jamnya.
     *
     * Di Filament, DatePicker adalah TURUNAN dari DateTimePicker -- bukan
     * sebaliknya. Aturan DateTimePicker karena itu ikut mengenai setiap
     * DatePicker, dan yang terdaftar belakangan berjalan belakangan.
     *
     * Kalau urutan pendaftarannya terbalik, seluruh isian tanggal biasa ikut
     * menampilkan jam. Sudah terjadi sekali saat memasangnya.
     */
    public function test_a_date_time_picker_keeps_its_time(): void
    {
        $picker = DateTimePicker::make('waktu');

        $this->assertFalse($picker->isNative());
        $this->assertSame('d/m/Y H:i', $picker->getDisplayFormat());
    }

    /**
     * Urutan pendaftarannya tidak boleh terbalik.
     *
     * Diperiksa pada sumbernya, bukan hanya hasilnya, supaya alasannya ikut
     * terbaca oleh siapa pun yang menyentuh berkas itu.
     */
    public function test_the_registration_order_is_not_swapped(): void
    {
        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $posisiDateTime = strpos($source, 'DateTimePicker::configureUsing');
        $posisiDate = strpos($source, 'DatePicker::configureUsing');

        $this->assertNotFalse($posisiDateTime, 'Konfigurasi DateTimePicker hilang.');
        $this->assertNotFalse($posisiDate, 'Konfigurasi DatePicker hilang.');

        $this->assertLessThan(
            $posisiDate,
            $posisiDateTime,
            'DateTimePicker harus didaftarkan lebih dulu; kalau tidak, tanggal biasa ikut menampilkan jam.',
        );
    }

    /**
     * State-nya tanggal saja, bukan datetime penuh.
     *
     * Inilah bagian yang paling menipu dari perubahan ini. Filament sengaja
     * menyimpan state pemilih non-native sebagai datetime penuh, sementara
     * pemilih bawaan browser menyimpannya sebagai tanggal saja.
     *
     * Untuk isian FORM perbedaannya tidak terasa: dehidrasi mengembalikannya
     * ke Y-m-d sebelum disimpan. Untuk SARINGAN TABEL terasa sekali --
     * saringan membaca state MENTAH tanpa melewati dehidrasi, lalu memakainya
     * di whereDate(). Dan '2026-09-01' >= '2026-09-01 00:00:00' bernilai
     * SALAH karena dibandingkan sebagai teks, sehingga seluruh baris hari itu
     * lenyap dari daftar tanpa satu pun error.
     *
     * Ada 42 berkas yang membaca state saringan tanggal seperti itu.
     */
    public function test_the_state_stays_a_plain_date(): void
    {
        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString('->toDateString()', $source);

        // Buktinya yang sesungguhnya ada di test saringan yang memakai
        // tabel betulan -- CashBookTest, BeefStockMovementTest, dan
        // PayableCategoryTest. Ketiganya GAGAL saat pemilih non-native
        // dipasang tanpa normalisasi ini, dan itulah yang membuat
        // persoalannya ketahuan.
        $this->assertFileExists(base_path('tests/Feature/CashBookTest.php'));
    }

    /**
     * Normalisasinya didaftarkan sebagai "important".
     *
     * Konfigurasi biasa dijalankan SEBELUM setUp() milik Filament, sehingga
     * callback normalisasi langsung ditimpa oleh milik Filament sendiri --
     * perbaikannya tampak terpasang padahal tidak berpengaruh sama sekali.
     * Sudah terjadi sekali saat memasangnya.
     */
    public function test_the_normalisation_runs_after_filaments_own(): void
    {
        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString('isImportant: true', $source);
        $this->assertStringContainsString('afterStateHydrated', $source);
    }

    /**
     * Isian yang butuh format lain masih bisa menimpanya.
     *
     * Pemanggilan berantai di call site berjalan sesudah configureUsing, jadi
     * aturan bersama ini tidak mengunci siapa pun.
     */
    public function test_a_field_can_still_override_the_shared_default(): void
    {
        $picker = DatePicker::make('khusus')->displayFormat('Y-m-d');

        $this->assertSame('Y-m-d', $picker->getDisplayFormat());
    }

    /**
     * Yang tersimpan tetap Y-m-d.
     *
     * Yang berubah hanya yang dibaca manusia. Kalau format simpannya ikut
     * berubah, seluruh perbandingan tanggal di basis data ikut rusak.
     */
    public function test_the_stored_format_is_untouched(): void
    {
        $this->assertSame('Y-m-d', DatePicker::make('tanggal')->getFormat());
    }
}
