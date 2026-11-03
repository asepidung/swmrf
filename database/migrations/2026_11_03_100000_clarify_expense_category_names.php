<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nama kategori pengeluaran dijernihkan (Owner, 3 November 2026: "namanya masih rancu"). Yang diubah hanya
     * nama; id, status, dan barang yang sudah memakainya tidak tersentuh. Dicocokkan dengan nama lama persis
     * (tanpa spasi di ujung), jadi aman dijalankan di database yang kategorinya sudah diubah atau kosong.
     *
     * @var array<string, string>
     */
    private const NAMES = [
        'PENGIRIMAN & PENGANGKUTAN' => 'ONGKOS KIRIM & EKSPEDISI',
        'BBM, TOL & PARKIR' => 'BBM, TOL & PARKIR KENDARAAN',
        'PERAWATAN KENDARAAN' => 'SERVIS & SPAREPART KENDARAAN',
        'MAKAN & KONSUMSI' => 'MAKAN & MINUM (RAPAT/TAMU)',
        'TUNJANGAN MAKAN LEMBUR' => 'UANG MAKAN LEMBUR',
        'PERLENGKAPAN PRODUKSI' => 'BAHAN & PERLENGKAPAN PRODUKSI',
        'KEBERSIHAN & RUMAH TANGGA' => 'KEBERSIHAN & RUMAH TANGGA KANTOR',
        'PERLENGKAPAN KANTOR (ATK)' => 'ATK & PERLENGKAPAN KANTOR',
        'KOMUNIKASI, INTERNET & IT' => 'INTERNET, TELEPON & PERANGKAT IT',
        'PENGOBATAN' => 'PENGOBATAN & KESEHATAN KARYAWAN',
        'PERBAIKAN & PEMELIHARAAN' => 'PERBAIKAN GEDUNG, MESIN & PERALATAN',
        'OPERASIONAL PERJALANAN DINAS' => 'PERJALANAN DINAS',
        'PROMOSI & PERCETAKAN' => 'PROMOSI, IKLAN & PERCETAKAN',
        'ASET & BARANG MODAL' => 'PEMBELIAN ASET & BARANG MODAL',
        'OPERASIONAL PULSA MARKETING' => 'PULSA & KUOTA MARKETING',
    ];

    public function up(): void
    {
        $this->rename(self::NAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::NAMES));
    }

    /** @param array<string, string> $map */
    private function rename(array $map): void
    {
        foreach ($map as $from => $to) {
            if (DB::table('expense_categories')->where('name', $to)->exists()) {
                continue;
            }

            DB::table('expense_categories')
                ->whereRaw('TRIM(name) = ?', [$from])
                ->update(['name' => $to, 'updated_at' => now()]);
        }
    }
};
