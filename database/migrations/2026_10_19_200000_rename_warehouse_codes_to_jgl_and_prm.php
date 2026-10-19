<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kode gudang diperpendek (keputusan Owner, 19 Oktober 2026): JONGGOL -> JGL, PERUM -> PRM. Nama gudang tidak berubah.
 *
 * Kode tidak dipakai di nomor dokumen maupun barcode (hanya nama dan id), jadi tidak ada data lain yang ikut berubah.
 * Aman dijalankan ulang: hanya mengganti bila kode lama ada dan kode baru belum dipakai gudang lain.
 */
return new class extends Migration
{
    private const CODES = ['JONGGOL' => 'JGL', 'PERUM' => 'PRM'];

    public function up(): void
    {
        $this->rename(self::CODES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::CODES));
    }

    /** @param  array<string, string>  $map */
    private function rename(array $map): void
    {
        foreach ($map as $from => $to) {
            if (DB::table('warehouses')->where('code', $to)->exists()) {
                continue;
            }

            DB::table('warehouses')->where('code', $from)->update(['code' => $to, 'updated_at' => now()]);
        }
    }
};
