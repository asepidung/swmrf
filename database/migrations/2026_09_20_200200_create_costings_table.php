<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesin HPP (issue #480). Satu costing per boning (`hpp.md` §2, §6, §8) --
 * lot untuk costing adalah dokumen CARCASS, tapi baris penghitungannya
 * lahir sesudah boning (barulah ada kg per produk). Boning yang memuat
 * lebih dari satu carcass menjumlahkan biaya beli dari semuanya (dicatat
 * di `costing_cattle`, bukan disatukan diam-diam).
 *
 * Harga DIKUNCI saat costing dibuat (`hpp.md` §6) -- seluruh angka di sini
 * dan di `costing_items`/`costing_cattle` adalah SALINAN, bukan rujukan ke
 * `price_list_items`/`purchase_cattle_items` yang bisa berubah di kemudian
 * hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costings', function (Blueprint $table) {
            $table->id();
            $table->string('costing_number')->unique();
            $table->date('costing_date');
            $table->foreignId('boning_id')->unique()->constrained('bonings')->restrictOnDelete();
            $table->decimal('purchase_cost', 15, 2)->default(0);
            $table->decimal('total_sales_value', 15, 2)->default(0);
            // Presisi tinggi -- k dipakai mengalikan setiap HPP per kg
            // (hpp.md §2), pembulatan dini di sini akan menggeser SELURUH
            // produk sekaligus.
            $table->decimal('ratio_k', 12, 6)->default(0);
            $table->decimal('overhead_per_kg', 12, 2)->default(0);
            $table->decimal('total_kg', 12, 2)->default(0);
            // Laba = total nilai jual - biaya beli - (overhead x total kg).
            // Overhead cuma memotong laba di sini, TIDAK masuk HPP produk
            // (hpp.md §9, §15 butir 2).
            $table->decimal('profit', 15, 2)->default(0);
            $table->string('status')->default('Draft'); // Draft | Locked
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costings');
    }
};
