<?php

namespace App\Filament\Admin\Resources\BeefStockResource\Pages;

use App\Filament\Admin\Resources\BeefStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListBeefStocks extends ListRecords
{
    protected static string $resource = BeefStockResource::class;

    /**
     * Produk bersaldo nol disembunyikan oleh QUERY-nya, bukan oleh filter.
     *
     * Dulu ini sebuah filter bernama "Hide Empty Stock" yang aktif secara
     * bawaan. Bentuk itu membingungkan: yang terbaca di layar adalah sebuah
     * filter yang menyala, sehingga daftar yang tampil terasa seperti hasil
     * penyaringan yang harus dilepas dulu untuk melihat "yang sebenarnya" --
     * padahal daftar itulah yang memang ingin dilihat sehari-hari.
     *
     * Keputusan Owner, 5 September 2026: bawaannya dari query saja, lalu
     * sediakan tombol untuk MEMUNCULKAN yang kosong. Tombolnya menyatakan apa
     * yang akan terjadi kalau ditekan, bukan keadaan yang sedang berlaku.
     *
     * `#[Url]` membuat pilihannya ikut di alamat halaman, jadi tautan yang
     * disalin ke orang lain membuka daftar yang sama persis.
     */
    #[Url]
    public bool $showEmpty = false;

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if ($query && ! $this->showEmpty) {
            $query->whereHas('beefStocks', fn (Builder $stok) => $stok->where('status', 'IN_STOCK'));
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggleEmptyStock')
                ->label(fn (): string => $this->showEmpty
                    ? __('Hide Empty Stock')
                    : __('Show Empty Stock'))
                ->icon(fn (): string => $this->showEmpty
                    ? 'heroicon-m-eye-slash'
                    : 'heroicon-m-eye')
                ->color('gray')
                ->action(fn () => $this->showEmpty = ! $this->showEmpty),
        ];
    }
}
