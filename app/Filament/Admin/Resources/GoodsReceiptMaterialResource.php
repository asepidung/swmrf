<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages;
use App\Models\GoodsReceiptMaterial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\RawJs;

use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptMaterialResource extends Resource
{
    protected static ?string $model = GoodsReceiptMaterial::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?int $navigationSort = 15;
    protected static ?string $navigationLabel = 'Material Receipt';
    protected static ?string $modelLabel = 'Material Receipt';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Goods Receipt Information'))
                    ->schema([
                        Forms\Components\TextInput::make('gr_number')
                            ->label(__('GR Number'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn(['view', 'edit']),
                        Forms\Components\TextInput::make('po_number_display')
                            ->label(__('PO Number'))
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->purchaseMaterial?->po_number ?? '-'),
                        Forms\Components\TextInput::make('supplier_name_display')
                            ->label(__('Supplier'))
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->supplier?->name ?? '-'),
                        Forms\Components\DatePicker::make('receive_date')
                            ->label(__('Receive Date'))
                            ->required(),
                        Forms\Components\TextInput::make('sj_number')
                            ->label(__('Surat Jalan Number'))
                            ->required(),
                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->columnSpanFull(),
                    ])->columns(3),
                
                Forms\Components\Section::make(__('Materials Received'))
                    ->schema([
                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(5)
                            ->schema([
                                Forms\Components\Placeholder::make('col_material')->label(__('Material')),
                                Forms\Components\Placeholder::make('col_po_qty')->label(__('Qty PO')),
                                Forms\Components\Placeholder::make('col_price')->label(__('Price')),
                                Forms\Components\Placeholder::make('col_qty_received')->label(__('Qty Received')),
                                Forms\Components\Placeholder::make('col_subtotal')->label(__('Subtotal')),
                            ])
                            ->extraAttributes(['class' => 'hidden md:grid']),

                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->relationship('material', 'name')
                                    ->label(__('Material'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Material'))
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('po_qty')
                                    ->label(__('Qty PO'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Qty PO'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->suffix(fn ($record) => $record?->material?->unit?->name)
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record && $record->goodsReceiptMaterial) {
                                            $poItem = \App\Models\PurchaseMaterialItem::where('purchase_material_id', $record->goodsReceiptMaterial->purchase_material_id)
                                                ->where('material_id', $record->material_id)
                                                ->first();
                                            $component->state($poItem ? number_format($poItem->qty, 2, ',', '.') : '0,00');
                                        }
                                    }),
                                Forms\Components\TextInput::make('price')
                                    ->label(__('Price'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Price'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->afterStateHydrated(function ($component, $state) {
                                        $component->state(number_format((float)$state, 0, ',', '.'));
                                    }),
                                Forms\Components\TextInput::make('qty_received')
                                    ->label(__('Qty Received'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Qty Received'))
                                    ->numeric()
                                    ->extraInputAttributes(['x-on:focus' => '$el.select()', 'class' => 'text-right'])
                                    ->required()
                                    ->live(onBlur: true)
                                    ->suffix(fn ($record) => $record?->material?->unit?->name),
                                Forms\Components\Placeholder::make('item_subtotal')
                                    ->label(__('Subtotal'))
                                    ->hiddenLabel()
                                    ->content(function (Forms\Get $get) {
                                        $qty = (float) str_replace(['.', ','], ['', '.'], $get('qty_received') ?? '0');
                                        $price = (float) str_replace(['.', ','], ['', '.'], $get('price') ?? '0');
                                        return 'Rp ' . number_format($qty * $price, 0, ',', '.');
                                    }),
                            ])
                            ->columns(5)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement(),
                    ]),

                Forms\Components\Section::make(__('Summary & Actions'))
                    ->schema([
                        Forms\Components\Placeholder::make('grand_total_summary')
                            ->label('')
                            ->hiddenLabel()
                            ->content(function (Forms\Get $get, ?GoodsReceiptMaterial $record) {
                                $items = $get('items') ?? [];
                                $subtotal = 0;
                                foreach ($items as $item) {
                                    $qty = (float) str_replace(['.', ','], ['', '.'], $item['qty_received'] ?? '0');
                                    $price = (float) str_replace(['.', ','], ['', '.'], $item['price'] ?? '0');
                                    $subtotal += $qty * $price;
                                }

                                // Check tax setting of the supplier
                                $isTax11 = false;
                                if ($record && $record->supplier) {
                                    $isTax11 = (bool) $record->supplier->is_tax_11;
                                } else {
                                    $poId = $record?->purchase_material_id;
                                    if ($poId) {
                                        $po = \App\Models\PurchaseMaterial::find($poId);
                                        if ($po && $po->supplier) {
                                            $isTax11 = (bool) $po->supplier->is_tax_11;
                                        }
                                    }
                                }

                                $tax = $isTax11 ? ($subtotal * 0.11) : 0;
                                $netTotal = $subtotal + $tax;

                                $taxLabel = $isTax11 ? 'PPN (11%)' : 'PPN (0% - Non Tax)';

                                return new HtmlString("
                                    <div class='flex flex-col gap-2 max-w-md ml-auto border-t pt-4 dark:border-gray-700'>
                                        <div class='flex justify-between text-sm'>
                                            <span class='text-gray-500 dark:text-gray-400'>Total Sebelum Pajak (Subtotal):</span>
                                            <span class='font-semibold'>Rp " . number_format($subtotal, 0, ',', '.') . "</span>
                                        </div>
                                        <div class='flex justify-between text-sm'>
                                            <span class='text-gray-500 dark:text-gray-400'>{$taxLabel}:</span>
                                            <span class='font-semibold'>Rp " . number_format($tax, 0, ',', '.') . "</span>
                                        </div>
                                        <div class='flex justify-between text-base border-t pt-2 dark:border-gray-600 font-bold text-primary-600 dark:text-primary-400'>
                                            <span>Total Setelah Pajak (Net Total):</span>
                                            <span>Rp " . number_format($netTotal, 0, ',', '.') . "</span>
                                        </div>
                                    </div>
                                ");
                            }),

                        Forms\Components\Radio::make('po_status_action')
                            ->label(__('Tindakan untuk Sisa PO'))
                            ->options([
                                'partial' => 'Tetap Partial (Sisa barang akan ditunggu di penerimaan berikutnya)',
                                'completed' => 'Tutup PO (Sisa barang dianggap hangus / selesai)',
                            ])
                            ->default('partial')
                            ->required()
                            ->visible(function (Forms\Get $get, ?GoodsReceiptMaterial $record) {
                                if (!$record) return false;
                                
                                // Calculate total PO qty
                                $poItems = \App\Models\PurchaseMaterialItem::where('purchase_material_id', $record->purchase_material_id)->get();
                                $totalPoQty = $poItems->sum('qty');
                                
                                // Calculate total received from OTHER GRs of this PO
                                $otherGrQty = \App\Models\GoodsReceiptMaterialItem::whereHas('goodsReceiptMaterial', function ($query) use ($record) {
                                    $query->where('purchase_material_id', $record->purchase_material_id)
                                          ->where('id', '!=', $record->id);
                                })->sum('qty_received');
                                
                                // Calculate current form's received qty
                                $items = $get('items') ?? [];
                                $currentFormQty = 0;
                                foreach ($items as $item) {
                                    $currentFormQty += (float) str_replace(['.', ','], ['', '.'], $item['qty_received'] ?? '0');
                                }
                                
                                $newTotalReceived = $otherGrQty + $currentFormQty;
                                
                                return $newTotalReceived < $totalPoQty;
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gr_number')
                    ->label(__('GR Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('receive_date')
                    ->label(__('Receive Date'))
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('sj_number')
                    ->label(__('Surat Jalan'))
                    ->searchable()
                    ->color(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('purchaseMaterial.po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('Created By'))
                    ->badge()
                    ->color(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(
                fn (GoodsReceiptMaterial $record): string => $record->trashed() 
                    ? Pages\ViewGoodsReceiptMaterial::getUrl(['record' => $record]) 
                    : Pages\EditGoodsReceiptMaterial::getUrl([$record->id]),
            )
            ->headerActions([
                \Filament\Tables\Actions\ActionGroup::make([
                    \Filament\Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'id', 'gr_number', 'purchase_material_id', 'supplier_id', 'receive_date', 'sj_number', 'note', 'created_by', 'deleted_at', 'created_at', 'updated_at'
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->id ?? '',
                                        $record->gr_number ?? '',
                                        $record->purchase_material_id ?? '',
                                        $record->supplier_id ?? '',
                                        $record->receive_date ?? '',
                                        $record->sj_number ?? '',
                                        $record->note ?? '',
                                        $record->created_by ?? '',
                                        $record->deleted_at ?? '',
                                        $record->created_at ?? '',
                                        $record->updated_at ?? ''
                                    ]));
                                }
                                $writer->close();
                            }, 'excel.xlsx');
                        }),
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    \Filament\Tables\Actions\Action::make('pdf')
                        ->label(__('PDF'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            // Optional: Implement PDF generation
                            // return response()->streamDownload(fn () => print($pdf->output()), 'export.pdf');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_gr_materials')),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label(__('Supplier')),
                Tables\Filters\Filter::make('receive_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('From'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('Until'))
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('receive_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('receive_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'border-s-2 border-danger-600 dark:border-danger-400 bg-danger-50 dark:bg-danger-900/50' : null)
            ->defaultSort('id', 'desc');
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
            'index' => Pages\ListGoodsReceiptMaterials::route('/'),
            'drafts' => Pages\ListPendingPoMaterials::route('/drafts'),
            'create' => Pages\CreateGoodsReceiptMaterial::route('/create'),
            'detail-list' => Pages\GoodsReceiptMaterialDetailList::route('/detail-list'),
            'view' => Pages\ViewGoodsReceiptMaterial::route('/{record}'),
            'edit' => Pages\EditGoodsReceiptMaterial::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('GOODS RECEIPT');
    }
}
