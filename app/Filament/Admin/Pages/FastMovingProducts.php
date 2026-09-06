<?php

namespace App\Filament\Admin\Pages;

use App\Models\ProductCategory;
use App\Support\LaporanPenjualan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Produk yang paling SERING dipesan, per kategori.
 *
 * Keputusan Owner, 6 September 2026: "paling sering di pesan, bisa dilihat
 * dari delivery order atau sales order, tapi lebih baik sales order walaupun
 * qty nya ditampilkan tapi itu bukan jadi acuan".
 *
 * Jadi yang mengurutkan FREKUENSI, bukan berat. Dua hal itu menjawab
 * pertanyaan yang berbeda: berat menjawab "apa yang paling banyak keluar",
 * frekuensi menjawab "apa yang paling sering diminta" -- dan yang kedua itu
 * yang menentukan apa yang harus selalu ada di gudang. Satu pesanan besar
 * tahunan tidak membuat sebuah produk perlu selalu tersedia; sepuluh pesanan
 * kecil setiap minggu membuatnya perlu.
 *
 * Beratnya tetap ditampilkan sebagai keterangan, karena dua produk dengan
 * frekuensi sama tetapi berat jauh berbeda menceritakan hal yang berbeda.
 *
 * **Dibandingkan per KATEGORI, bukan seluruhnya sekaligus.** Aplikasi lama
 * memisahkannya per cut (PRIME CUT, SECONDARY CUT, BONES) dengan alasan yang
 * sama: tulang dan prime cut tidak pernah bersaing memperebutkan tempat yang
 * sama di gudang, jadi mengurutkannya dalam satu daftar tidak menjawab apa
 * pun.
 *
 * Aplikasi lama menghitungnya dari SURAT JALAN. Angkanya karena itu tidak
 * akan sama persis: satu sales order bisa dikirim beberapa kali, dan pesanan
 * yang batal sebelum dikirim tidak pernah muncul di sana sama sekali.
 */
class FastMovingProducts extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static string $view = 'filament.admin.pages.fast-moving-products';

    protected static ?int $navigationSort = 2;

    public ?string $dari = null;

    public ?string $sampai = null;

    public ?int $kategori = null;

    public int $berapa = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('REPORTS');
    }

    public static function getNavigationLabel(): string
    {
        return __('Fast Moving Products');
    }

    public function getTitle(): string
    {
        return __('Fast Moving Products');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_fast_moving_products') ?? false;
    }

    public function mount(): void
    {
        $this->dari = now()->startOfMonth()->toDateString();

        // Batas akhirnya PESANAN TERAKHIR, bukan hari ini.
        //
        // Pada sistem yang datanya belum masuk setiap hari, "hari ini"
        // membuat laporannya kosong -- dan laporan kosong terbaca seolah
        // tidak ada pesanan sama sekali, bukan seolah rentangnya keliru.
        $this->sampai = LaporanPenjualan::tanggalPesananTerakhir();

        $this->kategori = ProductCategory::orderBy('prefix')->orderBy('name')->value('id');

        $this->form->fill([
            'dari' => $this->dari,
            'sampai' => $this->sampai,
            'kategori' => $this->kategori,
            'berapa' => $this->berapa,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('')
            ->schema([
                DatePicker::make('dari')
                    ->label(__('From'))
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->live(),

                DatePicker::make('sampai')
                    ->label(__('Until'))
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->live(),

                Select::make('kategori')
                    ->label(__('Category'))
                    ->options(fn (): array => ProductCategory::orderBy('prefix')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live(),

                Select::make('berapa')
                    ->label(__('How many'))
                    ->options(array_combine(range(5, 50, 5), array_map(
                        fn (int $satu): string => __('Top :count', ['count' => $satu]),
                        range(5, 50, 5),
                    )))
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live(),
            ])
            ->columns(4);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $dari = $this->dari ?: now()->startOfMonth()->toDateString();
        $sampai = $this->sampai ?: LaporanPenjualan::tanggalPesananTerakhir();

        $baris = LaporanPenjualan::seringDipesan($dari, $sampai, $this->kategori, (int) $this->berapa);

        return [
            'baris' => $baris,
            'dari' => $dari,
            'sampai' => $sampai,
            'adaKategori' => ProductCategory::exists(),
            // Dipakai untuk panjang batang: yang paling sering menjadi
            // seratus persen, sisanya sebanding terhadapnya.
            'frekuensiTertinggi' => (int) ($baris->max('frekuensi') ?: 1),
        ];
    }
}
