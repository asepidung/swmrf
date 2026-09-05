<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin yang hanya pernah hidup di `DatabaseSeeder`, dan karena itu tidak
 * pernah sampai ke sistem yang berjalan.
 *
 * `DatabaseSeeder` mengatur ulang kata sandi superuser, sehingga ia TIDAK
 * BOLEH dijalankan di server. Akibatnya setiap izin yang ditambahkan ke
 * seeder SESUDAH penyemaian pertama tidak akan pernah ada di sana: barisnya
 * tertulis rapi di kode, tetapi tabel `permissions` tidak memuatnya, sehingga
 * izin itu tidak muncul di form Hak Akses dan tidak bisa dicentang siapa pun.
 *
 * Gejalanya tidak pernah terlihat sebagai error. Yang terjadi hanya: sebuah
 * tombol tidak pernah muncul, dan tidak ada yang tahu kenapa. Dua belas izin
 * berada dalam keadaan itu -- termasuk `record_payable_compensations`, yang
 * sudah pernah diminta untuk dicentang tetapi memang tidak ada wujudnya.
 *
 * `delete_beef_stocks` ikut dibuat di sini. Ia disebut oleh tombol hapus stok
 * di Stock Overview dan oleh `BeefStockPolicy`, tetapi tidak pernah ada di
 * seeder MAUPUN di migrasi mana pun -- jadi selama ini hanya akun programmer
 * yang bisa menghapus stok, dan tidak ada cara memberikan hak itu kepada
 * orang gudang. Keputusan Owner: fiturnya memang dibutuhkan (barang tercatat
 * tetapi fisiknya tidak ada), dengan hak akses tersendiri.
 *
 * Dijalankan dengan `firstOrCreate`, jadi aman diulang dan aman di basis data
 * yang sudah memilikinya.
 */
return new class extends Migration
{
    private const IZIN = [
        ['pay_purchase_products', 'PO Beef', 'Pay down payment on PO beef'],
        ['pay_payables', 'Payables', 'Pay payables'],
        ['record_payable_compensations', 'Payables', 'Record supplier compensation on a payable'],
        ['receive_receivables', 'Receivables', 'Receive payment for receivables'],
        ['view_grades', 'Grades', 'View grades'],
        ['create_grades', 'Grades', 'Create grades'],
        ['edit_grades', 'Grades', 'Edit grades'],
        ['delete_grades', 'Grades', 'Delete grades'],
        ['view_warehouses', 'Warehouses', 'View warehouses'],
        ['create_warehouses', 'Warehouses', 'Create warehouses'],
        ['edit_warehouses', 'Warehouses', 'Edit warehouses'],
        ['delete_warehouses', 'Warehouses', 'Delete warehouses'],
        ['delete_beef_stocks', 'Beef Stocks', 'Delete beef stocks'],
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
        // Sengaja tidak menghapus apa pun.
        //
        // Izin-izin ini sudah bisa dilekatkan ke pengguna begitu ada di sini.
        // Menghapusnya saat rollback akan ikut memutus lekatannya, dan yang
        // hilang bukan barisnya saja melainkan hak akses orang yang sudah
        // terlanjur diberi.
    }
};
