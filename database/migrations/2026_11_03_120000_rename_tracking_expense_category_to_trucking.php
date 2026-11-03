<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "TRACKING" (pelacakan) diganti TRUCKING (angkutan truk), sebutan lapangan "uang treking" (Owner, 3 November
     * 2026). Hanya nama; id dan barang yang memakainya tetap.
     */
    public function up(): void
    {
        $this->rename('TRACKING', 'TRUCKING');
    }

    public function down(): void
    {
        $this->rename('TRUCKING', 'TRACKING');
    }

    private function rename(string $from, string $to): void
    {
        if (DB::table('expense_categories')->where('name', $to)->exists()) {
            return;
        }

        DB::table('expense_categories')->whereRaw('TRIM(name) = ?', [$from])->update(['name' => $to, 'updated_at' => now()]);
    }
};
