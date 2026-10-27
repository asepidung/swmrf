<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SalesReturnPlanResource\Pages;
use App\Models\Product;
use App\Models\SalesReturnPlan;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesReturnPlanResource extends Resource
{
    protected static ?string $model = SalesReturnPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function getNavigationLabel(): string
    {
        return __('Sales Return Plans');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('SALES');
    }

    /**
     * Draft (atau Create): header dan item bebas diubah. Submitted:
     * header terkunci total (juga ditegakkan `SalesReturnPlan::booted()`
     * `updating()` -- ini cuma mencegah field-nya TAMPIL bisa diketik),
     * item hanya boleh dinego qty-nya (tidak bisa tambah/hapus produk,
     * lihat `SalesReturnPlanItem::booted()`). Received/Cancelled tidak
     * pernah sampai ke sini -- `EditSalesReturnPlan::mount()` mengalihkan
     * sebelum form ini dirender.
     */
    private static function isNotFullyEditable($livewire): bool
    {
        return ! ($livewire instanceof CreateRecord) && $livewire->getRecord()->status !== SalesReturnPlan::STATUS_DRAFT;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Plan Information'))
                    ->schema([
                        // Retur boleh untuk customer yang kini nonaktif (barangnya dijual dulu).
                        \App\Filament\Support\MasterSelect::customer('customer_id', activeOnly: false)
                            ->label(__('Customer'))
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('delivery_order_id', null);
                            })
                            // SENGAJA tidak ->dehydrated(): field yang
                            // disabled by default TIDAK ikut ke $data,
                            // dan itu justru yang diinginkan di sini --
                            // header Submitted harus TIDAK TERSENTUH
                            // sama sekali oleh handleRecordUpdate().
                            // Memaksanya dehydrated pernah dicoba dan
                            // membongkar bug nyata: DatePicker
                            // menghidrasi `plan_date` lewat konversi
                            // zona waktu yang bergeser satu hari dari
                            // nilai tersimpan, dan mengirim balik nilai
                            // yang "berubah" itu ke update() membuat
                            // SalesReturnPlan::booted() updating()
                            // MENOLAK simpan sama sekali -- padahal
                            // yang diedit cuma qty klaim, bukan
                            // tanggalnya.
                            ->disabled(fn ($livewire): bool => static::isNotFullyEditable($livewire))
                            ->required(),

                        Forms\Components\Select::make('delivery_order_id')
                            ->label(__('Delivery Order'))
                            ->relationship('deliveryOrder', 'delivery_order_number', function (Builder $query, callable $get, $livewire) {
                                $customerId = $get('customer_id');
                                if ($customerId) {
                                    $query->where('customer_id', $customerId);
                                }

                                // Terbaru dulu: sales mengenali surat jalan dari tanggal kirimnya, bukan dari nomornya.
                                $query->orderByDesc('delivery_date')->orderByDesc('id');

                                // Retur hanya untuk barang yang dikirim dalam 3 bulan terakhir (Owner, 24 Oktober 2026); plan lama yang sudah
                                // tersimpan tetap menampilkan surat jalannya (batas hanya berlaku saat membuat plan baru).
                                if ($livewire instanceof \App\Filament\Admin\Resources\SalesReturnPlanResource\Pages\CreateSalesReturnPlan) {
                                    $query->whereDate('delivery_date', '>=', now()->subMonths(\App\Models\SalesReturnPlan::MAX_DELIVERY_AGE_MONTHS)->toDateString());
                                }
                            })
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\DeliveryOrder $record): string => $record->delivery_order_number.' — '.__('Shipped').' '.($record->delivery_date ? \Carbon\Carbon::parse($record->delivery_date)->translatedFormat('d M Y') : '-'))
                            ->searchable(['delivery_order_number'])
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('items', []))
                            ->disabled(fn ($livewire): bool => static::isNotFullyEditable($livewire))
                            ->placeholder(__('Pick a delivery order, or leave it empty for an unidentified return'))
                            ->helperText(__('Only delivery orders shipped within the last 3 months are listed.'))
                            ->columnSpan(['default' => 1, 'lg' => 2]),

                        Forms\Components\DatePicker::make('plan_date')
                            ->label(__('Plan Date'))
                            ->default(now())
                            ->disabled(fn ($livewire): bool => static::isNotFullyEditable($livewire))
                            ->required(),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->maxLength(255)
                            ->disabled(fn ($livewire): bool => static::isNotFullyEditable($livewire))
                            ->columnSpanFull(),
                    ])->columns(4),

                // Repeater BUKAN diikat lewat ->relationship() -- pola
                // yang sama dengan SalesOrderResource/
                // ProductRequisitionResource (lihat project.md): item
                // disimpan manual di handleRecordCreation()/afterSave()
                // supaya galat validasi server (klaim > terkirim di DO,
                // dari SalesReturnPlanItem::booted()) bisa ditangkap dan
                // ditampilkan ramah, bukan menjalar sebagai galat mentah
                // dari mekanisme auto-save relationship Filament.
                Forms\Components\Section::make(__('Items'))
                    // Pemisah ribuan otomatis untuk berat klaim (koma = desimal, maksimal 2 digit). Dipasang di Section pembungkus, bukan
                    // mask per baris, karena mask Alpine di dalam Repeater merusak baris yang dihapus (lihat ReceivePayment).
                    ->extraAttributes([
                        'x-on:input' => '
                            (function(e) {
                                let t = e.target;
                                if (!t || !t.classList.contains("pr-claim-weight")) return;
                                let raw = t.value.replace(/[^0-9,]/g, "");
                                let parts = raw.split(",");
                                let intPart = parts[0].replace(/^0+(?=\d)/, "");
                                let grouped = intPart === "" ? "" : new Intl.NumberFormat("de-DE").format(parseInt(intPart, 10));
                                let formatted = parts.length > 1 ? grouped + "," + parts.slice(1).join("").slice(0, 2) : grouped;
                                if (t.value !== formatted) {
                                    let start = t.selectionStart, len = t.value.length;
                                    t.value = formatted;
                                    let pos = Math.max(0, start + formatted.length - len);
                                    t.setSelectionRange(pos, pos);
                                    t.dispatchEvent(new Event("input", { bubbles: true }));
                                }
                            })(event)
                        ',
                    ])
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\Hidden::make('id'),

                                // Retur boleh untuk produk yang kini nonaktif.
                                \App\Filament\Support\MasterSelect::server(
                                    Forms\Components\Select::make('product_id'),
                                    \App\Models\Product::class,
                                    false,
                                    // Surat jalan dipilih: pilihan produk hanya yang ada di surat jalan itu (Owner, 24 Oktober 2026).
                                    fn (Builder $query, $livewire, Get $get) => $get('../../delivery_order_id')
                                        ? $query->whereIn('id', \App\Models\DeliveryOrderItem::query()->where('delivery_order_id', $get('../../delivery_order_id'))->select('product_id'))
                                        // Tanpa surat jalan: hanya produk yang diterima customer ini dalam 3 bulan terakhir (Owner, 24 Oktober 2026).
                                        : $query->when(
                                            $get('../../customer_id'),
                                            fn (Builder $q, $customerId) => $q->whereIn('id', \App\Models\DeliveryOrderItem::query()
                                                ->whereIn('delivery_order_id', \App\Models\DeliveryOrder::query()
                                                    ->where('customer_id', $customerId)
                                                    ->whereDate('delivery_date', '>=', now()->subMonths(\App\Models\SalesReturnPlan::MAX_DELIVERY_AGE_MONTHS)->toDateString())
                                                    ->select('id'))
                                                ->select('product_id')),
                                        ),
                                )
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, \Filament\Forms\Set $set) => $set('delivered_display', static::deliveredText($get)))
                                    ->label(__('Product'))
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->distinct()
                                    ->validationMessages(['distinct' => __('This item is already in the list.')])
                                    // Produk baris yang SUDAH ADA tidak
                                    // boleh diganti begitu Submitted --
                                    // baris baru (belum punya id) selalu
                                    // bisa diisi, tapi baris baru pun
                                    // hanya bisa ditambahkan selagi Draft
                                    // (lihat disableItemCreation()).
                                    ->disabled(fn (Get $get, $livewire): bool => static::isNotFullyEditable($livewire) && filled($get('id')))
                                    ->dehydrated()
                                    ->columnSpan(['default' => 1, 'lg' => 4]),

                                // Berat yang terkirim, otomatis dari surat jalan terpilih (atau dari yang diterima customer 3 bulan terakhir bila tanpa surat jalan).
                                Forms\Components\TextInput::make('delivered_display')
                                    ->label(__('Received (Kg)'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function (Forms\Components\TextInput $component, Get $get): void {
                                        $component->state(static::deliveredText($get));
                                    })
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->columnSpan(['default' => 1, 'lg' => 2]),

                                Forms\Components\TextInput::make('claimed_weight')
                                    ->label(__('Claimed Weight (Kg)'))
                                    ->required()
                                    ->formatStateUsing(fn ($state): ?string => $state === null || $state === '' ? null : rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ','))
                                    ->dehydrateStateUsing(fn ($state): float => static::parseWeight($state))
                                    ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                        if (static::parseWeight($value) <= 0) {
                                            $fail(__('The claimed weight must be more than zero.'));
                                        }
                                    })
                                    ->live(onBlur: true)
                                    ->helperText(function (Get $get): ?string {
                                        $delivered = static::deliveredKg($get);

                                        if ($delivered === null || static::parseWeight($get('claimed_weight')) <= $delivered) {
                                            return null;
                                        }

                                        return __('More than the :kg kg received. It is still allowed, for example for an agreement outside the system.', ['kg' => number_format($delivered, 2, ',', '.')]);
                                    })
                                    ->extraInputAttributes(['class' => 'text-right pr-claim-weight', 'inputmode' => 'decimal', 'x-on:focus' => '$el.select()'])
                                    ->columnSpan(['default' => 1, 'lg' => 2]),

                                Forms\Components\TextInput::make('note')
                                    ->label(__('Note'))
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 1, 'lg' => 4]),
                            ])
                            ->columns(12)
                            ->minItems(1)
                            ->validationMessages([
                                'min' => __('A plan cannot be saved without any items.'),
                                'minItems' => __('A plan cannot be saved without any items.'),
                            ])
                            ->reorderableWithDragAndDrop(false)
                            ->disableItemCreation(fn ($livewire): bool => static::isNotFullyEditable($livewire))
                            ->disableItemDeletion(fn ($livewire): bool => static::isNotFullyEditable($livewire))
                            ->addActionLabel(__('Add Item')),
                    ]),
            ]);
    }

    /** Berat yang diterima (menurut bukti terima) untuk sebuah baris item; null sebelum produk dipilih. */
    public static function deliveredKg(Get $get): ?float
    {
        $productId = (int) $get('product_id');

        if (! $productId) {
            return null;
        }

        return \App\Models\SalesReturnPlanItem::deliveredWeight(
            $get('../../delivery_order_id') ? (int) $get('../../delivery_order_id') : null,
            $get('../../customer_id') ? (int) $get('../../customer_id') : null,
            $productId,
            $get('../../plan_date') ? \Carbon\Carbon::parse($get('../../plan_date')) : null,
        );
    }

    /** Teks kolom "Diterima (Kg)" untuk sebuah baris item; kosong sebelum produk dipilih. */
    public static function deliveredText(Get $get): ?string
    {
        $kg = static::deliveredKg($get);

        return $kg === null ? null : number_format($kg, 2, ',', '.');
    }

    /** Berat klaim dari isian ("1.234,5" -> 1234.5); angka asli (int/float) dipakai apa adanya. */
    public static function parseWeight(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        return (float) str_replace(['.', ','], ['', '.'], trim((string) $value));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan_number')
                    ->label(__('Plan No.'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('plan_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deliveryOrder.delivery_order_number')
                    ->label(__('DO No.'))
                    ->placeholder(__('Unidentified'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('Items'))
                    ->counts('items')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SalesReturnPlan::statusLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        SalesReturnPlan::STATUS_DRAFT => 'warning',
                        SalesReturnPlan::STATUS_SUBMITTED => 'info',
                        SalesReturnPlan::STATUS_RECEIVED => 'success',
                        SalesReturnPlan::STATUS_CANCELLED => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make()
                    ->visible(fn () => auth()->user()?->hasPermission('view_deleted_sales_return_plans') ?? false),
                \App\Filament\Support\MasterSelect::filter(
                    Tables\Filters\SelectFilter::make('customer_id')
                        ->label(__('Customer')),
                    \App\Models\Customer::class,
                ),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        SalesReturnPlan::STATUS_DRAFT => SalesReturnPlan::STATUS_DRAFT,
                        SalesReturnPlan::STATUS_SUBMITTED => SalesReturnPlan::statusLabel(SalesReturnPlan::STATUS_SUBMITTED),
                        SalesReturnPlan::STATUS_RECEIVED => SalesReturnPlan::STATUS_RECEIVED,
                        SalesReturnPlan::STATUS_CANCELLED => SalesReturnPlan::STATUS_CANCELLED,
                    ]),
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
            // Draft DAN Submitted sekarang membuka halaman Edit yang sama
            // (header terkunci otomatis untuk Submitted, lihat form()) --
            // hanya Received/Cancelled yang diarahkan ke View, karena
            // Edit-nya sendiri akan mengalihkan balik ke View untuk
            // keduanya (EditSalesReturnPlan::mount()).
            ->recordUrl(fn (SalesReturnPlan $record) => $record->trashed()
                ? null
                : (in_array($record->status, [SalesReturnPlan::STATUS_DRAFT, SalesReturnPlan::STATUS_SUBMITTED], true)
                    ? static::getUrl('edit', ['record' => $record])
                    : static::getUrl('view', ['record' => $record])));
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
            'index' => Pages\ListSalesReturnPlans::route('/'),
            'create' => Pages\CreateSalesReturnPlan::route('/create'),
            'edit' => Pages\EditSalesReturnPlan::route('/{record}/edit'),
            'view' => Pages\ViewSalesReturnPlan::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_sales_return_plans',
        );
    }
}
