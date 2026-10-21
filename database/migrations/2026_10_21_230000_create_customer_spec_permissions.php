<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin sendiri untuk spek customer (#579), setelah menunya pindah ke grup QC
 * (permintaan Owner, 21 Oktober 2026): `view_customer_specs` (Lihat spek
 * customer) dan `manage_customer_specs` (Kelola spek customer).
 *
 * Sebelumnya spek meminjam izin Sales Order/Tally. Agar tidak ada yang tiba-tiba
 * kehilangan akses, pemegang izin lama diberi izin baru yang setara: lihat
 * untuk yang boleh melihat SO/Tally, kelola untuk yang boleh membuat/mengubah SO.
 * Selanjutnya Owner mengatur lewat daftar izin pengguna.
 */
return new class extends Migration
{
    private const IZIN = [
        ['view_customer_specs', 'Customer Specs', 'View customer specs'],
        ['manage_customer_specs', 'Customer Specs', 'Manage customer specs'],
    ];

    public function up(): void
    {
        foreach (self::IZIN as [$name, $module, $description]) {
            if (! DB::table('permissions')->where('name', $name)->exists()) {
                DB::table('permissions')->insert(['name' => $name, 'module_name' => $module, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $id = fn (string $name) => DB::table('permissions')->where('name', $name)->value('id');
        $holders = fn (array $names) => DB::table('user_permissions')->whereIn('permission_id', DB::table('permissions')->whereIn('name', $names)->pluck('id'))->pluck('user_id')->unique();

        foreach ([
            'view_customer_specs' => $holders(['view_sales_orders', 'view_tallies']),
            'manage_customer_specs' => $holders(['create_sales_orders', 'edit_sales_orders']),
        ] as $name => $users) {
            $permissionId = $id($name);

            foreach ($users as $userId) {
                if (! DB::table('user_permissions')->where(['user_id' => $userId, 'permission_id' => $permissionId])->exists()) {
                    DB::table('user_permissions')->insert(['user_id' => $userId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun -- izin yang sudah dilekatkan ke pengguna ikut terlepas.
    }
};
