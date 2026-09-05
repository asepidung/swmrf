<?php

namespace App\Filament\Admin\Resources\BeefStockResource\Pages;

use App\Filament\Admin\Resources\BeefStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
     */
    #[Url]
    public bool $showEmpty = false;

    /**
     * Tabelnya dibangun ULANG begitu tanggalnya berganti.
     *
     * Filament membangun tabelnya sekali saja, di `bootedInteractsWithTable()`,
     * dan boot berjalan SEBELUM nilai properti yang baru dipasang. Untuk
     * filter biasa itu tidak masalah: nilainya dibaca saat query dijalankan,
     * jadi selalu mutakhir.
     *
     * Tanggal di sini lain. Ia ikut menentukan KOLOM apa saja yang muncul,
     * dan kolom sudah terbentuk sejak boot. Tanpa membangun ulang, tanggal
     * yang baru dipilih hanya mengubah angkanya sementara kolomnya masih
     * milik tanggal sebelumnya -- angka tampil di kolom yang keliru, dan
     * tidak ada satu pun error yang memberitahu.
     */
    public function updatedTableFilters(): void
    {
        parent::updatedTableFilters();

        $this->table = $this->table($this->makeTable());
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if (! $query || $this->showEmpty) {
            return $query;
        }

        $batas = BeefStockResource::asOf();

        // Tanpa tanggal, "tidak kosong" berarti punya baris stok sekarang.
        if (! $batas) {
            return $query->whereHas('beefStocks', fn (Builder $stok) => $stok->where('status', 'IN_STOCK'));
        }

        // Dengan tanggal, `beef_stocks` tidak bisa menjawab sama sekali:
        // barang yang keluar dihapus barisnya, jadi produk yang dulu punya
        // stok tetapi kini habis akan ikut terbuang -- padahal justru itu
        // yang ingin dilihat saat menengok ke belakang.
        // Produk tanpa satu pun pergerakan ikut terbuang dengan sendirinya:
        // SUM atas himpunan kosong menghasilkan NULL, COALESCE membuatnya 0,
        // dan 0 <> 0 salah.
        return $query->whereRaw(
            '(SELECT ROUND(COALESCE(SUM(weight_in), 0) - COALESCE(SUM(weight_out), 0), 2)
              FROM beef_stock_movements
              WHERE beef_stock_movements.product_id = products.id
                AND beef_stock_movements.created_at <= ?) <> 0',
            [$batas],
        );
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
