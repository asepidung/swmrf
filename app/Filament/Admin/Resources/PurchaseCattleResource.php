<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseCattleResource\Pages;
use App\Filament\Admin\Resources\PurchaseCattleResource\RelationManagers;
use App\Models\PurchaseCattle;
use App\Support\TrashedRecords;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PurchaseCattleResource extends Resource
{
    protected static ?string $model = PurchaseCattle::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function getNavigationGroup(): ?string
    {
        return __('PURCHASE ORDER');
    }

    public static function getModelLabel(): string
    {
        return __('PO Cattle');
    }

    public static function getPluralModelLabel(): string
    {
        return __('PO Cattles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Header Info'))->schema([
                    Forms\Components\Select::make('supplier_id')
                        ->relationship('supplier', 'name')
                        ->required()
                        ->autofocus()
                        ->label(__('Supplier')),
                    Forms\Components\DatePicker::make('shipping_date')
                        ->required()
                        ->label(__('Shipping Date')),
                    Forms\Components\Textarea::make('summary_note')
                        ->label(__('Summary Note'))
                        ->columnSpanFull(),
                ])->columns(2),
                
                Forms\Components\Section::make(__('Cattle Details'))->schema([
                    // Clean Repeater Header UI
                    Forms\Components\Grid::make(4)
                        ->schema([
                            Forms\Components\Placeholder::make('col_category')->label(__('Category')),
                            Forms\Components\Placeholder::make('col_qty')->label(__('Qty / Head')),
                            Forms\Components\Placeholder::make('col_price')->label(__('Price / Kg')),
                            Forms\Components\Placeholder::make('col_item_notes')->label(__('Item Note')),
                        ])
                        ->extraAttributes(['class' => 'swm-wide-only']),

                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->reorderableWithDragAndDrop(false)
                        ->defaultItems(0)
                        ->schema([
                            Forms\Components\Select::make('cattle_class_id')
                                ->relationship('cattleClass', 'name')
                                ->required()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(table: 'cattle_classes', column: 'name')
                                        ->label(__('Name')),
                                ])
                                ->placeholder(__('Category'))
                                ->label('')
                                ->hiddenLabel(),
                            // Tanpa ->numeric(). Pemanggilan itu membuat input
                            // menjadi type=number lengkap dengan tombol panah,
                            // dan jumlah ekor yang tergeser tanpa disadari
                            // mengubah nilai seluruh pembelian.
                            Forms\Components\TextInput::make('qty')
                                ->required()
                                ->stripCharacters('.')
                                ->extraInputAttributes(['inputmode' => 'numeric', 'class' => 'text-right'])
                                ->rules(['integer', 'min:1'])
                                ->placeholder(__('Qty / Head'))
                                ->label('')
                                ->hiddenLabel(),

                            // Harga per kilo: bertombol panah DAN tanpa
                            // pemisah ribuan, sehingga 1500000 terbaca sebagai
                            // deretan angka yang harus dihitung dengan mata.
                            //
                            // Topengnya disamakan dengan kolom uang lain di
                            // aplikasi, dan stripCharacters('.') yang sudah ada
                            // yang membuang titiknya lagi sebelum tersimpan.
                            Forms\Components\TextInput::make('price')
                                ->required()
                                ->default(0)
                                ->prefix('Rp')
                                ->stripCharacters('.')
                                ->formatStateUsing(fn ($state): ?string => $state === null || $state === ''
                                    ? null
                                    : number_format((float) $state, 0, ',', '.'))
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->rules(['integer', 'min:0'])
                                ->extraInputAttributes([
                                    'x-on:focus' => '$el.select()',
                                    'inputmode' => 'numeric',
                                    'class' => 'text-right',
                                ])
                                ->placeholder(__('Price / Kg'))
                                ->label('')
                                ->hiddenLabel(),
                            Forms\Components\TextInput::make('item_notes')
                                ->placeholder(__('ITEM NOTE'))
                                ->label('')
                                ->hiddenLabel(),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->hiddenLabel()
                        ->addActionLabel(__('Add Cattle'))
                ])
            ])
            ->disabled(fn (?PurchaseCattle $record) => $record && $record->receivings()->exists());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('PO Date'))
                    ->date('d-M-Y')
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('shipping_date')
                    ->label(__('Shipping Date'))
                    ->date('d-M-Y')
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('items_sum_qty')
                    ->sum('items', 'qty')
                    ->label(__('Total Head'))
                    ->numeric()
                    ->badge()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : 'info')
                    ->suffix(' Ekor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('summary_note')
                    ->label(__('Note'))
                    ->limit(50)
                    ->searchable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
            ])
            ->recordUrl(
                fn (Model $record): string => $record->trashed() 
                    ? Pages\ViewPurchaseCattle::getUrl(['record' => $record]) 
                    : Pages\EditPurchaseCattle::getUrl(['record' => $record]),
            )
            ->recordClasses(fn (Model $record) => $record->trashed() ? 'border-s-2 border-danger-600 dark:border-danger-400 bg-danger-50 dark:bg-danger-900/50' : null)
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_purchase_cattles')),
                SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label(__('Supplier')),
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('From'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('Until'))
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            // Ekspor Excel dan PDF wajib untuk modul transaksional
            // (project.md). Halaman ini sebelumnya punya headerActions kosong,
            // jadi satu-satunya ekspor ada di halaman Detail List.
            //
            // Excel sengaja TIDAK memakai Filament Exporter -- ia memicu queue
            // yang lambat, dan di lingkungan ini tidak ada worker sama sekali.
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->with(['supplier', 'items'])->get();

                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'PO Number', 'PO Date', 'Shipping Date', 'Supplier', 'Total Head', 'Note',
                                ]));

                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->document_number ?? '',
                                        optional($record->created_at)->format('Y-m-d') ?? '',
                                        optional($record->shipping_date)->format('Y-m-d') ?? '',
                                        $record->supplier?->name ?? '',
                                        (int) $record->items->sum('qty'),
                                        $record->summary_note ?? '',
                                    ]));
                                }

                                $writer->close();
                            }, 'purchase-cattles.xlsx');
                        }),

                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->with(['supplier', 'items'])->get();

                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.purchase-cattles-pdf', [
                                'records' => $records,
                                'title' => __('PO Cattles'),
                            ]);

                            return response()->streamDownload(fn () => print($pdf->output()), 'purchase-cattles.pdf');
                        }),
                ])
                    ->label(__('Export Data'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->button()
                    ->color('success'),
            ])
            ->actions([
                // No action buttons on index page
            ])
            // Tanpa bulk delete, sama seperti PO Beef dan PO Material.
            //
            // Penjagaan hapus hidup di `PurchaseCattle::deleting()` sebagai
            // Exception. Lewat tombol per-baris ia tertahan lebih dulu oleh
            // `->disabled()`, tapi penghapusan massal menembusnya dan
            // Exception-nya sampai ke pengguna sebagai halaman error mentah.
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListPurchaseCattle::route('/'),
            'create' => Pages\CreatePurchaseCattle::route('/create'),
            'detail-list' => Pages\PurchaseCattleDetailList::route('/detail-list'),
            'view' => Pages\ViewPurchaseCattle::route('/{record}'),
            'edit' => Pages\EditPurchaseCattle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_purchase_cattles',
        );
    }
}
