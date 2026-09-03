<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DeliveryPlanResource\Pages;
use App\Models\DeliveryPlan;
use App\Models\SalesOrder;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliveryPlanResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('Plan Delivery');
    }

    protected static ?string $model = DeliveryPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getModelLabel(): string
    {
        return __('Plan Delivery');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Plan Deliveries');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Order Information'))
                    ->compact()
                    ->schema([
                        Forms\Components\Placeholder::make('customer_name')
                            ->label(__('Customer'))
                            ->content(fn ($record) => $record?->customer?->name),
                        Forms\Components\Placeholder::make('delivery_date_formatted')
                            ->label(__('Tanggal Kirim'))
                            ->content(fn ($record) => $record?->delivery_date ? \Carbon\Carbon::parse($record->delivery_date)->format('d-m-Y') : '-'),
                    ])->columns(2),

                Forms\Components\Section::make(__('Trip Details'))
                    ->compact()
                    ->schema([
                        Forms\Components\TextInput::make('driver')
                            ->label(__('Driver'))
                            ->required()
                            ->maxLength(255)
                            ->autofocus(), // Ergonomic UI: autofocus on first editable field
                        Forms\Components\TextInput::make('armada')
                            ->label(__('Fleet'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TimePicker::make('load_time')
                            ->label(__('Loading Time'))
                            ->seconds(false)
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make(__('Associated Sales Orders'))
                    ->compact()
                    ->schema([
                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('col_so_number')->label(__('SO Number'))->columnSpan(['default' => 'full', 'lg' => 4]),
                                Forms\Components\Placeholder::make('col_weight')->label(__('Qty (Kg)'))->columnSpan(['default' => 'full', 'lg' => 3]),
                                Forms\Components\Placeholder::make('col_note')->label(__('Note'))->columnSpan(['default' => 'full', 'lg' => 5]),
                            ])
                            ->extraAttributes(['class' => 'swm-wide-only']),

                        Forms\Components\Repeater::make('salesOrders')
                            ->relationship('salesOrders')
                            ->hiddenLabel()
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement()
                            ->columns(12)
                            ->schema([
                                Forms\Components\TextInput::make('so_number')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(['default' => 'full', 'lg' => 4]),
                                Forms\Components\Placeholder::make('total_weight')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->content(fn ($record) => $record ? number_format($record->items()->sum('weight')) . ' Kg' : '-')
                                    ->columnSpan(['default' => 'full', 'lg' => 3]),
                                Forms\Components\TextInput::make('delivery_note')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->placeholder(__('Note'))
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 'full', 'lg' => 5]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sales_orders_count')
                    ->label(__('Total PO'))
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_qty')
                    ->label(__('Qty (Kg)'))
                    ->state(fn (DeliveryPlan $record) => number_format($record->total_qty))
                    ->alignRight(),
                Tables\Columns\TextColumn::make('driver')
                    ->label(__('Driver'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('armada')
                    ->label(__('Fleet'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('load_time')
                    ->label(__('Loading Time'))
                    ->time('H:i')
                    ->sortable(),
                // Kemajuan tiap jadwal, dihitung dari relasi yang sudah
                // dimuat. Inilah yang membuat daftarnya berguna sebagai alat
                // mengatur: tanpa ini, petugas harus membuka satu per satu
                // untuk tahu mana yang sudah disiapkan.
                Tables\Columns\TextColumn::make('progress')
                    ->label(__('Progress'))
                    ->state(fn (DeliveryPlan $record): string => $record->progressLabel())
                    ->badge()
                    ->color(fn (DeliveryPlan $record): string => match (true) {
                        $record->isOverdue() => 'danger',
                        $record->progressLabel() === __('Delivered') => 'success',
                        $record->progressLabel() === __('Delivery note issued') => 'info',
                        $record->progressLabel() === __('Being prepared') => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('Notes'))
                    ->limit(40),
            ])
            ->recordUrl(
                fn (DeliveryPlan $record): ?string => Pages\EditDeliveryPlan::getUrl([$record->getKey()])
            )
            ->recordClasses(fn (DeliveryPlan $record) => match (true) {
                $record->trashed() => 'bg-danger-50 dark:bg-danger-900/20 border-danger-500',
                default => null,
            })
            ->filters([
                // Menyala secara bawaan. Daftar ini adalah alat kerja
                // petugas distribusi, jadi yang pertama terlihat harus
                // jadwal yang masih perlu diurus -- bukan seluruh jadwal
                // yang pernah dibuat sejak sistem berdiri.
                //
                // Dibuat sebagai SARINGAN, bukan sebagai batasan tetap pada
                // kueri, supaya riwayatnya tetap bisa dibuka: mematikan
                // saringannya mengembalikan seluruh daftar.
                //
                // Batasnya akhir hari kirim, bukan peristiwa dokumen; lihat
                // DeliveryPlan::scopeStillRelevant() untuk alasannya.
                Tables\Filters\Filter::make('still_relevant')
                    ->label(__('Active schedules only'))
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->stillRelevant()),

                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_delivery_plans')),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('Customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('delivery_date')
                    ->form([
                        Forms\Components\DatePicker::make('delivery_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('delivery_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['delivery_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['delivery_until'] ?? null;

                        return $query
                            ->when(
                                $from,
                                fn(Builder $query, $date): Builder => $query->whereDate('delivery_date', '>=', $date),
                            )
                            ->when(
                                $until,
                                fn(Builder $query, $date): Builder => $query->whereDate('delivery_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['delivery_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['delivery_from'])->format('d M Y');
                        }
                        if ($data['delivery_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['delivery_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->headerActions([])
            ->actions([
                // Rows are clickable
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('delivery_date', 'desc');
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
            'index' => Pages\ListDeliveryPlans::route('/'),
            'edit' => Pages\EditDeliveryPlan::route('/{record}/edit'),
            'view' => Pages\ViewDeliveryPlan::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withCount('salesOrders')
            // Dimuat sekaligus. Kolom Qty dan Notes membaca Sales Order
            // beserta barisnya untuk setiap jadwal; tanpa ini, satu kueri
            // menembak untuk setiap Sales Order pada setiap baris tabel.
            ->with(['salesOrders:id,delivery_plan_id,status,delivery_note', 'salesOrders.items:id,sales_order_id,weight']);

        return TrashedRecords::visibleTo($query, 'view_deleted_delivery_plans');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('DISTRIBUTION');
    }
}
