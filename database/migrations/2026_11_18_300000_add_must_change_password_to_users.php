<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda "wajib ganti kata sandi" (audit pra-rilis 14 November 2026, A-03; keputusan Ayah 18 November 2026).
 * Reset kata sandi kini menghasilkan kata sandi sementara ACAK, jadi pemaksaan ganti tidak bisa lagi mengandalkan
 * `Hash::check('1234')`; ia memakai kolom ini. Akun yang masih memakai 1234 tetap dipaksa seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'must_change_password')) {
            return;
        }

        Schema::table('users', fn (Blueprint $t) => $t->boolean('must_change_password')->default(false));
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('must_change_password'));
        }
    }
};
