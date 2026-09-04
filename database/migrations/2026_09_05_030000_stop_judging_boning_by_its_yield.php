<?php

use App\Models\Permission;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Susut boning TIDAK dihitung. Keputusan Project Owner, 5 September 2026.
 *
 * Alasannya ada pada bentuk pekerjaannya sendiri, bukan pada malasnya
 * mengukur. Kulit dan offal diberi label DI DALAM dokumen boning yang sama --
 * itu satu-satunya pintu agar keduanya punya stok, karena kontraktor
 * mengambilnya hari itu juga dan untuk itu dibutuhkan DO, yang butuh Sales
 * Order, yang butuh Tally, yang butuh STOK.
 *
 * Akibatnya hasil sebuah boning memuat barang yang tidak berasal dari
 * karkasnya:
 *
 *     bahan  = karkas + buntut
 *     hasil  = daging + offal + kulit
 *
 * dan tiap batch akan terbaca hasilnya jauh melebihi bahannya. Alarm palsu
 * pada SETIAP dokumen, yang justru mengajari orang mengabaikan alarm.
 *
 * Bisa saja diperbaiki dengan menandai produk mana yang by-product karkas,
 * tetapi itu menambah satu daftar yang harus dirawat manusia demi angka yang
 * -- kata Owner -- memang tidak dibutuhkan. Jadi yang dicabut fiturnya.
 *
 * YANG TIDAK IKUT DICABUT: rendemen karkas (`Carcass::yieldPercent()`), yaitu
 * berapa persen bobot hidup yang menjadi karkas. Ia tidak menyentuh hasil
 * boning sama sekali, sudah ada di aplikasi lama, dan hilangnya adalah
 * regresi. Begitu pula `Boning::lock()`/`unlock()` yang mengumpulkan syarat
 * penguncian ke satu tempat.
 *
 * Kolomnya DIHAPUS, tidak dibiarkan menganggur. Kolom mati yang masih berdiri
 * adalah jebakan yang diam -- pelajaran dari `invoices.additional_charges`
 * yang baru dibuang hari ini juga.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'set_boning_yield_limit',
        'override_boning_yield',
    ];

    public function up(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)->delete();
        Setting::where('key', 'boning.max_shrink_percent')->delete();

        Schema::table('bonings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('yield_override_by');
            $table->dropColumn(['yield_override_reason', 'yield_override_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bonings', function (Blueprint $table) {
            $table->text('yield_override_reason')->nullable()->after('note');
            $table->foreignId('yield_override_by')->nullable()->after('yield_override_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('yield_override_at')->nullable()->after('yield_override_by');
        });
    }
};
