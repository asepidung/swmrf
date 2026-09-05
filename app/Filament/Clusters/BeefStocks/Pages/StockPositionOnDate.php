<?php

namespace App\Filament\Clusters\BeefStocks\Pages;

use App\Filament\Clusters\BeefStocks;
use App\Models\StockTake;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * Posisi stok pada tanggal tertentu.
 *
 * `beef_stocks` sengaja hanya menyimpan keadaan sekarang -- barang yang keluar
 * dihapus barisnya supaya tabel yang dibaca sepanjang hari tetap ringan.
 * Karena itu halaman ini TIDAK membacanya sama sekali; angkanya dihitung ulang
 * dari `beef_stock_movements`, buku besar yang memuat setiap masuk dan keluar.
 *
 * Boleh dipercaya karena dua hal sudah diperiksa lebih dulu: seluruh 29 titik
 * yang mengubah stok menulis catatan pergerakan, dan baris stok tidak pernah
 * disunting setelah lahir. Keutuhannya di data yang berjalan diuji
 * `php artisan stock:reconcile` -- hitung ulang sampai sekarang harus sama
 * persis dengan isi `beef_stocks`.
 *
 * Bentuk tabelnya sengaja SAMA dengan Stock Overview: baris produk, kolom
 * gudang x grade, dikelompokkan per kategori. "Posisi tanggal lalu" jadi bisa
 * langsung dibandingkan dengan "posisi sekarang" tanpa membaca dua bentuk
 * tabel yang berbeda.
 */
class StockPositionOnDate extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = BeefStocks::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.clusters.beef-stocks.pages.stock-position-on-date';

    /**
     * Tanggalnya ikut di alamat halaman, jadi tautan yang disalin membuka
     * posisi yang sama persis.
     */
    #[Url]
    public ?string $tanggal = null;

    /** Wadah keadaan form-nya. */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Stock Position on a Date');
    }

    public function getTitle(): string
    {
        return __('Stock Position on a Date');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isProgrammer()
            || (auth()->user()?->hasPermission('view_beef_stocks') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->tanggal ??= now()->toDateString();

        $this->form->fill(['tanggal' => $this->tanggal]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('tanggal')
                    ->label(__('Position at the end of this day'))
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->maxDate(now())
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->tanggal = $state),
            ])
            ->statePath('data');
    }

    /** Batas waktu yang dipakai: AKHIR HARI dari tanggal yang dipilih. */
    public function batas(): Carbon
    {
        try {
            return Carbon::parse($this->tanggal ?: now()->toDateString())->endOfDay();
        } catch (\Throwable) {
            return now()->endOfDay();
        }
    }

    /**
     * Selama opname daging berjalan, angkanya tidak ditampilkan.
     *
     * Halaman ini menjawab persis pertanyaan yang seharusnya dijawab oleh
     * hitungan fisik. Hitungan yang bisa menyalin jawabannya lebih dulu tidak
     * memeriksa apa pun -- alasan yang sama membuat enam digit terakhir
     * barcode disamarkan di daftar stok.
     */
    public function sedangOpname(): bool
    {
        return StockTake::isCounting();
    }

    /**
     * Posisi stok pada `batas()`, dalam bentuk yang sama dengan Stock Overview.
     *
     * @return array{buckets: array<int, array<string, mixed>>, kategori: array<string, mixed>, total: array<string, float>, grand: float}
     */
    public function posisi(): array
    {
        if ($this->sedangOpname()) {
            return ['buckets' => [], 'kategori' => [], 'total' => [], 'grand' => 0.0];
        }

        $baris = DB::table('beef_stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('product_categories as c', 'c.id', '=', 'p.category_id')
            ->join('warehouses as w', 'w.id', '=', 'm.warehouse_id')
            ->join('grades as g', 'g.id', '=', 'm.condition')
            ->where('m.created_at', '<=', $this->batas())
            ->groupBy('m.product_id', 'm.warehouse_id', 'm.condition')
            ->selectRaw('m.product_id, m.warehouse_id, m.condition as grade_id')
            ->selectRaw('MAX(p.code) as kode, MAX(p.name) as produk, MAX(c.name) as kategori')
            ->selectRaw('MAX(w.name) as gudang, MAX(g.name) as grade')
            ->selectRaw('COALESCE(SUM(m.weight_in), 0) - COALESCE(SUM(m.weight_out), 0) as kg')
            ->get();

        $buckets = [];
        $produk = [];
        $totalKolom = [];
        $grand = 0.0;

        foreach ($baris as $satu) {
            $kg = round((float) $satu->kg, 2);

            // Saldo nol tidak melahirkan kolom, mengikuti keputusan Owner di
            // Stock Overview: kolom yang tidak ada datanya jangan ditampilkan.
            if (abs($kg) < 0.005) {
                continue;
            }

            $kunci = 'w' . $satu->warehouse_id . '_g' . $satu->grade_id;

            $buckets[$kunci] ??= [
                'key' => $kunci,
                'warehouse_id' => (int) $satu->warehouse_id,
                'grade_id' => (int) $satu->grade_id,
                'warehouse' => $satu->gudang,
                'grade' => $satu->grade,
            ];

            $idProduk = (int) $satu->product_id;

            $produk[$idProduk] ??= [
                'kode' => $satu->kode,
                'nama' => $satu->produk,
                'kategori' => $satu->kategori ?? '-',
                'kolom' => [],
                'total' => 0.0,
            ];

            $produk[$idProduk]['kolom'][$kunci] = ($produk[$idProduk]['kolom'][$kunci] ?? 0.0) + $kg;
            $produk[$idProduk]['total'] += $kg;

            $totalKolom[$kunci] = ($totalKolom[$kunci] ?? 0.0) + $kg;
            $grand += $kg;
        }

        // Urutan kolom: gudang lalu grade, sama seperti Stock Overview.
        uasort($buckets, fn (array $a, array $b): int => [$a['warehouse_id'], $a['grade_id']]
            <=> [$b['warehouse_id'], $b['grade_id']]);

        $kategori = [];

        foreach ($produk as $satu) {
            $kategori[$satu['kategori']]['produk'][] = $satu;

            foreach ($satu['kolom'] as $kunci => $kg) {
                $kategori[$satu['kategori']]['kolom'][$kunci] =
                    ($kategori[$satu['kategori']]['kolom'][$kunci] ?? 0.0) + $kg;
            }

            $kategori[$satu['kategori']]['total'] =
                ($kategori[$satu['kategori']]['total'] ?? 0.0) + $satu['total'];
        }

        ksort($kategori);

        foreach ($kategori as $nama => $isi) {
            usort($kategori[$nama]['produk'], fn (array $a, array $b): int => strcmp($a['kode'] ?? '', $b['kode'] ?? ''));
        }

        return [
            'buckets' => array_values($buckets),
            'kategori' => $kategori,
            'total' => $totalKolom,
            'grand' => $grand,
        ];
    }
}
