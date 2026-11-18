<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk pencarian yang sering dan sebelumnya memindai seluruh tabel:
 * penanda R/T (cari barcode di repack_materials dan tally_items), kartu stok
 * (cari barcode / nomor dokumen / tanggal di beef_stock_movements).
 *
 * `tally_items` hanya punya unik (tally_id, barcode), yang tidak membantu
 * pencarian dengan barcode saja.
 */
return new class extends Migration
{
    /** @return array<string, array<string, list<string>>> tabel => [nama index => kolom] */
    private function indexes(): array
    {
        return [
            'tally_items' => [
                'tally_items_barcode_index' => ['barcode'],
                'tally_items_original_barcode_index' => ['original_barcode'],
            ],
            'repack_materials' => [
                'repack_materials_barcode_index' => ['barcode'],
            ],
            'beef_stock_movements' => [
                'beef_stock_movements_barcode_index' => ['barcode'],
                'beef_stock_movements_reference_document_index' => ['reference_document'],
                'beef_stock_movements_created_at_index' => ['created_at'],
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->indexes() as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (Schema::hasIndex($table, $name)) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes() as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
