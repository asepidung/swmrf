<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Tests\TestCase;

/** TX-01: simpan dokumen induk-anak di panel dibungkus satu transaksi (kepala tanpa baris tidak boleh tersimpan). */
class PanelDatabaseTransactionsTest extends TestCase
{
    /** @test */
    public function the_admin_panel_wraps_create_and_save_in_a_transaction(): void
    {
        $this->assertTrue(Filament::getPanel('admin')->hasDatabaseTransactions());
    }
}
