<?php

namespace App\Filament\Concerns;

use App\Models\DeliveryOrder;
use App\Models\Tally;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Penjaga perpindahan status Tally (approve / unapprove), TL-01.
 *
 * Dipanggil DI DALAM transaksi aksi: baris tally dikunci dan statusnya dibaca ulang, jadi tab lama atau dua penekanan
 * bersamaan tidak bisa memindahkan tally yang sudah berpindah tahap. Bila tidak lolos, pemberitahuan dikirim dan aksi
 * dihentikan (Halt membatalkan transaksinya).
 */
trait GuardsTallyTransition
{
    protected function lockTallyOrHalt(string $expectedStatus, bool $needsItems = false, bool $mustHaveNoDeliveryOrder = false): Tally
    {
        $tally = Tally::query()->whereKey($this->record->getKey())->lockForUpdate()->first();

        $reason = match (true) {
            ! $tally || $tally->status !== $expectedStatus => __('This tally has already moved on, so this action was not done.'),
            $needsItems && ! $tally->items()->exists() => __('This tally has no items yet, so it cannot be approved.'),
            $mustHaveNoDeliveryOrder && DeliveryOrder::where('tally_id', $tally->id)->exists() => __('This tally already has a delivery order, so its approval cannot be undone.'),
            default => null,
        };

        if ($reason !== null) {
            Notification::make()->title($reason)->warning()->send();

            throw new Halt();
        }

        return $tally;
    }
}
