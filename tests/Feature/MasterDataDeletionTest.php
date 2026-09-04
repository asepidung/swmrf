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
}
