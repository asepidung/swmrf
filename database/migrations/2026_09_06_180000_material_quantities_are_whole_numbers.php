<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qty material disimpan BULAT, sama seperti yang ditampilkan.
 *
 * Keputusan Owner, 5 September 2026: "material itu gak ada qty koma-komaan".
 * Dipertegas 6 September: "barang pasti seperti plastik karton dan
 * lain-lain" -- dihitung per lembar, per pcs, per roll. Tidak ada setengah
 * karton.
 *
 * Sebelas kolom masih `decimal` padahal aturannya sudah lama begitu, dan
 * layarnya sudah setengah mengikuti: `goods_receipt_material_items.qty_received`
 * bahkan SUDAH `int` sejak awal, tetapi tiga tempat menampilkannya dengan dua
 * angka di belakang koma -- nol palsu yang mengajak orang mengetik koma yang
 * tidak akan pernah tersimpan.
 *
 * Seluruh nilainya diperiksa lebih dulu di basis data lokal MAUPUN server:
 * sebelas kolom, tidak satu pun berisi angka berkoma. Tidak ada yang
 * dibulatkan diam-diam oleh migrasi ini.
 *
 * **Bentuk tiap kolom dipertahankan apa adanya, satu per satu.** Yang berubah
 * HANYA tipenya. Percobaan pertama migrasi ini menyeragamkan semuanya menjadi
 * `default(0)` dan bukan-null, dan itu menghapus perbedaan yang justru
 * penting: `physical_qty` dan `difference_qty` boleh KOSONG, dan kosong di
 * sana berarti "belum dihitung" -- bukan "sudah dihitung, hasilnya nol". Dua
 * keadaan yang sangat berbeda di tengah opname berjalan.
 *
 * Tanda tangannya juga berbeda dengan sengaja. Qty pesanan tidak boleh
 * negatif dan sudah dijaga aturan `> 0` di form-nya. Kolom buku besar dan
 * selisih opname JUSTRU harus bisa negatif: `difference_qty` negatif berarti
 * fisiknya kurang dari catatan, dan `balance` negatif -- yang tiga barisnya
 * masih ada di server dari sebelum penolakan stok minus dipasang -- adalah
 * keadaan yang harus bisa DILIHAT dan dilaporkan `stock:reconcile-material`,
 * bukan yang membuat penyimpanannya gagal dengan galat SQL tanpa keterangan.
 */
return new class extends Migration
{
    /**
     * [tabel, kolom, boleh negatif, boleh kosong, bawaan]
     *
     * Bawaan `null` berarti kolomnya memang tidak punya nilai bawaan.
     */
    private const KOLOM = [
        ['material_requisition_items', 'qty', false, false, 0],
        ['purchase_material_items', 'qty', false, false, null],

        ['material_stocks', 'qty', true, false, 0],
        ['material_stock_movements', 'qty_in', true, false, 0],
        ['material_stock_movements', 'qty_out', true, false, 0],
        ['material_stock_movements', 'balance', true, false, null],

        ['material_stock_take_items', 'system_qty', true, false, 0],
        ['material_stock_take_items', 'physical_qty', true, true, null],
        ['material_stock_take_items', 'difference_qty', true, true, null],

        ['material_findings', 'qty', true, false, null],
        ['material_usages', 'qty', true, false, null],
    ];

    public function up(): void
    {
        $this->tanpaTampilanYangBergantung(function (): void {
            foreach (self::KOLOM as [$tabel, $kolom, $negatif, $kosong, $bawaan]) {
                Schema::table($tabel, function (Blueprint $table) use ($kolom, $negatif, $kosong, $bawaan) {
                    $ubah = $negatif
                        ? $table->integer($kolom)
                        : $table->unsignedInteger($kolom);

                    if ($kosong) {
                        $ubah->nullable();
                    }

                    if ($bawaan !== null) {
                        $ubah->default($bawaan);
                    }

                    $ubah->change();
                });
            }
        });
    }

    public function down(): void
    {
        $this->tanpaTampilanYangBergantung(function (): void {
            foreach (self::KOLOM as [$tabel, $kolom, , $kosong, $bawaan]) {
                Schema::table($tabel, function (Blueprint $table) use ($kolom, $kosong, $bawaan) {
                    $ubah = $table->decimal($kolom, 15, 2);

                    if ($kosong) {
                        $ubah->nullable();
                    }

                    if ($bawaan !== null) {
                        $ubah->default($bawaan);
                    }

                    $ubah->change();
                });
            }
        });
    }

    /**
     * Menjalankan perubahan kolom tanpa ditolak tampilan yang bergantung.
     *
     * SQLite tidak bisa mengubah tipe kolom di tempat: ia MEMBANGUN ULANG
     * tabelnya lewat tabel sementara, lalu menamainya kembali. Penamaan itu
     * ditolak selama masih ada VIEW yang menyebut tabel tersebut --
     * `material_usage_headers` menyebut `material_usages`.
     *
     * MySQL tidak punya persoalan ini: `ALTER TABLE ... MODIFY` mengubah
     * kolomnya di tempat dan tidak menyentuh view mana pun.
     *
     * Definisi view-nya TIDAK ditulis ulang di sini. Ia dibaca dari basis
     * data apa adanya sesaat sebelum dibuang, lalu dipasang kembali persis
     * seperti semula. Menyalin definisinya ke berkas ini akan melahirkan
     * salinan kedua yang diam-diam berbeda begitu view-nya diubah.
     */
    private function tanpaTampilanYangBergantung(\Closure $kerja): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $kerja();

            return;
        }

        $tabel = array_unique(array_map(
            fn (array $satu): string => $satu[0],
            self::KOLOM,
        ));

        $tampilan = DB::table('sqlite_master')
            ->where('type', 'view')
            ->whereNotNull('sql')
            ->get()
            ->filter(fn ($satu): bool => (bool) array_filter(
                $tabel,
                fn (string $nama): bool => str_contains($satu->sql, $nama),
            ));

        foreach ($tampilan as $satu) {
            DB::statement('DROP VIEW IF EXISTS '.$satu->name);
        }

        try {
            $kerja();
        } finally {
            foreach ($tampilan as $satu) {
                DB::statement($satu->sql);
            }
        }
    }
};
