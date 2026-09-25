<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Assign dummy supplier to existing null records to prevent constraint errors
        $firstSupplier = DB::table('suppliers')->first();
        if ($firstSupplier) {
            DB::table('product_requisitions')->whereNull('supplier_id')->update(['supplier_id' => $firstSupplier->id]);
            DB::table('material_requisitions')->whereNull('supplier_id')->update(['supplier_id' => $firstSupplier->id]);
        } else {
            DB::table('product_requisitions')->whereNull('supplier_id')->delete();
            DB::table('material_requisitions')->whereNull('supplier_id')->delete();
        }

        // 2. Modify columns to NOT NULL for product_requisitions
        Schema::table('product_requisitions', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->unsignedBigInteger('supplier_id')->nullable(false)->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
        });

        // 3. Modify columns to NOT NULL for material_requisitions
        Schema::table('material_requisitions', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->unsignedBigInteger('supplier_id')->nullable(false)->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_requisitions', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->unsignedBigInteger('supplier_id')->nullable()->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });

        Schema::table('material_requisitions', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->unsignedBigInteger('supplier_id')->nullable()->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }
};
