<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BeefStockResource\Pages;
use App\Filament\Admin\Resources\BeefStockResource\RelationManagers;
use App\Models\Product;
use App\Models\BeefStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Columns\Summarizers\Sum;

use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Grouping\Group;
use Illuminate\Support\Carbon;
use App\Models\BeefStockMovement;

class BeefStockResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $cluster = \App\Filament\Clusters\BeefStocks::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?int $navigationSort = 1;



    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function getNavigationLabel(): string
    {
        return __('Beef Stock');
    }

    public static function getModelLabel(): string
    {
        return __('Beef Stock');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Beef Stock');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isProgrammer() || auth()->user()?->hasPermission('view_beef_stocks');
    }

    /**
     * Membuka satu produk dijaga izin YANG SAMA dengan membuka daftarnya.
     *
     * Model resource ini `Product`, bukan `BeefStock`. Karena `canView()`
     * tidak pernah ditulis, ia jatuh ke `ProductPolicy::view()` yang meminta
     * `view_products` -- izin yang sama sekali berbeda dari `view_beef_stocks`
     * yang menjaga daftarnya.
     *
     * Akibatnya: orang gudang yang hanya diberi `view_beef_stocks` melihat
     * daftarnya utuh, setiap barisnya bisa diklik (`recordUrl` selalu
     * dipasang), lalu menemukan 403. Tidak ada gejala lain -- tombolnya tidak
     * hilang, hanya halamannya yang menolak.
     */
    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    /**
     * Hasil hitungan disimpan di CONTAINER, bukan di properti statis.
     *
     * Properti statis pada kelas resource hidup selama prosesnya hidup. Di
     * satu permintaan web itu tidak terasa, tetapi di rangkaian test seluruh
     * berkasnya berjalan dalam satu proses -- sehingga bucket dari test
     * pertama ikut terbawa ke test berikutnya yang datanya sama sekali
     * berbeda, dan yang gagal justru test yang benar.
     *
     * Container disegarkan setiap permintaan DAN setiap test, jadi umurnya
     * persis selama yang dibutuhkan.
     */
    private const CACHE_BUCKETS = 'beef-stock.buckets';

    private const CACHE_SUMS = 'beef-stock.category-sums';

    /** Tanggal yang sedang dilihat; kosong berarti sekarang. */
    public const AS_OF = 'beef-stock.as-of';

    /**
     * Batas waktu yang sedang dilihat, atau `null` kalau yang diminta posisi
     * sekarang.
     *
     * Halaman daftarnyalah yang menaruh nilainya di container, karena kolom
     * dan query di kelas ini statis dan tidak bisa membaca keadaan Livewire.
     */
    public static function asOf(): ?Carbon
    {
        return app()->bound(self::AS_OF) ? app()->make(self::AS_OF) : null;
    }

    /**
     * Kalimat yang menerangkan angka ini posisi kapan.
     *
     * `null` berarti posisi sekarang -- di layar itu tidak perlu dikatakan,
     * karena tanpa saringan tanggal memang itu yang diharapkan orang.
     *
     * Peringatan WAKTU INPUT ikut di dalamnya dan tidak boleh dipisah. Angka
     * posisi tanggal mundur dihitung ulang dari `beef_stock_movements`, yang
     * hanya punya `created_at`: barang yang datang Senin tetapi baru diinput
     * Selasa terhitung di hari Selasa. Untuk pemakaian harian bedanya tidak
     * terasa; pada batas bulan bedanya persis sebesar keterlambatan input,
     * dan di situlah angkanya dibandingkan dengan hitungan fisik.
     */
    public static function keteranganPosisi(): ?string
    {
        if (! $batas = static::asOf()) {
            return null;
        }

        return __('Position as at :date at 23:59:59. The date is the time of ENTRY, not the document date.', [
            'date' => $batas->format('d M Y'),
        ]);
    }

    /**
     * Kalimat yang sama untuk BERKAS, dan di sini ia tidak boleh kosong.
     *
     * Di layar, "posisi sekarang" cukup dimengerti dari tidak adanya
     * saringan tanggal. Berkas tidak punya konteks itu: ia dibuka besok, atau
     * dikirim ke orang yang tidak melihat layarnya sama sekali, dan tidak ada
     * apa pun di dalamnya yang memberi tahu angka itu milik kapan.
     */
    public static function keteranganPosisiBerkas(): string
    {
        return static::keteranganPosisi()
            ?? __('Current position, exported :date.', ['date' => now()->format('d M Y H:i')]);
    }

    /**
     * Membuang hitungan yang tersimpan, karena tanggalnya berganti.
     *
     * Kolom dan jumlah per kategori disimpan di container supaya tidak
     * dihitung berkali-kali dalam satu permintaan. Begitu tanggalnya berganti,
     * simpanan itu menjadi jawaban untuk pertanyaan yang lain: kolom milik
     * tanggal lama dipakai untuk angka tanggal baru, dan angkanya tampil di
     * kolom yang keliru tanpa satu pun error.
     *
     * Di web setiap permintaan membawa container baru sehingga tidak terasa;
     * yang tidak punya pengaman ini adalah rangkaian test dan proses yang
     * berumur panjang.
     */
    public static function forgetCachedPosition(): void
    {
        app()->forgetInstance(self::CACHE_BUCKETS);
        app()->forgetInstance(self::CACHE_SUMS);
    }

    /**
     * Kolom stok dibangun dari GUDANG x GRADE yang benar-benar punya isi.
     *
     * Dulu keempat kolomnya dipatok mati pada `warehouse_id` 1 dan 2 serta
     * `grade_id` 1 dan 2, di LIMA tempat sekaligus: query di sini, kolom
     * tabel, baris jumlah per kategori di Blade, ekspor Excel, dan ekspor PDF.
     *
     * Kolom Total tidak pernah ikut dipatok -- ia menjumlah SELURUH baris
     * berstatus `IN_STOCK`. Padahal grade yang aktif ada lima (CHILL, FROZEN,
     * A, B, R) dan setiap form yang membuat stok menawarkan semuanya. Satu
     * karton ber-grade A masuk ke Total tetapi tidak muncul di kolom mana pun,
     * sehingga Total lebih besar daripada jumlah kolom yang terlihat dan tidak
     * ada yang bisa menunjuk selisihnya ada di mana.
     *
     * Keputusan Owner, 5 September 2026: kolomnya tetap dibagi per gudang,
     * gradenya lima, dan kolom yang tidak ada datanya JANGAN ditampilkan.
     * Karena kolomnya kini lahir dari data, Total selalu sama dengan jumlah
     * kolom yang terlihat -- bukan karena dijaga, melainkan karena tidak ada
     * kombinasi berisi yang bisa kehilangan kolomnya.
     *
     * @return array<int, array{key: string, warehouse_id: int, grade_id: int, warehouse: string, grade: string}>
     */
    public static function stockBuckets(): array
    {
        if (app()->bound(self::CACHE_BUCKETS)) {
            return app()->make(self::CACHE_BUCKETS);
        }

        $buckets = (static::asOf() ? static::bucketsPadaTanggal() : static::bucketsSekarang())
            ->map(fn ($row): array => [
                'key' => 'w' . $row->warehouse_id . '_g' . $row->grade_id,
                'warehouse_id' => (int) $row->warehouse_id,
                'grade_id' => (int) $row->grade_id,
                'warehouse' => (string) $row->warehouse_name,
                'grade' => (string) $row->grade_name,
            ])
            ->all();

        app()->instance(self::CACHE_BUCKETS, $buckets);

        return $buckets;
    }

    /** Kombinasi yang ada isinya SEKARANG, dibaca dari tabel stok. */
    protected static function bucketsSekarang(): \Illuminate\Support\Collection
    {
        return BeefStock::query()
            ->join('warehouses', 'warehouses.id', '=', 'beef_stocks.warehouse_id')
            ->join('grades', 'grades.id', '=', 'beef_stocks.grade_id')
            ->where('beef_stocks.status', 'IN_STOCK')
            ->select(
                'beef_stocks.warehouse_id',
                'beef_stocks.grade_id',
                'warehouses.name as warehouse_name',
                'grades.name as grade_name',
            )
            ->distinct()
            ->orderBy('beef_stocks.warehouse_id')
            ->orderBy('beef_stocks.grade_id')
            ->get();
    }

    /**
     * Kombinasi yang ada isinya PADA TANGGAL yang dipilih.
     *
     * Dihitung ulang dari buku besar, dan yang saldonya nol dibuang -- kolom
     * yang tidak ada datanya tidak ditampilkan, aturan yang sama dengan
     * posisi sekarang.
     */
    protected static function bucketsPadaTanggal(): \Illuminate\Support\Collection
    {
        return BeefStockMovement::query()
            ->join('warehouses', 'warehouses.id', '=', 'beef_stock_movements.warehouse_id')
            ->join('grades', 'grades.id', '=', 'beef_stock_movements.condition')
            ->where('beef_stock_movements.created_at', '<=', static::asOf())
            ->groupBy('beef_stock_movements.warehouse_id', 'beef_stock_movements.condition')
            ->havingRaw('ROUND(COALESCE(SUM(weight_in), 0) - COALESCE(SUM(weight_out), 0), 2) <> 0')
            ->selectRaw('beef_stock_movements.warehouse_id, beef_stock_movements.condition as grade_id')
            ->selectRaw('MAX(warehouses.name) as warehouse_name, MAX(grades.name) as grade_name')
            ->orderBy('beef_stock_movements.warehouse_id')
            ->orderBy('beef_stock_movements.condition')
            ->get();
    }

    /** Judul kolom di berkas ekspor: "JONGGOL CHILL". */
    protected static function bucketLabel(array $bucket): string
    {
        return $bucket['warehouse'] . ' ' . $bucket['grade'];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->select('products.*');

        foreach (static::stockBuckets() as $bucket) {
            $query->addSelect([$bucket['key'] => static::saldo($bucket)]);
        }

        return $query->addSelect(['total_qty' => static::saldo()]);
    }

    /**
     * Berat satu produk: sekarang dari tabel stok, tanggal mundur dari buku
     * besar.
     *
     * Tanpa tanggal, yang dibaca `beef_stocks` -- tabel itulah yang memegang
     * kebenaran tentang stok hari ini, dan tidak diganti hanya karena buku
     * besarnya juga bisa menjawab.
     *
     * Dengan tanggal, `beef_stocks` sama sekali tidak bisa menjawab: barang
     * yang keluar DIHAPUS barisnya, sesuai keputusan Owner supaya tabel yang
     * dibaca sepanjang hari tetap ringan. Riwayatnya hanya ada di
     * `beef_stock_movements`, dan keutuhannya diuji `php artisan stock:reconcile`.
     *
     * @param  array<string, mixed>|null  $bucket  null berarti seluruh gudang & grade
     */
    protected static function saldo(?array $bucket = null): Builder
    {
        if ($batas = static::asOf()) {
            $query = BeefStockMovement::selectRaw(
                'COALESCE(SUM(weight_in), 0) - COALESCE(SUM(weight_out), 0)'
            )
                ->whereColumn('product_id', 'products.id')
                ->where('created_at', '<=', $batas);

            if ($bucket) {
                $query->where('warehouse_id', $bucket['warehouse_id'])
                    ->where('condition', $bucket['grade_id']);
            }

            return $query;
        }

        $query = BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
            ->whereColumn('product_id', 'products.id')
            ->where('status', 'IN_STOCK');

        if ($bucket) {
            $query->where('warehouse_id', $bucket['warehouse_id'])
                ->where('grade_id', $bucket['grade_id']);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Product Details'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('Code'))
                            ->disabled(),
                        Forms\Components\TextInput::make('name')
                            ->label(__('Name'))
                            ->disabled(),
                        Forms\Components\TextInput::make('category_name')
                            ->label(__('Category'))
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $record) {
                                if ($record) {
                                    $component->state($record->category?->name);
                                }
                            }),
                    ])->columns(3),
            ]);
    }

    public static function getCategorySums($livewire, $categoryName): array
    {
        $buckets = static::stockBuckets();

        if (! app()->bound(self::CACHE_SUMS)) {
            $terkumpul = [];

            $query = $livewire->getFilteredTableQuery();
            $sumsQuery = (clone $query);

            $pilih = ['product_categories.name as category_name'];

            foreach ($buckets as $bucket) {
                $pilih[] = "COALESCE(SUM(sub.{$bucket['key']}), 0) as {$bucket['key']}";
            }

            $pilih[] = 'COALESCE(SUM(sub.total_qty), 0) as total_qty';

            $results = Product::query()
                ->fromSub($sumsQuery, 'sub')
                ->join('product_categories', 'sub.category_id', '=', 'product_categories.id')
                ->selectRaw(implode(', ', $pilih))
                ->groupBy('product_categories.name')
                ->get();

            foreach ($results as $row) {
                $baris = [];

                foreach ($buckets as $bucket) {
                    $baris[$bucket['key']] = (float) $row->{$bucket['key']};
                }

                $baris['total_qty'] = (float) $row->total_qty;

                $terkumpul[$row->category_name] = $baris;
            }

            app()->instance(self::CACHE_SUMS, $terkumpul);
        }

        $kosong = ['total_qty' => 0.0];

        foreach ($buckets as $bucket) {
            $kosong[$bucket['key']] = 0.0;
        }

        return app()->make(self::CACHE_SUMS)[$categoryName] ?? $kosong;
    }

    /** Satu kolom berat, dipakai untuk setiap kombinasi gudang x grade. */
    protected static function weightColumn(string $key, string $label): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make($key)
            ->label($label)
            ->alignRight()
            ->extraHeaderAttributes(['style' => 'text-align: center; justify-content: center;'])
            ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
            ->summarize(Sum::make()->label(''));
    }

    /**
     * Memasang tanggal yang sedang dipilih, dibaca dari komponen hidupnya.
     *
     * Percobaan pertama memasangnya di `booted()` halaman daftarnya, dan itu
     * SALAH dengan cara yang halus: `booted()` berjalan di awal permintaan,
     * sebelum nilai filter yang baru dipasang ke propertinya. Akibatnya
     * tanggal yang baru dipilih baru berlaku pada interaksi BERIKUTNYA --
     * layar menampilkan angka tanggal lama sementara filternya sudah
     * menunjukkan tanggal baru, dan tidak ada error apa pun.
     *
     * `table()` dipanggil saat render, sesudah seluruh properti mutakhir, dan
     * ia menerima komponennya lewat `$table->getLivewire()`. Di sinilah satu-
     * satunya tempat yang pasti melihat keadaan yang sebenarnya.
     */
    protected static function pasangTanggal(Table $table): void
    {
        $livewire = $table->getLivewire();
        $tanggal = data_get($livewire->tableFilters ?? [], 'as_of.date');
        $batas = filled($tanggal) ? Carbon::parse($tanggal)->endOfDay() : null;

        if (static::asOf()?->toDateTimeString() === $batas?->toDateTimeString()) {
            return;
        }

        app()->instance(self::AS_OF, $batas);

        // Kolom dan jumlah per kategori milik tanggal lama tidak boleh dipakai
        // untuk angka tanggal baru.
        static::forgetCachedPosition();
    }

    public static function table(Table $table): Table
    {
        static::pasangTanggal($table);

        $columns = [
            Tables\Columns\TextColumn::make('code')
                ->label(__('Code'))
                ->weight('bold')
                ->alignCenter()
                ->searchable(),

            Tables\Columns\TextColumn::make('name')
                ->label(__('Product Name'))
                ->weight('bold')
                ->searchable(),
        ];

        // Satu ColumnGroup per gudang, berisi grade yang ada isinya di gudang
        // itu saja. Urutannya mengikuti urutan bucket, jadi gudang dan grade
        // tampil dengan urutan id yang sama setiap saat.
        $perGudang = [];

        foreach (static::stockBuckets() as $bucket) {
            $perGudang[$bucket['warehouse']][] = static::weightColumn($bucket['key'], $bucket['grade']);
        }

        foreach ($perGudang as $namaGudang => $kolomGudang) {
            $columns[] = ColumnGroup::make($namaGudang, $kolomGudang);
        }

        $columns[] = Tables\Columns\TextColumn::make('total_qty')
            ->label(__('Total'))
            ->alignRight()
            ->extraHeaderAttributes(['style' => 'text-align: center; justify-content: center;'])
            ->weight('bold')
            ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2, '.', ',') : '')
            ->summarize(Sum::make()->label(''));

        return $table
            ->view('filament.admin.resources.beef-stock.table')
            ->striped()
            ->paginated(false)
            ->defaultGroup(
                Group::make('category.name')
                    ->titlePrefixedWithLabel(false)
            )
            ->columns($columns)
            ->headerActions([
                \Filament\Tables\Actions\ActionGroup::make([
                    \Filament\Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $buckets = static::stockBuckets();

                            return response()->streamDownload(function () use ($records, $buckets) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');

                                // Baris pertama menyebut angka ini posisi
                                // kapan. Tanpa itu, berkas posisi tanggal
                                // mundur tidak bisa dibedakan dari berkas
                                // posisi hari ini oleh siapa pun yang
                                // membukanya besok.
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    static::keteranganPosisiBerkas(),
                                ]));

                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['']));

                                $judul = [__('Code'), __('Product Name')];

                                foreach ($buckets as $bucket) {
                                    $judul[] = static::bucketLabel($bucket);
                                }

                                $judul[] = __('Total');

                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($judul));

                                foreach ($records as $record) {
                                    $baris = [$record->code ?? '', $record->name ?? ''];

                                    foreach ($buckets as $bucket) {
                                        $baris[] = $record->{$bucket['key']} ?? '';
                                    }

                                    $baris[] = $record->total_qty ?? '';

                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($baris));
                                }

                                $writer->close();
                            }, 'excel.xlsx');
                        }),
                    \Filament\Tables\Actions\Action::make('pdf')
                        ->label(__('PDF'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.beef-stocks-pdf', [
                                'records' => $records,
                                'buckets' => static::stockBuckets(),
                                'title' => __('Beef Stock'),
                                'keterangan' => static::keteranganPosisiBerkas()
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export_beef_stocks.pdf');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            // Filternya di ATAS tabel, bukan di balik tombol.
            //
            // Tanggal yang sedang dilihat harus terbaca sekilas: angka stok
            // yang ternyata milik tanggal lain adalah kesalahan yang tidak
            // menimbulkan gejala apa pun.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->description(fn (): ?string => static::keteranganPosisi())
            ->filters([
                // Posisi stok pada tanggal tertentu.
                //
                // Kosong berarti sekarang. Diisi berarti akhir hari itu,
                // dihitung ulang dari `beef_stock_movements` -- `beef_stocks`
                // tidak bisa menjawabnya karena barang yang keluar dihapus
                // barisnya.
                //
                // Penyaringannya TIDAK dikerjakan di sini. Tanggalnya ikut
                // menentukan KOLOM apa saja yang muncul, dan kolom dibangun
                // sebelum filter dijalankan; jadi `ListBeefStocks` yang
                // memasangnya lebih dulu, dan bagian ini hanya isian layarnya.
                Tables\Filters\Filter::make('as_of')
                    ->form([
                        Forms\Components\DatePicker::make('date')
                            ->label(__('Stock position on'))
                            ->placeholder(__('Now'))
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->maxDate(now()),
                    ])
                    ->query(fn (Builder $query): Builder => $query)
                    ->indicateUsing(function (array $data): ?string {
                        if (blank($data['date'] ?? null)) {
                            return null;
                        }

                        return __('Position as at :date', [
                            'date' => Carbon::parse($data['date'])->format('d M Y'),
                        ]);
                    }),
                // Filter kategori dilepas atas keputusan Owner: kategorinya
                // sudah menjadi baris pengelompokan di tabel ini, jadi
                // menyaringnya dari atas hanya mengulang hal yang sama dengan
                // cara kedua.
            ])
            ->actions([
                // Clickable rows are enabled, no explicit view action button is needed
            ])
            ->recordUrl(fn (Product $record): string => Pages\ViewBeefStock::getUrl(['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BeefStocksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBeefStocks::route('/'),
            'view' => Pages\ViewBeefStock::route('/{record}'),
        ];
    }
}
