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
                        Forms\Components\Select::make('customer_id')
                            ->label(__('Customer'))
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
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
                            ->relationship('deliveryOrder', 'delivery_order_number', function (Builder $query, callable $get) {
                                $customerId = $get('customer_id');
                                if ($customerId) {
                                    $query->where('customer_id', $customerId);
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->disabled(fn ($livewire): bool => static::isNotFullyEditable($livewire))
                            ->placeholder(__('Pick a delivery order, or leave it empty for an unidentified return')),

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
                    ])->columns(3),

                // Repeater BUKAN diikat lewat ->relationship() -- pola
                // yang sama dengan SalesOrderResource/
                // ProductRequisitionResource (lihat project.md): item
                // disimpan manual di handleRecordCreation()/afterSave()
                // supaya galat validasi server (klaim > terkirim di DO,
                // dari SalesReturnPlanItem::booted()) bisa ditangkap dan
                // ditampilkan ramah, bukan menjalar sebagai galat mentah
                // dari mekanisme auto-save relationship Filament.
                Forms\Components\Section::make(__('Items'))
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\Hidden::make('id'),

                                Forms\Components\Select::make('product_id')
                                    ->label(__('Product'))
                                    ->options(fn () => Product::orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    // Produk baris yang SUDAH ADA tidak
                                    // boleh diganti begitu Submitted --
                                    // baris baru (belum punya id) selalu
                                    // bisa diisi, tapi baris baru pun
                                    // hanya bisa ditambahkan selagi Draft
                                    // (lihat disableItemCreation()).
                                    ->disabled(fn (Get $get, $livewire): bool => static::isNotFullyEditable($livewire) && filled($get('id')))
                                    ->dehydrated()
                                    ->columnSpan(['default' => 1, 'lg' => 4]),

                                Forms\Components\TextInput::make('claimed_weight')
                                    ->label(__('Claimed Weight (Kg)'))
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required()
                                    ->extraInputAttributes(['class' => 'text-right', 'inputmode' => 'numeric'])
                                    ->columnSpan(['default' => 1, 'lg' => 2]),

                                Forms\Components\TextInput::make('claimed_qty_pcs')
                                    ->label(__('Claimed Qty (Pcs)'))
                                    ->numeric()
                                    ->integer()
                                    ->extraInputAttributes(['class' => 'text-right', 'inputmode' => 'numeric'])
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
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('Customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        SalesReturnPlan::STATUS_DRAFT => SalesReturnPlan::STATUS_DRAFT,
                        SalesReturnPlan::STATUS_SUBMITTED => SalesReturnPlan::STATUS_SUBMITTED,
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
