<?php

namespace App\Filament\Admin\Widgets;

use App\Support\LaporanPenjualan;
use Filament\Widgets\ChartWidget;

/**
 * Penjualan dua belas bulan, tahun terpilih melawan tahun sebelumnya.
 *
 * Bentuknya mengikuti aplikasi lama: dua garis, bukan dua batang. Garis
 * dipilih karena yang dibaca dari grafik ini BENTUK NAIK-TURUNNYA sepanjang
 * tahun -- kapan ramai, kapan sepi, dan apakah pola tahun ini mengikuti tahun
 * lalu. Batang menonjolkan perbandingan antar bulan satu per satu, dan itu
 * bukan pertanyaan yang dibawa orang ke halaman ini.
 *
 * Tabel di bawahnya tetap ada dan tetap yang berwenang. Grafik menjawab
 * "bagaimana bentuknya"; angka yang dipakai untuk memutuskan sesuatu dibaca
 * dari tabel.
 */
class SalesYearlyChart extends ChartWidget
{
    /** Diisi halaman laporannya lewat `@livewire`, ikut berganti saat tahunnya diganti. */
    public ?int $tahun = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '280px';

    public function getHeading(): ?string
    {
        return __('Sales trend');
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * Sumbu Y dimulai dari NOL.
     *
     * Chart.js secara bawaan memulai sumbunya dari nilai terendah datanya,
     * dan itu membesar-besarkan selisih: dua bulan yang berbeda satu persen
     * bisa tergambar seolah berbeda dua kali lipat. Untuk angka penjualan
     * yang dibaca sekilas, itu menyesatkan.
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    protected function getData(): array
    {
        $tahun = $this->tahun ?? (int) now()->format('Y');

        $sekarang = LaporanPenjualan::totalPerBulan($tahun);
        $sebelumnya = LaporanPenjualan::totalPerBulan($tahun - 1);

        // Bulan yang belum terjadi dikirim `null`, bukan nol.
        //
        // Chart.js MEMUTUS garisnya pada nilai null. Kalau dikirim nol,
        // garisnya terjun ke dasar sampai Desember -- terbaca seolah
        // penjualannya berhenti, padahal bulannya cuma belum datang.
        $batas = $tahun === (int) now()->format('Y') ? (int) now()->format('n') : 12;

        $data = [];

        foreach ($sekarang as $bulan => $total) {
            $data[] = $bulan > $batas ? null : $total;
        }

        return [
            'datasets' => [
                [
                    'label' => (string) $tahun,
                    'data' => $data,
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                    'spanGaps' => false,
                ],
                [
                    'label' => (string) ($tahun - 1),
                    'data' => array_values($sebelumnya),
                    'borderColor' => '#94a3b8',
                    'borderDash' => [6, 4],
                    'fill' => false,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_values($this->namaBulan()),
        ];
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
