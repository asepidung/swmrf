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
use Filament\Tables\Grouping\Group;

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

        $buckets = BeefStock::query()
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
            ->get()
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

    /** Judul kolom di berkas ekspor: "JONGGOL CHILL". */
    protected static function bucketLabel(array $bucket): string
    {
        return $bucket['warehouse'] . ' ' . $bucket['grade'];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->select('products.*');

        foreach (static::stockBuckets() as $bucket) {
            $query->addSelect([
                $bucket['key'] => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('warehouse_id', $bucket['warehouse_id'])
                    ->where('grade_id', $bucket['grade_id'])
                    ->where('status', 'IN_STOCK'),
            ]);
        }

        return $query->addSelect([
            'total_qty' => BeefStock::selectRaw('COALESCE(SUM(weight), 0)')
                ->whereColumn('product_id', 'products.id')
                ->where('status', 'IN_STOCK'),
        ]);
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

    public static function table(Table $table): Table
    {
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
                                'title' => __('Beef Stock')
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export_beef_stocks.pdf');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->filters([
                // Produk bersaldo nol disembunyikan oleh QUERY halaman daftarnya,
                // bukan oleh filter yang menyala sendiri. Lihat `ListBeefStocks`.
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('Category'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
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
