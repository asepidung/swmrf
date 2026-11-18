<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus Pemasok, Material, Produk, atau Grup Pelanggan tidak boleh ikut
 * menghapus data anaknya diam-diam (keputusan Owner, FK-01). Induk ini tidak
 * soft-delete, jadi "hapus" berarti permanen. Kunci asing anak yang tadinya
 * CASCADE sekarang RESTRICT: basis data menolak selama masih ada anaknya.
 *
 * Yang sengaja TETAP CASCADE: product_materials (resep BOM milik produk, ada tesnya),
 * costing_price_defaults dan customer_product_specs (pengaturan milik produk, bukan riwayat).
 */
return new class extends Migration
{
    /** [tabel anak, kolom, tabel induk] */
    private const FKS = [
        ['supplier_payments', 'supplier_id', 'suppliers'],
        ['cattle_receivings', 'supplier_id', 'suppliers'],
        ['material_requisition_items', 'material_id', 'materials'],
        ['material_stocks', 'material_id', 'materials'],
        ['material_stock_take_items', 'material_id', 'materials'],
        ['material_findings', 'material_id', 'materials'],
        ['products', 'parent_id', 'products'],
        ['product_requisition_items', 'product_id', 'products'],
        ['price_lists', 'customer_group_id', 'customer_groups'],
    ];

    public function up(): void
    {
        $this->ubah('restrictOnDelete');
    }

    public function down(): void
    {
        $this->ubah('cascadeOnDelete');
    }

    private function ubah(string $aksi): void
    {
        foreach (self::FKS as [$tabel, $kolom, $induk]) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, $kolom)) {
                continue;
            }

            Schema::table($tabel, fn (Blueprint $t) => $t->dropForeign([$kolom]));
            Schema::table($tabel, fn (Blueprint $t) => $t->foreign($kolom)->references('id')->on($induk)->{$aksi}());
        }
    }
};
