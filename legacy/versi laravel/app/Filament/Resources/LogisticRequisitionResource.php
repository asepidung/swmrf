<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogisticRequisitionResource\Pages;
use App\Models\LogisticRequisition;
use App\Models\LogisticPurchaseOrder;
use App\Models\LogisticPurchaseOrderItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Filament\Support\RawJs;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Illuminate\Support\Facades\DB;

class LogisticRequisitionResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = LogisticRequisition::class;
    protected static ?string $navigationGroup = 'REQUEST';
    protected static ?int $navigationSort = 11;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Logistic Request';
    protected static ?string $modelLabel = 'Logistic Request';

    public static function getPermissionPrefixes(): array
    {
        return ['view_any', 'view', 'create', 'update', 'delete', 'review', 'approve'];
    }

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
                /* Bagian Informasi Header */
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
                            ->live() /* Memperbarui tampilan secara langsung saat supplier dipilih */
                            ->columnSpan(['default' => 12, 'md' => 6]),

                        /* Note dipindah ke Header Information sesuai permintaan */
                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->columnSpan(12),

                        Forms\Components\Hidden::make('user_id')->default(fn() => Auth::id()),
                        Forms\Components\Hidden::make('total_amount'),
                    ])->columns(12),

                /* Bagian Detail Item */
                Forms\Components\Section::make('Item Details')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\Select::make('logistic_item_id')
                                    ->relationship('item', 'name')
                                    ->searchable() /* Menambahkan fitur ketik */
                                    ->preload()    /* Menambahkan fitur preload */
                                    ->required()
                                    ->hiddenLabel()
                                    ->placeholder('Pilih Item...')
                                    ->columnSpan(['default' => 12, 'md' => 4]),

                                /* Qty, Price, dan Subtotal SAMA PERSIS dengan kode lu, nggak gue sentuh */
                                Forms\Components\TextInput::make('qty')
                                    ->required()
                                    ->hiddenLabel()
                                    ->placeholder('Qty')
                                    ->formatStateUsing(fn($state) => $state ? number_format(self::parseNumber($state), 2, ',', '.') : '')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 2)'))
                                    ->dehydrateStateUsing(fn($state) => self::parseNumber($state))
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $qty = self::parseNumber($state);
                                        $price = self::parseNumber($get('price'));
                                        $set('item_total', number_format($qty * $price, 0, ',', '.'));
                                    })
                                    ->columnSpan(['default' => 6, 'md' => 2]),

                                Forms\Components\TextInput::make('price')
                                    ->hiddenLabel()
                                    ->placeholder('Harga')
                                    ->prefix('Rp')
                                    ->formatStateUsing(fn($state) => $state ? number_format(self::parseNumber($state), 0, ',', '.') : '')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->dehydrateStateUsing(fn($state) => self::parseNumber($state))
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $price = self::parseNumber($state);
                                        $qty = self::parseNumber($get('qty'));
                                        $set('item_total', number_format($qty * $price, 0, ',', '.'));
                                    })
                                    ->columnSpan(['default' => 6, 'md' => 3]),

                                Forms\Components\TextInput::make('item_total')
                                    ->hiddenLabel()
                                    ->placeholder('Subtotal')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->afterStateHydrated(function ($component, $get) {
                                        $qty = self::parseNumber($get('qty'));
                                        $price = self::parseNumber($get('price'));
                                        $component->state(number_format($qty * $price, 0, ',', '.'));
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 3]),
                            ])
                            ->columns(12)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, $set) {
                                $total = collect($state)->sum(function ($item) {
                                    $qty = self::parseNumber($item['qty'] ?? 0);
                                    $price = self::parseNumber($item['price'] ?? 0);
                                    return $qty * $price;
                                });
                                $set('total_amount', $total);
                            }),
                    ]),

                /* Bagian Ringkasan dan Perhitungan Pajak */
                Forms\Components\Section::make('Summary')
                    ->schema([
                        /* Dibuat Grid 3 kolom ke samping agar rapi */
                        Forms\Components\Grid::make(3)
                            ->schema([
                                /* Menampilkan subtotal keseluruhan item */
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

                                /* Menghitung pajak berdasarkan status has_tax pada supplier */
                                Forms\Components\Placeholder::make('tax_display')
                                    ->label('Tax / PPN (11%)')
                                    ->content(function ($get) {
                                        $supplierId = $get('supplier_id');
                                        $supplier = \App\Models\Supplier::find($supplierId);
                                        $hasTax = $supplier ? $supplier->has_tax : false;

                                        if (!$hasTax) return 'Rp 0 (Non-PKP)';

                                        $items = $get('items') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += self::parseNumber($item['qty'] ?? 0) * self::parseNumber($item['price'] ?? 0);
                                        }
                                        $tax = $total * 0.11;
                                        return 'Rp ' . number_format($tax, 0, ',', '.');
                                    }),

                                /* Menampilkan total keseluruhan setelah perhitungan pajak */
                                Forms\Components\Placeholder::make('grand_total_display')
                                    ->label('Grand Total')
                                    ->content(function ($get) {
                                        $supplierId = $get('supplier_id');
                                        $supplier = \App\Models\Supplier::find($supplierId);
                                        $hasTax = $supplier ? $supplier->has_tax : false;

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
                            ->columnSpan(12), /* Supaya memenuhi lebar layar horizontal */
                    ])->columns(12),

                /* Bagian Informasi Penolakan atau Revisi */
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
                $canView = $user->hasRole('super_admin') ||
                    ($record->status === 'Requested' && $user->can('review_logistic::requisition')) ||
                    (in_array($record->status, ['Pending Finance', 'Returned to Purchasing', 'PO Created']));
                return $canView ? static::getUrl('view', ['record' => $record]) : null;
            })
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('No.')
                    ->searchable()
                    ->weight('bold'),

                /* Menampilkan tanggal pembuatan request */
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
            ->actions([
                Tables\Actions\Action::make('print_dynamic')
                    ->icon('heroicon-s-printer')
                    ->color(fn($record) => $record->status === 'PO Created' ? 'success' : 'primary')
                    ->tooltip(fn($record) => $record->status === 'PO Created' ? 'Print Purchase Order' : 'Print Request')
                    ->iconButton()
                    ->url(function ($record) {
                        if ($record->status === 'PO Created') {
                            $po = \App\Models\LogisticPurchaseOrder::where('logistic_requisition_id', $record->id)->first();
                            return $po ? route('print.logistic-po', ['id' => $po->id]) : '#';
                        }
                        return route('print.logistic-request', ['id' => $record->id]);
                    })
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('review')
                    ->icon('heroicon-s-clipboard-document-check')
                    ->color('warning')
                    ->tooltip('Review Request')
                    ->iconButton()
                    ->visible(function ($record) {
                        /** @var \App\Models\User $user */
                        $user = auth()->user();
                        return in_array($record->status, ['Requested', 'Returned to Purchasing']) && ($user->hasRole('super_admin') || $user->can('review_logistic::requisition'));
                    })
                    ->url(fn($record) => static::getUrl('review', ['record' => $record])),

                Tables\Actions\Action::make('finance_approval')
                    ->icon('heroicon-s-shield-check')
                    ->color('success')
                    ->tooltip('Finance Approval')
                    ->iconButton()
                    ->visible(function ($record) {
                        /** @var \App\Models\User $user */
                        $user = auth()->user();
                        return $record->status === 'Pending Finance' && ($user->hasRole('super_admin') || $user->can('approve_logistic::requisition'));
                    })
                    ->url(fn($record) => static::getUrl('finance-approve', ['record' => $record])),

                Tables\Actions\Action::make('resubmit')
                    ->icon('heroicon-s-arrow-path')
                    ->color('info')
                    ->tooltip('Edit & Re-submit')
                    ->iconButton()
                    ->visible(fn($record) => $record->status === 'Rejected')
                    ->url(fn($record) => static::getUrl('edit', ['record' => $record])),

                Tables\Actions\EditAction::make()->iconButton()->visible(fn($record) => in_array($record->status, ['Requested', 'Returned to Purchasing'])),
                Tables\Actions\DeleteAction::make()->iconButton()->visible(fn($record) => $record->status === 'Requested'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLogisticRequisitions::route('/'),
            'create' => Pages\CreateLogisticRequisition::route('/create'),
            'view' => Pages\ViewLogisticRequisition::route('/{record}'),
            'review' => Pages\ReviewLogisticRequisition::route('/{record}/review'),
            'finance-approve' => Pages\ApproveFinanceLogisticRequisition::route('/{record}/finance-approve'),
            'edit' => Pages\EditLogisticRequisition::route('/{record}/edit'),
        ];
    }

    /* Memproses pembuatan Purchase Order Logistic secara otomatis dari data Request */
    public static function generatePurchaseOrder($record)
    {
        $record->loadMissing(['items', 'supplier']);

        DB::transaction(function () use ($record) {
            $subtotal = $record->total_amount;
            $tax = 0;

            if ($record->supplier && $record->supplier->has_tax) {
                $tax = $subtotal * 0.11;
            }

            $grandTotal = $subtotal + $tax;

            /* Penomoran otomatis Purchase Order Logistic */
            $currentYear2Digit = date('y');
            $currentYear4Digit = date('Y');

            $countThisYear = \App\Models\LogisticPurchaseOrder::whereYear('created_at', $currentYear4Digit)->count();
            $urut = $countThisYear + 1;

            $poNumber = 'SWM/PL#' . $currentYear2Digit . str_pad($urut, 3, '0', STR_PAD_LEFT);

            /* Membuat record Purchase Order dengan po_date sebagai tanggal pengiriman */
            $po = \App\Models\LogisticPurchaseOrder::create([
                'po_number' => $poNumber,
                'logistic_requisition_id' => $record->id,
                'supplier_id' => $record->supplier_id,
                'approved_by' => auth()->id(),
                'po_date' => $record->due_date, // Shipping Date
                'total_amount' => $grandTotal,
                'note' => $record->note,
            ]);

            /* Duplikasi item dari Request ke Purchase Order */
            foreach ($record->items as $item) {
                \App\Models\LogisticPurchaseOrderItem::create([
                    'logistic_purchase_order_id' => $po->id,
                    'logistic_item_id' => $item->logistic_item_id,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'subtotal' => $item->qty * $item->price,
                ]);
            }
        });
    }
}
