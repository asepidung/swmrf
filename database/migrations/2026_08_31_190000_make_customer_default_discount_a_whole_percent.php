<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diskon pelanggan disamakan menjadi persen bulat.
 *
 * Kolom ini semula `decimal(5,2)`, sementara `sales_order_items.discount`
 * yang menerimanya bertipe bilangan bulat. Ketidakcocokan itu tidak berhenti
 * pada pembulatan: penyimpanan Sales Order membuang titik desimalnya, bukan
 * membulatkannya, sehingga 2,5% berubah menjadi 25% dan 12,75% menjadi
 * 1275%.
 *
 * Keputusan Project Owner, 31 Agustus 2026: persen bulat saja. Hari ini
 * tidak ada yang hilang -- satu-satunya diskon yang berlaku adalah 2% untuk
 * tiga Distribution Center Lion Superindo. Kalau suatu saat dibutuhkan
 * diskon berkoma, KEDUA kolom harus dilebarkan bersamaan; melebarkan yang
 * satu saja mengembalikan persoalan yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('default_discount')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('default_discount', 5, 2)->default(0)->change();
        });
    }
};
