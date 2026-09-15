<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin baru: siapa yang boleh mengubah izin akun ORANG LAIN.
 *
 * `edit_users` sebelumnya menanggung dua hal sekaligus -- mengelola data &
 * status aktif akun, DAN mencentang/mencabut hak akses modul lain lewat form
 * yang sama. Siapa pun yang bisa mengedit user otomatis bisa mengangkat
 * dirinya sendiri (atau siapa pun) ke akses penuh, tanpa jejak keputusan yang
 * berbeda dari "mengubah nama".
 *
 * Izin ini SENGAJA tidak diberikan ke siapa pun di sini -- Ayah yang akan
 * mencentangnya secara manual lewat form Hak Akses. Tanpa izin ini, form
 * Edit User tidak menampilkan checkbox izin sama sekali, dan `sync()`-nya
 * tidak dijalankan; `edit_users` saja tinggal mengelola data & status aktif.
 * Dicatat di `.agents/tertunda.md` §G.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('permissions')->where('name', 'manage_user_permissions')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'manage_user_permissions',
            'module_name' => 'Users',
            'description' => 'Manage other users\' permissions',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun -- kalau sudah dilekatkan ke
        // pengguna, penghapusan lewat migrasi akan melepasnya diam-diam.
    }
};
