<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kategori pengiriman diberi nama TRACKING (Owner, 3 November 2026), menggantikan "ONGKOS KIRIM & EKSPEDISI" dari
     * migrasi sebelumnya. Hanya nama yang berubah; id dan barang yang memakainya tetap.
     */
    public function up(): void
    {
        $this->rename('ONGKOS KIRIM & EKSPEDISI', 'TRACKING');
    }

    public function down(): void
    {
        $this->rename('TRACKING', 'ONGKOS KIRIM & EKSPEDISI');
    }

    private function rename(string $from, string $to): void
    {
        if (DB::table('expense_categories')->where('name', $to)->exists()) {
            return;
        }

        DB::table('expense_categories')->whereRaw('TRIM(name) = ?', [$from])->update(['name' => $to, 'updated_at' => now()]);
    }
};
