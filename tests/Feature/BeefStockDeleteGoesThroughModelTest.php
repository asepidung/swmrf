<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * FZ-01: pembekuan gudang saat opname dijaga lewat event model `BeefStock` (`deleting`). Hapus lewat kueri massal
 * (`BeefStock::where(...)->delete()`) tidak memicu event itu, jadi stok bisa terhapus saat opname berjalan.
 */
class BeefStockDeleteGoesThroughModelTest extends TestCase
{
    public function test_no_code_deletes_beef_stock_rows_with_a_mass_query(): void
    {
        $pelanggar = [];

        foreach ((new Finder())->files()->in(app_path())->name('*.php') as $file) {
            $isi = $file->getContents();

            if (preg_match('/BeefStock::(?:where|whereIn|query)\([^;]*->delete\(\)/s', $isi, $m) && ! str_contains($m[0], '->get()')) {
                $pelanggar[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $pelanggar, "Hapus stok daging lewat kueri massal melewati pembekuan opname:\n".implode("\n", $pelanggar));
    }
}
