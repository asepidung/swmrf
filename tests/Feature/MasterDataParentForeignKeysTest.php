<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * FK-01: menghapus Pemasok/Material/Produk/Grup Pelanggan (tanpa soft delete) tidak boleh
 * menghapus anaknya diam-diam; kunci asingnya harus RESTRICT, bukan CASCADE.
 */
class MasterDataParentForeignKeysTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function parent_foreign_keys_restrict_instead_of_cascade()
    {
        $daftar = [
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

        foreach ($daftar as [$tabel, $kolom, $induk]) {
            $fk = collect(Schema::getForeignKeys($tabel))
                ->first(fn ($f) => $f['columns'] === [$kolom] && $f['foreign_table'] === $induk);

            $this->assertNotNull($fk, "FK $tabel.$kolom -> $induk tidak ada");
            $this->assertNotSame('cascade', $fk['on_delete'], "$tabel.$kolom masih CASCADE");
        }
    }
}
