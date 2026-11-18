<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Audit pra-rilis 14 November 2026, I-01 dan PF-02: pencarian barcode tidak boleh memindai seluruh tabel. */
class BarcodeLookupIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_barcode_lookups_have_their_own_index(): void
    {
        $this->assertTrue(Schema::hasIndex('tally_items', ['barcode']));
        $this->assertTrue(Schema::hasIndex('tally_items', ['original_barcode']));
        $this->assertTrue(Schema::hasIndex('repack_materials', ['barcode']));
        $this->assertTrue(Schema::hasIndex('beef_stock_movements', ['barcode']));
        $this->assertTrue(Schema::hasIndex('beef_stock_movements', ['reference_document']));
        $this->assertTrue(Schema::hasIndex('beef_stock_movements', ['created_at']));
    }
}
