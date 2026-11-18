<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Keputusan Ayah (18 November 2026): akun yang kata sandinya MASIH bawaan lama "1234" (nonaktif, atau aktif tetapi
 * belum pernah mengganti/masuk) diganti kata sandi ACAK yang tidak diketahui siapa pun dan tidak ditampilkan.
 * Admin memberi kata sandi sementara lewat Reset Kata Sandi bila orangnya memang akan masuk.
 * Sekali jalan; migrasi ini tidak dapat dibalikkan (kata sandi lama sengaja dibuang).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->select('id', 'password')->orderBy('id')->each(function ($user) {
            if ($user->password && Hash::check('1234', $user->password)) {
                DB::table('users')->where('id', $user->id)->update([
                    'password' => Hash::make(Str::random(40)),
                    'must_change_password' => true,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Sengaja kosong: kata sandi 1234 tidak dipulihkan.
    }
};
