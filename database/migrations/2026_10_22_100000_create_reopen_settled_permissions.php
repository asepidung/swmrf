<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin untuk akunting membuka kembali invoice/hutang yang dianggap LUNAS (riwayat impor legacy tidak mencatat pelunasan,
 * jadi semuanya masuk lunas; beberapa transaksi pasti ada yang sebenarnya belum selesai). Tidak diberikan ke siapa pun otomatis:
 * Owner mencentangnya untuk peran akunting.
 */
return new class extends Migration
{
    private const IZIN = [
        ['reopen_settled_invoices', 'Invoices', 'Reopen settled invoices'],
        ['reopen_settled_payables', 'Payables', 'Reopen settled payables'],
    ];

    public function up(): void
    {
        foreach (self::IZIN as [$name, $module, $description]) {
            if (! DB::table('permissions')->where('name', $name)->exists()) {
                DB::table('permissions')->insert(['name' => $name, 'module_name' => $module, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun -- izin yang sudah dilekatkan ke pengguna ikut terlepas.
    }
};
