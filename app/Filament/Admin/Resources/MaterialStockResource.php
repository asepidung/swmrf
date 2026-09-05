<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialStockResource\Pages;
use App\Models\MaterialStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;

class MaterialStockResource extends Resource
{
    protected static ?string $model = \App\Models\Material::class;

    protected static ?string $cluster = \App\Filament\Clusters\MaterialsStock::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('view_material_stocks') ?? false;
    }

    /**
     * Membuka satu material dijaga izin YANG SAMA dengan daftarnya.
     *
     * Model resource ini `Material`, bukan `MaterialStock`, sehingga
     * `canView()` yang tidak ditulis akan jatuh ke `MaterialPolicy::view()`
     * dan meminta `view_materials` -- izin yang berbeda dari yang menjaga
     * daftarnya. Barisnya memang tidak bisa diklik, tetapi rute `view`-nya
     * terdaftar, jadi alamatnya tetap bisa dibuka langsung.
     */
    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    /** Daftar ini LAPORAN. Stok material berubah lewat dokumen, bukan di sini. */
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function getNavigationLabel(): string
    {
        return __('Material Stock');
    }

    public static function getModelLabel(): string
    {
        return __('Material Stock');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Material Stocks');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('show_in_stock', true)
            ->addSelect([
                'qty' => \App\Models\MaterialStock::selectRaw('COALESCE(SUM(qty), 0)')
                    ->whereColumn('material_id', 'materials.id'),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Material Stock Details'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('Item Code'))
                            ->disabled(),
                        Forms\Components\TextInput::make('name')
                            ->label(__('Item Name'))
                            ->disabled(),
                        Forms\Components\TextInput::make('category_name')
                            ->label(__('Category'))
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => data_get($record, 'category.name')),
                        Forms\Components\TextInput::make('unit_name')
                            ->label(__('Unit'))
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => data_get($record, 'unit.name')),
                        Forms\Components\TextInput::make('qty')
                            ->label(__('Actual stock'))
                            ->disabled()
                            ->formatStateUsing(fn ($state): string => \App\Models\MaterialStockTake::isCounting()
                                ? '***'
                                : number_format((float) $state, 2, ',', '.')),
                        Forms\Components\TextInput::make('min_stock')
                            ->label(__('Min. Stock'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.')),
                    ])->columns(3)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Tidak dikelompokkan per kategori, atas keputusan Owner.
            // Daftarnya pendek dan kategorinya sudah punya kolom sendiri;
            // baris pemisah hanya memecah tabel tanpa menambah apa pun.
            //
            // Yang tampil tetap hanya material ber-`show_in_stock` -- lihat
            // `getEloquentQuery()`.
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Item Code'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Item Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label(__('Unit'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label(__('Actual stock'))
                    ->getStateUsing(fn (\App\Models\Material $record): string => \App\Models\MaterialStockTake::isCounting()
                        ? '***'
                        : number_format((float) $record->qty, 2, ',', '.'))
                    ->sortable()
                    ->color(fn (\App\Models\Material $record): string => \App\Models\MaterialStockTake::isCounting()
                        ? 'gray'
                        : ($record->qty < ($record->min_stock ?? 0) ? 'danger' : 'success'))
                    ->weight(fn (\App\Models\Material $record): ?string => \App\Models\MaterialStockTake::isCounting()
                        ? null
                        : ($record->qty < ($record->min_stock ?? 0) ? 'bold' : null)),
                Tables\Columns\TextColumn::make('min_stock')
                    ->label(__('Min. Stock'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable(),
            ])
            ->headerActions([
                \Filament\Tables\Actions\ActionGroup::make([
                    \Filament\Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        // Ekspor ikut disamarkan saat opname berjalan.
                        //
                        // Sebelumnya layar menampilkan `***` tetapi kedua
                        // tombol ekspor mencetak angka aslinya, jadi
                        // penyamarannya bisa dilewati dengan satu klik.
                        // Penyamaran yang punya pintu belakang bukan
                        // penyamaran.
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $disamarkan = \App\Models\MaterialStockTake::isCounting();

                            return response()->streamDownload(function () use ($records, $disamarkan) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    __('Item Code'), __('Item Name'), __('Category'),
                                    __('Unit'), __('Actual stock'), __('Min. Stock'),
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->code ?? '',
                                        $record->name ?? '',
                                        $record->category?->name ?? '',
                                        $record->unit?->name ?? '',
                                        $disamarkan ? '***' : ($record->qty ?? ''),
                                        $record->min_stock ?? ''
                                    ]));
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
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.material-stocks-pdf', [
                                'records' => $records,
                                'masked' => \App\Models\MaterialStockTake::isCounting(),
                                'title' => __('Material Stocks')
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export_material_stocks.pdf');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('material_category_id')
                    ->relationship('category', 'name')
                    ->label(__('Category')),
                // Ikut ditutup saat opname berjalan.
                //
                // Menyaring "di bawah minimum" memakai angka yang sedang
                // disamarkan sama saja membocorkannya: daftar yang tersisa
                // sudah menjawab pertanyaan yang seharusnya dijawab oleh
                // hitungan fisik.
                Tables\Filters\Filter::make('below_min_stock')
                    ->label(__('Below Min. Stock'))
                    ->visible(fn (): bool => ! \App\Models\MaterialStockTake::isCounting())
                    ->query(fn (Builder $query) => $query->whereRaw('(SELECT COALESCE(SUM(qty), 0) FROM material_stocks WHERE material_id = materials.id) < materials.min_stock')),
            ])
            ->actions([
                // Read-only, clickable row handles navigation
            ])
            ->bulkActions([
                // Read-only stock levels
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->defaultSort('id', 'desc');
    }

    public static function getRecordUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return null;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialStocks::route('/'),
            'view' => Pages\ViewMaterialStock::route('/{record}'),
        ];
    }
}
