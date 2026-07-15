<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseProductResource\Pages;
use App\Models\PurchaseProduct;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\RawJs;

class PurchaseProductResource extends Resource
{
    protected static ?string $model = PurchaseProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 11;
    protected static ?string $navigationLabel = 'PO Product';
    protected static ?string $modelLabel = 'PO Product';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Header Information')
                    ->schema([
                        Forms\Components\TextInput::make('po_number')
                            ->label('PO Number')
                            ->disabled()
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\DatePicker::make('po_date')
                            ->label('PO Date')
                            ->disabled()
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->disabled()
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\Select::make('approved_by')
                            ->label('Approved By')
                            ->relationship('approver', 'name')
                            ->disabled()
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->disabled()
                            ->columnSpan(12),
                    ])->columns(12),

                Forms\Components\Section::make('Item Details')
                    ->schema([
                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('col_product')
                                    ->label(__('Product'))
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                                Forms\Components\Placeholder::make('col_qty')
                                    ->label(__('Qty'))
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                Forms\Components\Placeholder::make('col_price')
                                    ->label(__('Price'))
                                    ->columnSpan(['default' => 6, 'md' => 3]),
                                Forms\Components\Placeholder::make('col_subtotal')
                                    ->label(__('Subtotal'))
                                    ->columnSpan(['default' => 12, 'md' => 3]),
                            ])
                            ->extraAttributes(['class' => 'hidden md:grid']),

                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->disabled()
                                    ->hiddenLabel()
                                    ->columnSpan(['default' => 12, 'md' => 4]),

                                Forms\Components\TextInput::make('qty')
                                    ->disabled()
                                    ->hiddenLabel()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->formatStateUsing(fn($state) => number_format($state ?? 0, 2, ',', '.'))
                                    ->columnSpan(['default' => 6, 'md' => 2]),

                                Forms\Components\TextInput::make('price')
                                    ->hiddenLabel()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->formatStateUsing(fn($state) => number_format($state ?? 0, 0, ',', '.'))
                                    ->columnSpan(['default' => 6, 'md' => 3]),

                                Forms\Components\TextInput::make('subtotal')
                                    ->hiddenLabel()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->formatStateUsing(fn($state) => number_format($state ?? 0, 0, ',', '.'))
                                    ->columnSpan(['default' => 12, 'md' => 3]),
                            ])
                            ->columns(12),
                    ]),

                Forms\Components\Section::make('Summary')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('grand_total_display')
                                    ->label('Grand Total')
                                    ->content(function ($record) {
                                        return 'Rp ' . number_format($record ? $record->total_amount : 0, 0, ',', '.');
                                    })
                                    ->extraAttributes(['class' => 'font-bold text-lg text-primary-600']),
                            ])
                            ->columnSpan(12),
                    ])->columns(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordAction(null)
            ->recordUrl(fn($record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('po_date')
                    ->label('PO Date')
                    ->date('d-M-Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\TextColumn::make('approver.name')
                    ->label('Approved By'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('IDR', locale: 'id'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('po_date')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        $from = $data['created_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['created_until'] ?? now()->toDateString();

                        return $query
                            ->when($from, fn($q, $date) => $q->whereDate('po_date', '>=', $date))
                            ->when($until, fn($q, $date) => $q->whereDate('po_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['created_from'])->format('d M Y');
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['created_until'])->format('d M Y');
                        }
                        return $indicators;
                    })
            ])
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
                                    'Date', 'Document No.', 'Supplier', 'Status', 'User', 'Total Amount (Rp)', 'Notes'
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->created_at ?? '',
                                        $record->document_number ?? '',
                                        $record->supplier?->name ?? '',
                                        $record->status ?? '',
                                        $record->user?->name ?? '',
                                        $record->total_amount ?? '',
                                        $record->note ?? ''
                                    ]));
                                }
                                $writer->close();
                            }, 'excel.xlsx');
                        }),
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    \Filament\Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.parent-records-pdf', [
                                'records' => $records,
                                'title' => 'Purchase Products'
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export.pdf');
                        }),
                ])
                ->label('Export Data')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->actions([
                // Clean table UI
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseProducts::route('/'),
            'detail-list' => Pages\PurchaseProductDetailList::route('/detail-list'),
            'view' => Pages\ViewPurchaseProduct::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('PURCHASE ORDER');
    }
}
