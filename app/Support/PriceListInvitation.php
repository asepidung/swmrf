<?php

namespace App\Support;

use App\Filament\Admin\Resources\PriceListResource;
use App\Models\CustomerGroup;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Tawaran membuat price list untuk grup yang belum punya harga.
 *
 * Kenapa ini perlu. Pelanggan baru yang dibuat tanpa memilih grup akan
 * OTOMATIS dibuatkan grup sendiri bernama sama dengan pelanggannya. Grup
 * baru itu tentu belum punya satu baris harga pun, sehingga setiap Sales
 * Order untuknya terisi Rp 0 di semua barisnya.
 *
 * Rp 0 itu sendiri tidak salah -- Project Owner menegaskan user bebas
 * mengubah harga saat membuat SO, jadi nol hanyalah titik awal. Yang keliru
 * adalah URUTANNYA: price list baru terpikirkan setelah SO dibuat, padahal
 * seharusnya sudah siap sebelumnya.
 *
 * Karena itu tawarannya muncul di saat yang paling awal mungkin, yaitu tepat
 * setelah pelanggan atau grupnya disimpan, dan sifatnya HANYA menawarkan.
 * Menghalangi penyimpanan akan salah sasaran: yang membuat pelanggan belum
 * tentu orang yang berhak menetapkan harga -- haknya pun terpisah
 * (`create_price_lists`).
 */
class PriceListInvitation
{
    /**
     * Tawarkan pembuatan price list bila grup ini memang belum punya harga.
     *
     * Yang diperiksa adalah ADA TIDAKNYA BARIS HARGA, bukan ada tidaknya
     * baris `price_lists`. Sebuah grup bisa saja punya baris price list
     * kosong yang tercipta sebagai efek samping form, dan grup seperti itu
     * sama tidak bergunanya dengan grup yang tidak punya price list sama
     * sekali. Daftar Price List pun memakai ukuran yang sama.
     */
    public static function offerFor(?CustomerGroup $group): void
    {
        if (! $group || static::hasPrices($group)) {
            return;
        }

        $notification = Notification::make()
            ->title(__('The group :group has no price list yet', ['group' => $group->name]))
            ->body(__('Without one, every Sales Order line for this group starts at Rp 0 and has to be typed by hand.'))
            ->warning()
            ->persistent();

        // Tautannya hanya ditampilkan kepada yang memang berhak mengisi
        // harga. Menawarkan pintu yang terkunci lebih membingungkan
        // daripada tidak menawarkan apa-apa.
        if (auth()->user()?->hasPermission('create_price_lists')) {
            $notification->actions([
                Action::make('create_price_list')
                    ->label(__('Create Price List Now'))
                    ->button()
                    ->url(PriceListResource\Pages\EditPriceList::getUrl([$group->id])),
            ]);
        }

        $notification->send();
    }

    /** Grup ini sudah punya setidaknya satu harga produk. */
    public static function hasPrices(CustomerGroup $group): bool
    {
        return (bool) $group->priceList?->items()->exists();
    }
}
