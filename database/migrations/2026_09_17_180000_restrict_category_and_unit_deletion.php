<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus Material Category, Material Unit, atau Product Category yang
 * masih dipakai TIDAK BOLEH ikut menghapus Material/Product-nya.
 *
 * Ketiga FK ini sebelumnya cascadeOnDelete() -- warisan dari saat tabelnya
 * pertama dibuat, sebelum ada MasterDataDeletion::attempt(). Perbaikan
 * hapus-ramah untuk ketiga Resource ini (komentarnya menyebut "sama seperti
 * EditWarehouse") disalin dari Warehouse/CattleClass, yang FK anaknya memang
 * restrict -- tapi tidak ada yang memeriksa FK materials/products-nya
 * sendiri. Akibatnya `delete()` "berhasil" tanpa Exception apa pun sambil
 * diam-diam menghapus PERMANEN setiap Material/Product yang masih memakai
 * kategori/satuan itu (Material dan Product tidak soft-delete).
 *
 * Owner: menghapus kategori tidak boleh berarti menghapus produknya diam-
 * diam -- konsisten dengan Warehouse, CattleClass, dan keputusan CustomerGroup
 * di batch awal. TOLAK, dengan pesan ramah (lihat guard `deleting()` di
 * MaterialCategory/MaterialUnit/ProductCategory dan MasterDataDeletion di
 * halaman Edit/index).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['material_category_id']);
            $table->dropForeign(['material_unit_id']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreign('material_category_id')
                ->references('id')->on('material_categories')
                ->restrictOnDelete();
            $table->foreign('material_unit_id')
                ->references('id')->on('material_units')
                ->restrictOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')->on('product_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['material_category_id']);
            $table->dropForeign(['material_unit_id']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreign('material_category_id')
                ->references('id')->on('material_categories')
                ->cascadeOnDelete();
            $table->foreign('material_unit_id')
                ->references('id')->on('material_units')
                ->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')->on('product_categories')
                ->cascadeOnDelete();
        });
    }
};
