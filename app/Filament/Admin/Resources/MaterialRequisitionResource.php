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
                Forms\Components\Section::make(fn() => __('Header Information'))
                    ->schema([
                        Forms\Components\DatePicker::make('due_date')
                            ->label(fn() => __('Due Date'))
                            ->required()
                            ->default(now())
                            ->disabled(fn($record) => $record && $record->status !== 'Requested')
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\Select::make('supplier_id')
                            ->label(fn() => __('Supplier'))
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        Forms\Components\Textarea::make('note')
                            ->label(fn() => __('Note'))
                            ->columnSpan(12),

                        Forms\Components\Hidden::make('user_id')->default(fn() => Auth::id()),
                        Forms\Components\Hidden::make('total_amount'),
                        Forms\Components\Hidden::make('tax_amount')->default(0),
                    ])->columns(12),

                Forms\Components\Section::make(fn() => __('Item Details'))
                    // Pemisah ribuan hidup saat mengetik.
                    //
                    // Listener sengaja dipasang di Section ini, BUKAN di tiap input
                    // di dalam baris Repeater. Alpine jadi tidak pernah menempel ke
                    // elemen baris, sehingga saat sebuah baris dihapus tidak ada yang
                    // perlu di-teardown dan bug "baris zombie" tidak pernah terjadi.
                    // Pola yang sama sudah lama dipakai di modul Sales Order dan
                    // Request Beef.
                    ->extraAttributes([
                        // Papan ketik angka sebagian perangkat mengeluarkan '.' sebagai
                        // tombol desimal, sementara pemformat di bawah hanya menerima ','.
                        // Tanpa ini, mengetik 300.5 di HP tersebut menjadi 3005.
                        // Titik yang diketik pengguna diubah menjadi koma di sini, supaya
                        // pemformat cukup mengenal satu bentuk desimal saja.
                        'x-on:keydown' => '
                            (function (e) {
                                let target = e.target;
                                if (!target || !target.classList.contains("qty-input")) return;
                                if (e.key !== "." && e.key !== ",") return;

                                e.preventDefault();
                                if (target.value.includes(",")) return;

                                let start = target.selectionStart;
                                let end = target.selectionEnd;
                                target.value = target.value.slice(0, start) + "," + target.value.slice(end);
                                target.setSelectionRange(start + 1, start + 1);
                                target.dispatchEvent(new Event("input", { bubbles: true }));
                            })(event)
                        ',
                        'x-on:input' => '
                            (function (e) {
                                let target = e.target;
                                if (!target) return;

                                let isQty = target.classList.contains("qty-input");
                                let isPrice = target.classList.contains("price-input");
                                if (!isQty && !isPrice) return;

                                let raw = target.value;
                                let cleaned = raw.replace(/[^0-9,]/g, "");
                                let parts = cleaned.split(",");
                                let intPart = parts.shift().replace(/^0+(?=\d)/, "");
                                let decPart = parts.length ? parts.join("").slice(0, 2) : null;

                                let formatted = "";
                                if (intPart !== "") {
                                    formatted = new Intl.NumberFormat("de-DE").format(parseInt(intPart, 10));
                                }
                                if (decPart !== null) {
                                    formatted = (formatted === "" ? "0" : formatted) + "," + decPart;
                                }

                                if (target.value === formatted) return;

                                let selectionStart = target.selectionStart;
                                let originalLength = target.value.length;
                                target.value = formatted;
                                let diff = formatted.length - originalLength;
                                target.setSelectionRange(selectionStart + diff, selectionStart + diff);
                                target.dispatchEvent(new Event("input", { bubbles: true }));
                            })(event)
                        ',
                    ])
                    ->schema([
                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('col_material')
                                    ->label(fn() => __('Material'))
                                    ->columnSpan(['default' => 12, 'md' => fn ($livewire) => ($livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord) ? 4 : 3]),
                                Forms\Components\Placeholder::make('col_qty')
                                    ->label(fn() => __('Qty'))
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                Forms\Components\Placeholder::make('col_price')
                                    ->label(fn() => __('Price'))
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                Forms\Components\Placeholder::make('col_item_total')
                                    ->label(fn() => __('Subtotal'))
                                    ->columnSpan(['default' => 6, 'md' => 2])
                                    ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                Forms\Components\Placeholder::make('col_note')
                                    ->label(fn() => __('Notes'))
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
                                    ->placeholder(fn() => __('Pilih Material...'))
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->columnSpan(['default' => 12, 'md' => fn ($livewire) => ($livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord) ? 4 : 3])
                                    ->live(),

                                Forms\Components\TextInput::make('qty')
                                    ->required()
                                    ->rules([
                                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                            if (self::parseNumber($value) <= 0) {
                                                $fail(__('Qty is required and cannot be zero.'));
                                            }
                                        },
                                    ])
                                    ->hiddenLabel()
                                    ->placeholder(fn() => __('Qty'))
                                    ->default(0)
                                    ->suffix(function (Forms\Get $get) {
                                        if ($get('material_id')) {
                                            return \App\Models\Material::find($get('material_id'))?->unit?->name;
                                        }
                                        return null;
                                    })
                                    // Masking numerik bawaan Filament SENGAJA dilepas dari field ini.
                                    // Field itu jadi <input type="number">, yang menolak pemisah
                                    // ribuan sehingga tampil kosong.
                                    ->extraInputAttributes(['x-on:focus' => '$el.select()', 'class' => 'qty-input text-right', 'inputmode' => 'decimal', 'x-on:keydown.enter.prevent' => 'let inputs = Array.from(document.querySelectorAll(".qty-input")); let idx = inputs.indexOf($el); if(idx !== -1 && idx + 1 < inputs.length) { inputs[idx + 1].focus(); }'])
                                    ->columnSpan(['default' => 6, 'md' => 2]),

                                Forms\Components\TextInput::make('price')
                                    ->hiddenLabel()
                                    ->placeholder(fn() => __('Harga'))
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->extraInputAttributes(['x-on:focus' => '$el.select()', 'class' => 'price-input text-right', 'inputmode' => 'numeric', 'x-on:keydown.enter.prevent' => 'let inputs = Array.from(document.querySelectorAll(".price-input")); let idx = inputs.indexOf($el); if(idx !== -1 && idx + 1 < inputs.length) { inputs[idx + 1].focus(); }'])
                                    ->columnSpan(['default' => 6, 'md' => 2]),

                                Forms\Components\TextInput::make('item_total')
                                    ->hiddenLabel()
                                    ->placeholder(fn() => __('Subtotal'))
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
                                    ->placeholder(fn() => __('Notes'))
                                    ->extraInputAttributes(['class' => 'note-input', 'x-on:keydown.enter.prevent' => 'let inputs = Array.from(document.querySelectorAll(".note-input")); let idx = inputs.indexOf($el); if(idx !== -1 && idx + 1 < inputs.length) { inputs[idx + 1].focus(); }'])
                                    ->columnSpan(['default' => 12, 'md' => fn ($livewire) => ($livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord) ? 4 : 3]),
                            ])
                            ->columns(12),
                    ]),

                Forms\Components\Section::make(fn() => __('Summary'))
                    ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord || $livewire instanceof \Filament\Resources\Pages\EditRecord)
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('subtotal_display')
                                    ->label(fn() => __('Subtotal'))
                                    ->content(function ($get) {
                                        $items = $get('items') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += self::parseNumber($item['qty'] ?? 0) * self::parseNumber($item['price'] ?? 0);
                                        }
                                        return 'Rp ' . number_format($total, 0, ',', '.');
                                    }),

                                Forms\Components\Placeholder::make('tax_display')
                                    ->label(fn() => __('Tax / PPN (11%)'))
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
                                    ->label(fn() => __('Grand Total'))
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

                Forms\Components\Section::make(fn() => __('Rejection Info'))
                    ->description('Informasi alasan penolakan atau revisi request ini.')
                    ->aside()
                    ->schema([
                        Forms\Components\Placeholder::make('reject_note')
                            ->label(fn() => __('Alasan'))
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

                // Pemohon selalu boleh membuka dokumennya sendiri, apa pun statusnya.
                // Sebelum ini baris berstatus Rejected tidak bisa diklik sama sekali oleh pemohon.
                $canView = $user->isProgrammer()
                    || $record->user_id === $user->id
                    || $user->hasPermission('review_material_requisitions')
                    || $user->hasPermission('approve_material_requisitions')
                    || in_array($record->status, ['Pending Finance', 'Returned to Purchasing', 'PO Created']);

                return $canView ? static::getUrl('view', ['record' => $record]) : null;
            })
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label(fn() => __('No.'))
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('Request Date'))
                    ->date('d-M-Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label(fn() => __('Requester')),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(fn() => __('Supplier')),

                Tables\Columns\TextColumn::make('note')
                    ->label(fn() => __('Note'))
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
                    ->label(fn() => __('Supplier'))
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(fn() => __('Requester'))
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
                            ->label(fn() => __('From Date'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(fn() => __('Until Date'))
                            ->default(now()),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when($data['created_from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('detail')
                    ->label(fn() => __('Detail'))
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->url(fn() => static::getUrl('detail-list')),
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
            'approve-finance' => Pages\ApproveFinanceMaterialRequisition::route('/{record}/finance-approval'),
            'edit' => Pages\EditMaterialRequisition::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('REQUEST');
    }

    public static function getModelLabel(): string
    {
        return __('Material Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Material Requests');
    }

    public static function getNavigationLabel(): string
    {
        return __('Material Requests');
    }
}
