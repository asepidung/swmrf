<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menembus ambang susut boleh, TANPA JEJAK tidak.
 *
 * Kalau susut sebuah Repack melewati batas wajar yang disetel QC, dokumennya
 * tidak dibuang -- ia menunggu orang yang berwenang. Yang berwenang itu boleh
 * menguncinya, tetapi wajib menuliskan alasannya, dan alasan itu tersimpan
 * bersama namanya dan waktunya.
 *
 * Inilah yang membuat bentuk ini tidak jatuh menjadi "peringatan yang
 * diabaikan": penembusan meninggalkan bekas, dan penembusan yang sering
 * terbaca sebagai pola -- entah ambangnya yang terlalu ketat, atau ada yang
 * salah di lapangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repacks', function (Blueprint $table) {
            $table->text('yield_override_reason')->nullable()->after('note');
            $table->foreignId('yield_override_by')->nullable()->after('yield_override_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('yield_override_at')->nullable()->after('yield_override_by');
        });
    }

    public function down(): void
    {
        Schema::table('repacks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('yield_override_by');
            $table->dropColumn(['yield_override_reason', 'yield_override_at']);
        });
    }
};
