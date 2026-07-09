<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;
use App\Models\MaterialRequisition;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Support\RawJs;
class MaterialRequisitionResource extends Resource
{
    protected static ?string $model = MaterialRequisition::class;
    protected static ?int $navigationSort = 11;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Material Request';
    protected static ?string $modelLabel = 'Material Request';

    public static function parseNumber($value): float
    {
        if (blank($value)) return 0.0;
        $val = (string) $value;

        if (preg_match('/^-?\d+(\.\d{1,2})?$/', $val)) {
            return (float) $val;
        }

        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
        return (float) $val;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Header Information')
                    ->schema([
                        Forms\Components\DatePicker::make('due_date')
                            ->label('Due Date')
                            ->required()
                            ->default(now())
                            ->disabled(fn($record) => $record && $record->status !== 'Requested')
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->columnSpan(12),

                        Forms\Components\Hidden::make('user_id')->default(fn() => Auth::id()),
                        Forms\Components\Hidden::make('total_amount'),
                        Forms\Components\Hidden::make('tax_amount')->default(0),
                    ])->columns(12),

                Forms\Components\Section::make('Item Details')
                    ->schema([
                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('col_material')
                                    ->label(__('Material'))
                                    ->columnSpan(['default' => 12, 'md' => 3]),
                                Forms\Components\Placeholder::make('col_qty')
                                    ->label(__('Qty'))
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                Forms\Components\Placeholder::make('col_price')
                                    ->label(__('Price'))
                                    ->columnSpan(['default' => 6, 'md' => fn ($livewire) => ($livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord) ? 3 : 2]),
                                Forms\Components\Placeholder::make('col_item_total')
                                    ->label(__('Subtotal'))
                                    ->columnSpan(['default' => 6, 'md' => 2])
                                    ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                Forms\Components\Placeholder::make('col_note')
                                    ->label(__('Notes'))
                                    ->columnSpan(['default' => 6, 'md' => fn ($livewire) => ($livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord) ? 4 : 3]),
                            ])
                            ->extraAttributes(['class' => 'hidden md:grid']),

                        Forms\Components\Repeater::make('items')
                            ->hiddenLabel()
                            ->reorderableWithDragAndDrop(false)
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->options(fn() => \App\Models\Material::orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->hiddenLabel()
                                    ->placeholder('Pilih Material...')
                                    ->columnSpan(['default' => 12, 'md' => 3])
                                    ->live(),

                                Forms\Components\TextInput::make('qty')
                                    ->required()
                                    ->hiddenLabel()
                                    ->placeholder('Qty')
                                    ->default(0)
                                    ->suffix(function (Forms\Get $get) {
                                        if ($get('material_id')) {
                                            return \App\Models\Material::find($get('material_id'))?->unit?->name;
                                        }
                                        return null;
                                    })
                                    ->extraInputAttributes(['x-on:focus' => '$el.select()', 'class' => 'text-right'])
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->stripCharacters('.')
                                    ->numeric()
                                    ->columnSpan(['default' => 6, 'md' => 2]),

                                Forms\Components\TextInput::make('price')
                                    ->hiddenLabel()
                                    ->placeholder('Harga')
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->extraInputAttributes(['x-on:focus' => '$el.select()', 'class' => 'text-right'])
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->stripCharacters('.')
                                    ->numeric()
                                    ->columnSpan(['default' => 6, 'md' => fn ($livewire) => ($livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord) ? 3 : 2]),

                                Forms\Components\TextInput::make('item_total')
                                    ->hiddenLabel()
                                    ->placeholder('Subtotal')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord)
                                    ->afterStateHydrated(function ($component, $get) {
                                        $qty = self::parseNumber($get('qty'));
                                        $price = self::parseNumber($get('price'));
                                        $component->state(number_format($qty * $price, 0, ',', '.'));
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 2]),

                                Forms\Components\TextInput::make('note')
                                    ->hiddenLabel()
                                    ->placeholder('Notes')
                                    ->columnSpan(['default' => 12, 'md' => fn ($livewire) => ($livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord) ? 4 : 3]),
                            ])
                            ->columns(12),
                    ]),

                Forms\Components\Section::make('Summary')
                    ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord)
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(function ($get) {
                                        $items = $get('items') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += self::parseNumber($item['qty'] ?? 0) * self::parseNumber($item['price'] ?? 0);
                                        }
                                        return 'Rp ' . number_format($total, 0, ',', '.');
                                    }),

                                Forms\Components\Placeholder::make('tax_display')
                                    ->label('Tax / PPN (11%)')
                                    ->content(function ($get) {
                                        $supplierId = $get('supplier_id');
                                        $supplier = \App\Models\Supplier::find($supplierId);
                                        $hasTax = $supplier ? $supplier->is_tax_11 : false;

                                        if (!$hasTax) return 'Rp 0 (Non-PKP)';

                                        $items = $get('items') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += self::parseNumber($item['qty'] ?? 0) * self::parseNumber($item['price'] ?? 0);
                                        }
                                        $tax = $total * 0.11;
                                        return 'Rp ' . number_format($tax, 0, ',', '.');
                                    }),

                                Forms\Components\Placeholder::make('grand_total_display')
                                    ->label('Grand Total')
                                    ->content(function ($get) {
                                        $supplierId = $get('supplier_id');
                                        $supplier = \App\Models\Supplier::find($supplierId);
                                        $hasTax = $supplier ? $supplier->is_tax_11 : false;

                                        $items = $get('items') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += self::parseNumber($item['qty'] ?? 0) * self::parseNumber($item['price'] ?? 0);
                                        }

                                        $tax = $hasTax ? ($total * 0.11) : 0;
                                        $grandTotal = $total + $tax;

                                        return 'Rp ' . number_format($grandTotal, 0, ',', '.');
                                    })
                                    ->extraAttributes(['class' => 'font-bold text-lg text-primary-600']),
                            ])
                            ->columnSpan(12),
                    ])->columns(12),

                Forms\Components\Section::make('Rejection Info')
                    ->description('Informasi alasan penolakan atau revisi request ini.')
                    ->aside()
                    ->schema([
                        Forms\Components\Placeholder::make('reject_note')
                            ->label('Alasan')
                            ->content(fn($record) => $record ? $record->reject_note : '-')
                            ->extraAttributes(['class' => 'text-danger-600 font-bold px-4 py-3 bg-danger-50 border border-danger-300 rounded-lg']),
                    ])
                    ->visible(fn($record) => $record && in_array($record->status, ['Rejected', 'Returned to Purchasing']))
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordAction(null)
            ->recordUrl(function ($record) {
                $user = auth()->user();
                $canView = $user->isProgrammer() ||
                    ($record->status === 'Requested' && $user->hasPermission('review_material_requisitions')) ||
                    (in_array($record->status, ['Pending Finance', 'Returned to Purchasing', 'PO Created']));
                return $canView ? static::getUrl('view', ['record' => $record]) : null;
            })
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('No.')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Request Date')
                    ->date('d-M-Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requester'),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Requested' => 'gray',
                        'Pending Finance' => 'warning',
                        'Returned to Purchasing' => 'danger',
                        'PO Created' => 'success',
                        'Rejected' => 'danger',
                        default => 'info',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Requester')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Requested' => 'Requested',
                        'Pending Finance' => 'Pending Finance',
                        'Returned to Purchasing' => 'Returned to Purchasing',
                        'PO Created' => 'PO Created',
                        'Rejected' => 'Rejected',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From Date')
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until Date')
                            ->default(now()),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when($data['created_from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
            ])
            ->headerActions([
                \Filament\Tables\Actions\ActionGroup::make([
                    \Filament\Tables\Actions\ExportAction::make('excel')
                        ->label('Excel')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->exporter(\App\Filament\Exports\MaterialRequisitionExporter::class)
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    \Filament\Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.parent-records-pdf', [
                                'records' => $records,
                                'title' => 'Material Requisitions'
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
                // Clean UI: Actions moved to View Page
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialRequisitions::route('/'),
            'create' => Pages\CreateMaterialRequisition::route('/create'),
            'detail-list' => Pages\ListMaterialRequisitionDetails::route('/detail-list'),
            'view' => Pages\ViewMaterialRequisition::route('/{record}'),
            'review' => Pages\ReviewMaterialRequisition::route('/{record}/review'),
            'finance-approve' => Pages\ApproveFinanceMaterialRequisition::route('/{record}/finance-approve'),
            'edit' => Pages\EditMaterialRequisition::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('REQUEST');
    }
}
