<?php

namespace App\Console\Commands;

use App\Models\DeliveryOrder;
use App\Models\FinancialLoss;
use App\Services\DeliveryShrinkValuator;
use Illuminate\Console\Command;

/**
 * Menilai susut kirim yang masih Rp 0 ("belum dinilai") dari harga pokok barcode kirimannya (Owner, 10 November 2026).
 *
 * Tanpa `--apply` hanya laporan. Hanya mengisi `financial_losses.amount` yang masih nol; tidak menyentuh harga barcode.
 */
class NilaiSusutKirim extends Command
{
    protected $signature = 'costing:nilai-susut-kirim {--apply : Simpan (tanpa ini hanya laporan)}';

    protected $description = 'Nilai susut kirim yang belum bernilai dari harga pokok barcode kirimannya';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = FinancialLoss::query()
            ->where('transaction_type', FinancialLoss::SUMBER_SURAT_JALAN)
            ->where('amount', '<=', 0)
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->get();

        $filled = 0;

        foreach ($rows as $loss) {
            $deliveryOrder = DeliveryOrder::find($loss->lossable_id);

            if (! $deliveryOrder) {
                continue;
            }

            $amount = DeliveryShrinkValuator::amountFor($deliveryOrder);

            if ($amount === null) {
                $this->line("{$loss->reference_number}: {$loss->quantity} kg, BELUM BISA dinilai (ada karton produk susut yang belum bernilai).");

                continue;
            }

            $this->line("{$loss->reference_number}: {$loss->quantity} kg = Rp ".number_format($amount, 2, ',', '.'));

            if ($apply && DeliveryShrinkValuator::revalue($deliveryOrder)) {
                $filled++;
            }
        }

        $this->info($apply ? "Diisi: {$filled} susut kirim." : 'Hanya laporan. Tambahkan --apply untuk menyimpan.');

        return self::SUCCESS;
    }
}
