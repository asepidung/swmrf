<?php

namespace Tests\Feature;

use App\Support\MasterDataDeletion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penolakan basis data disampaikan sebagai kalimat, bukan galat SQL.
 *
 * Data induk seperti Gudang dan Grade ditunjuk banyak dokumen, dan kunci
 * asingnya RESTRICT -- datanya memang aman. Yang tidak aman adalah CARA
 * penolakannya sampai ke layar: sebagai galat SQL mentah lengkap dengan nama
 * constraint, yang tidak memberi tahu apa pun kepada orang yang menekannya.
 */
class MasterDataDeletionTest extends TestCase
{
    use RefreshDatabase;

    /** Yang tidak dipakai siapa pun tetap terhapus seperti biasa. */
    public function test_it_deletes_what_nothing_points_at(): void
    {
        $terhapus = false;

        $berhasil = MasterDataDeletion::attempt(function () use (&$terhapus): void {
            $terhapus = true;
        }, 'Gudang JONGGOL');

        $this->assertTrue($berhasil);
        $this->assertTrue($terhapus);
    }

    /**
     * Galat LAIN tidak ikut ditelan.
     *
     * Menerjemahkan penolakan kunci asing tidak boleh berubah menjadi
     * menyembunyikan setiap kegagalan basis data. Yang bukan urusannya
     * dilempar terus ke atas.
     */
    public function test_it_never_swallows_a_different_database_error(): void
    {
        $this->expectException(QueryException::class);

        MasterDataDeletion::attempt(function (): void {
            \DB::select('select * from tabel_yang_tidak_pernah_ada');
        }, 'Gudang JONGGOL');
    }

    /**
     * Yang masih ditunjuk baris lain ditolak dengan kalimat, bukan galat
     * SQL mentah -- inilah skenario UTAMA kelas ini, dan sebelumnya tidak
     * pernah dibuktikan di sini sama sekali.
     *
     * SQLite (dipakai test) memberi kode galat BERBEDA dari MySQL
     * (produksi) untuk pelanggaran kunci asing yang SAMA -- 19, bukan
     * 1451. Dibuat langsung lewat tabel bertautan sungguhan, bukan
     * ditiru, supaya kode error yang diperiksa `isStillInUse()` benar-benar
     * yang dikembalikan driver, bukan yang dikarang.
     */
    public function test_it_refuses_with_a_sentence_when_something_still_points_at_it(): void
    {
        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON');
        \Illuminate\Support\Facades\DB::statement('create table induk_uji (id integer primary key)');
        \Illuminate\Support\Facades\DB::statement('create table anak_uji (id integer primary key, induk_id integer, foreign key(induk_id) references induk_uji(id))');
        \Illuminate\Support\Facades\DB::table('induk_uji')->insert(['id' => 1]);
        \Illuminate\Support\Facades\DB::table('anak_uji')->insert(['id' => 1, 'induk_id' => 1]);

        $berhasil = MasterDataDeletion::attempt(function (): void {
            \Illuminate\Support\Facades\DB::table('induk_uji')->where('id', 1)->delete();
        }, 'Gudang JONGGOL');

        $this->assertFalse($berhasil);
        $this->assertDatabaseHas('induk_uji', ['id' => 1]);

        \Filament\Notifications\Notification::assertNotified(
            __(':label cannot be deleted', ['label' => 'Gudang JONGGOL'])
        );
    }
}
