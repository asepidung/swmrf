<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus pengguna tidak boleh ikut menghapus dokumen bisnisnya.
 *
 * Tiga kunci asing ke `users` memakai CASCADE:
 *
 *     material_requisitions.user_id
 *     product_requisitions.user_id
 *     material_findings.created_by
 *
 * Artinya menghapus satu pengguna akan ikut menghapus permintaan bahan dan
 * permintaan produk yang pernah ia buat -- tanpa satu pun peringatan, dan
 * tanpa kemungkinan dipulihkan karena `users` tidak memakai hapus lunak. Saat
 * migrasi ini dibuat ada 5 permintaan bahan dan 3 permintaan produk yang
 * berdiri di atas kunci itu.
 *
 * Project Owner sudah memutuskan pengguna TIDAK BISA DIHAPUS sama sekali --
 * dinonaktifkan saja. Jadi jalur ini seharusnya tidak pernah dilalui lagi.
 *
 * Perubahan ini tetap dikerjakan justru KARENA itu: penjagaan di policy bisa
 * dilepas orang berikutnya yang tidak tahu apa yang menunggu di baliknya,
 * sementara kunci asing yang RESTRICT menolak dengan sendirinya, di lapisan
 * yang tidak bisa dilewati kode aplikasi mana pun.
 */
return new class extends Migration
{
    private const KUNCI = [
        ['tabel' => 'material_requisitions', 'kolom' => 'user_id'],
        ['tabel' => 'product_requisitions', 'kolom' => 'user_id'],
        ['tabel' => 'material_findings', 'kolom' => 'created_by'],
    ];

    public function up(): void
    {
        foreach (self::KUNCI as $kunci) {
            $this->pasangUlang($kunci['tabel'], $kunci['kolom'], 'restrict');
        }
    }

    public function down(): void
    {
        foreach (self::KUNCI as $kunci) {
            $this->pasangUlang($kunci['tabel'], $kunci['kolom'], 'cascade');
        }
    }

    private function pasangUlang(string $tabel, string $kolom, string $aksi): void
    {
        // SQLite tidak bisa mengubah kunci asing di tempat, dan basis data
        // pengujian memang SQLite. Di sana kuncinya memang tidak ditegakkan
        // dengan cara yang sama, jadi tidak ada yang perlu dikerjakan.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table($tabel, function (Blueprint $table) use ($tabel, $kolom, $aksi) {
            $table->dropForeign($tabel.'_'.$kolom.'_foreign');
            $table->foreign($kolom)->references('id')->on('users')->onDelete($aksi);
        });
    }
};
