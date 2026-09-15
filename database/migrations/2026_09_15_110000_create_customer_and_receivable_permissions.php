<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin Customer/CustomerGroup/CustomerSegment dan view_receivables --
 * SATU migrasi untuk semua yang kurang, atas arahan Hafizh.
 *
 * Kedua belas izin Customer sudah ada di `DatabaseSeeder`, tapi seeder itu
 * TIDAK BOLEH dijalankan di server -- ia mengatur ulang kata sandi superuser.
 * Izin yang hanya lahir di seeder karena itu tidak akan pernah sampai ke
 * hosting: cluster Customers efektif terkunci total dari semua employee.
 * Sama untuk `view_receivables`, satu-satunya izin yang menentukan apakah
 * seseorang bisa melihat modul Piutang sama sekali -- pola persis bug Fleet
 * yang sudah diperbaiki 13 September.
 *
 * Ditemukan lewat penyisiran modul Customers dan Receivable, 15 September
 * 2026.
 */
return new class extends Migration
{
    private const IZIN = [
        ['view_customers', 'Customers', 'View customers'],
        ['create_customers', 'Customers', 'Create customers'],
        ['edit_customers', 'Customers', 'Edit customers'],
        ['delete_customers', 'Customers', 'Delete customers'],
        ['view_customer_groups', 'Customer Groups', 'View customer groups'],
        ['create_customer_groups', 'Customer Groups', 'Create customer groups'],
        ['edit_customer_groups', 'Customer Groups', 'Edit customer groups'],
        ['delete_customer_groups', 'Customer Groups', 'Delete customer groups'],
        ['view_customer_segments', 'Customer Segments', 'View customer segments'],
        ['create_customer_segments', 'Customer Segments', 'Create customer segments'],
        ['edit_customer_segments', 'Customer Segments', 'Edit customer segments'],
        ['delete_customer_segments', 'Customer Segments', 'Delete customer segments'],
        ['view_receivables', 'Receivables', 'View receivables'],
    ];

    public function up(): void
    {
        foreach (self::IZIN as [$name, $module, $description]) {
            if (DB::table('permissions')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('permissions')->insert([
                'name' => $name,
                'module_name' => $module,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun -- izin yang sudah dilekatkan ke
        // pengguna akan ikut terlepas.
    }
};
