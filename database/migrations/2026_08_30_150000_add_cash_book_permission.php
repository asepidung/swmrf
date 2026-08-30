<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permission modul Buku Kas disisipkan lewat MIGRASI, bukan lewat seeder.
 *
 * `DatabaseSeeder` memang tempat resmi permission didaftarkan, dan
 * `view_cash_book` juga ditambahkan di sana untuk lingkungan yang dibangun
 * dari nol. Tetapi seeder itu TIDAK BOLEH dijalankan di server yang sudah
 * hidup: ia menyetel ulang password akun `saepullrock` menjadi '1234' tanpa
 * syarat, sehingga setiap kali dijalankan pemiliknya terlempar ke alur
 * penggantian password.
 *
 * Deploy menjalankan `php artisan migrate --force`, jadi menaruhnya di sini
 * membuat permission-nya sampai ke server tanpa efek samping apa pun.
 *
 * `updateOrInsert` dipakai supaya migrasi ini aman diulang dan tidak bentrok
 * dengan seeder yang mendaftarkan permission yang sama. Sintaksnya didukung
 * MySQL maupun SQLite -- testing berjalan di SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'view_cash_book'],
            [
                'module_name' => 'Cash Book',
                'description' => 'View cash book',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'view_cash_book')->delete();
    }
};
