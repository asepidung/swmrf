<?php

namespace App\Filament\Admin\Pages;

use App\Support\LaporanPenjualan;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Total penjualan per bulan, tahun ini dibandingkan tahun lalu.
 *
 * Bentuknya mengikuti laporan yang sama di aplikasi lama
 * (`reports/sales.php`): satu tahun dipilih, dua belas bulan berjajar, dan
 * tahun sebelumnya berdiri di sebelahnya sebagai pembanding. Perbandingan
 * itulah isinya -- angka penjualan satu bulan tidak berarti apa-apa sampai
 * ada yang bisa dibandingkan dengannya.
 *
 * **Yang dijumlahkan `billedAmount()`**, bukan `subtotal`: berapa yang
 * benar-benar ditagihkan kepada pelanggan, sesudah biaya lain, uang muka,
 * dan retur yang sudah disetujui. Aturannya di `LaporanPenjualan`, dan ada
 * uji yang membandingkannya dengan `Invoice::billedAmount()` baris per baris.
 *
 * **Laba belum bisa ditampilkan di sini.** Itu butuh HPP, dan HPP menunggu
 * B.O.M. Yang ada sekarang omzet, bukan untung -- dan menuliskannya sebagai
 * "laba" hanya karena kolomnya kosong akan lebih buruk daripada tidak
 * menampilkannya sama sekali.
 */
class SalesReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string $view = 'filament.admin.pages.sales-report';

    protected static ?int $navigationSort = 1;

    public ?int $tahun = null;

    public static function getNavigationGroup(): ?string
    {
        return __('REPORTS');
    }

    public static function getNavigationLabel(): string
    {
        return __('Sales Report');
    }

    public function getTitle(): string
    {
        return __('Sales Report');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_sales_report') ?? false;
    }

    public function mount(): void
    {
        $this->tahun = (int) now()->format('Y');

        $this->form->fill(['tahun' => $this->tahun]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('')
            ->schema([
                Select::make('tahun')
                    ->label(__('Year'))
                    ->options(fn (): array => array_combine(
                        LaporanPenjualan::tahunYangAdaDatanya(),
                        LaporanPenjualan::tahunYangAdaDatanya(),
                    ))
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live(),
            ]);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $tahun = $this->tahun ?? (int) now()->format('Y');

        $sekarang = LaporanPenjualan::totalPerBulan($tahun);
        $sebelumnya = LaporanPenjualan::totalPerBulan($tahun - 1);

        // Bulan yang BELUM terjadi dikosongkan, tidak ditulis nol.
        //
        // Nol berarti "tidak ada penjualan"; bulan yang belum datang bukan
        // itu. Menuliskannya nol membuat garis tahun berjalan terjun ke dasar
        // dan totalnya terbaca seolah penjualan berhenti.
        $batas = $tahun === (int) now()->format('Y') ? (int) now()->format('n') : 12;

        $barisSekarang = [];

        foreach ($sekarang as $bulan => $total) {
            $barisSekarang[$bulan] = $bulan > $batas ? null : $total;
        }

        $totalSekarang = array_sum(array_filter($barisSekarang, fn ($satu) => $satu !== null));
        $totalSebelumnya = array_sum($sebelumnya);

        return [
            'tahun' => $tahun,
            'tahunSebelumnya' => $tahun - 1,
            'bulanan' => $barisSekarang,
            'bulananSebelumnya' => $sebelumnya,
            'totalSekarang' => $totalSekarang,
            'totalSebelumnya' => $totalSebelumnya,
            'selisihPersen' => $this->selisihPersen($totalSekarang, $totalSebelumnya),
            'namaBulan' => $this->namaBulan(),
        ];
    }

    /**
     * Selisih terhadap tahun lalu, dalam persen.
     *
     * `null` kalau tahun lalu nol: naik dari nol bukan "naik seratus persen",
     * itu pembagian yang tidak ada artinya. Layarnya menuliskan tanda strip,
     * bukan angka yang terdengar meyakinkan tetapi tidak berdasar.
     */
    private function selisihPersen(float $sekarang, float $sebelumnya): ?float
    {
        if ($sebelumnya <= 0.0) {
            return null;
        }

        return round((($sekarang - $sebelumnya) / $sebelumnya) * 100, 1);
    }

    /** @return array<int, string> */
    private function namaBulan(): array
    {
        $nama = [];

        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $nama[$bulan] = \Illuminate\Support\Carbon::create(null, $bulan, 1)->translatedFormat('M');
        }

        return $nama;
    }
}
