<?php

namespace App\Filament\Admin\Widgets;

use App\Support\LaporanPenjualan;
use Filament\Widgets\ChartWidget;

/**
 * Berapa kali tiap produk dipesan, sebagai batang.
 *
 * Bentuknya mengikuti aplikasi lama: batang, bukan garis. Yang dibaca dari
 * sini PERBANDINGAN ANTAR PRODUK pada satu rentang waktu -- seberapa jauh
 * yang teratas meninggalkan sisanya -- dan itu memang pertanyaan yang dijawab
 * batang. Garis akan menyiratkan urutan waktu yang tidak ada di sumbu ini.
 *
 * Batangnya MENDATAR. Nama produk di sini panjang-panjang ("TENDERLOIN
 * FROZEN GRADE A"), dan pada batang tegak nama sepanjang itu dimiringkan atau
 * dipotong. Pada batang mendatar ia terbaca apa adanya.
 */
class FastMovingChart extends ChartWidget
{
    public ?string $dari = null;

    public ?string $sampai = null;

    public ?int $kategori = null;

    public int $berapa = 10;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '360px';

    public function getHeading(): ?string
    {
        return __('Times ordered');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    // Frekuensi selalu bilangan bulat: satu produk tidak bisa
                    // dipesan dua setengah kali.
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }

    protected function getData(): array
    {
        $baris = LaporanPenjualan::seringDipesan(
            $this->dari ?: now()->startOfMonth()->toDateString(),
            $this->sampai ?: LaporanPenjualan::tanggalPesananTerakhir(),
            $this->kategori,
            $this->berapa,
        );

        return [
            'datasets' => [
                [
                    'label' => __('Times ordered'),
                    'data' => $baris->map(fn ($satu): int => (int) $satu->frekuensi)->all(),
                    'backgroundColor' => '#0ea5e9',
                ],
            ],
            'labels' => $baris->map(fn ($satu): string => $satu->name)->all(),
        ];
    }
}
