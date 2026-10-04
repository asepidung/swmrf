<?php

namespace App\Filament\Concerns;

use App\Support\BarcodeSegments;
use App\Support\LabelWeightLimit;
use Filament\Actions\Action;

/**
 * Konfirmasi untuk berat label yang melewati batas wajar produknya (#486).
 *
 * Mengingatkan, BUKAN menolak: offal, kulit, dan bone memang sah ribuan kilo.
 * Yang dinilai berat per label setelah dipecah.
 * Halaman pemakai menyatakan metodenya lewat `labelMethod()` (bukan dari
 * permintaan klien -- nama metode yang datang dari klien bisa dipakai
 * memanggil metode apa saja), lalu memanggil `abnormalLabelWeight()` sebelum
 * menulis apa pun. Jawabannya `true` bila ia menampilkan modal dan pemanggil
 * HARUS berhenti; setelah dikonfirmasi, metode yang sama dipanggil ulang
 * dengan `$confirmed = true`.
 */
trait ConfirmsAbnormalLabelWeight
{
    /**
     * Metode halaman yang dipanggil ulang setelah operator mengonfirmasi.
     * Halaman yang metodenya bukan `create` menimpa fungsi ini.
     */
    protected function labelMethod(): string
    {
        return 'create';
    }

    protected function abnormalLabelWeight(int|string|null $productId, float $weight, bool $confirmed): bool
    {
        if ($confirmed || ! $productId || $weight <= 0) {
            return false;
        }

        // Yang dinilai berat PER LABEL: berat yang terlalu besar untuk satu
        // barcode dipecah rata (BarcodeSegments::split), jadi 16 ton offal
        // menjadi dua label 8 ton -- tidak perlu ditanyakan tiap kali.
        $weight = max(array_column(BarcodeSegments::split($weight, 1), 'weight'));

        if (! LabelWeightLimit::exceeds((int) $productId, $weight)) {
            return false;
        }

        $this->mountAction('confirmAbnormalLabelWeight', [
            'weight' => $weight,
            'limit' => LabelWeightLimit::forProduct((int) $productId),
        ]);

        return true;
    }

    public function confirmAbnormalLabelWeightAction(): Action
    {
        return Action::make('confirmAbnormalLabelWeight')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalHeading(__('Is this weight correct?'))
            ->modalDescription(fn (array $arguments): string => __(
                'This label weighs :weight kg, above the usual :limit kg for this product. Check for a typing mistake before continuing.',
                [
                    'weight' => number_format((float) ($arguments['weight'] ?? 0), 2, ',', '.'),
                    'limit' => number_format((float) ($arguments['limit'] ?? 0), 2, ',', '.'),
                ],
            ))
            ->modalSubmitActionLabel(__('Yes, the weight is correct'))
            ->action(fn () => $this->{$this->labelMethod()}(true));
    }
}
