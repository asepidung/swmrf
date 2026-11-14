<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Halaman yang dipakai memindai/mencetak berulang harus memberi id yang sama ('scan-toast') pada setiap toast-nya,
 * supaya toast baru menggantikan yang lama dan tidak bertumpuk (Owner, 14 November 2026).
 */
class ScanToastIdTest extends TestCase
{
    private const HALAMAN = [
        'GoodsReceiptProductResource/Pages/ScanGoodsReceiptProduct',
        'GoodsReceiptProductResource/Pages/LabelingGoodsReceiptProduct',
        'BoningResource/Pages/LabelingBoning',
        'MutationResource/Pages/ScanMutation',
        'MutationResource/Pages/ReceiveMutation',
        'RepackResource/Pages/InputBahanRepack',
        'RepackResource/Pages/InputHasilRepack',
        'SalesReturnResource/Pages/InputReturnItems',
        'StockTakeResource/Pages/ScanStockTake',
        'TallyResource/Pages/ScanTally',
    ];

    /** @test */
    public function every_toast_on_a_scan_page_carries_the_shared_id(): void
    {
        foreach (self::HALAMAN as $halaman) {
            $sumber = file_get_contents(app_path("Filament/Admin/Resources/{$halaman}.php"));

            $this->assertStringNotContainsString(
                'Notification::make()',
                $sumber,
                "{$halaman}: toast tanpa id akan bertumpuk saat scan beruntun; pakai Notification::make('scan-toast').",
            );
        }
    }
}
