<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\StockTakeResource\Pages;
use App\Filament\Admin\Resources\StockTakeResource\RelationManagers;
use App\Models\StockTake;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists;
use Filament\Infolists\Infolist;

use App\Models\Warehouse;
use Illuminate\Support\Carbon;

class StockTakeResource extends Resource
{
    protected static ?string $model = StockTake::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    public static function getModelLabel(): string
    {
        return __('Opname Beef');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Opname Beef');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Stock Opname Info'))
                    ->schema([
                        Forms\Components\TextInput::make('document_number')
                            ->label(__('Document Number'))
                            ->default('AUTO')
                            ->disabled()
                            ->dehydrated(false)
                            ->hiddenOn('create')
                            ->required(),
                        Forms\Components\TextInput::make('period')
                            ->label(__('Periode (Bulan/Tahun)'))
                            ->type('month')
                            ->required()
                            ->autofocus(),
                        Forms\Components\DatePicker::make('date')
                            ->label(__('Date'))
                            ->default(now())
                            ->required(),
                        Forms\Components\Textarea::make('summary_note')
                            ->label(__('Note'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label(__('Doc No'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('period')
                    ->label(__('Periode'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label(__('Date'))
                    ->date('d-M-Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'IN_PROGRESS' => 'warning',
                        'COMPLETED' => 'success',
                        'CANCELED' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('d-M-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (StockTake $record): string => static::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (StockTake $record) => match (true) {
                $record->trashed() => 'bg-danger-100/50 dark:bg-danger-900/50 border-l-4 border-danger-500',
                default => null,
            })
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label(__('Created From')),
                        Forms\Components\DatePicker::make('created_until')->label(__('Created Until')),
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
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->can('view_deleted_stock_takes')),
            ])
            ->actions([
                Tables\Actions\Action::make('scan')
                    ->label(__('Scan'))
                    ->icon('heroicon-o-qr-code')
                    ->iconButton()
                    ->url(fn (StockTake $record) => static::getUrl('scan', ['record' => $record])),
                    
                Tables\Actions\Action::make('print')
                    ->label(__('Print'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->url(fn (StockTake $record) => route('stock-take.print', ['id' => $record->id]))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('finish')
                    ->label(__('Finish Opname'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip(__('Finish Stock Opname'))
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalHeading(__('PERINGATAN: Selesaikan Stock Opname?'))
                    ->modalDescription(__('Tindakan ini memiliki KONSEKUENSI BESAR pada Master Data Stock Anda! Barang yang berstatus "MISSING" akan DIHAPUS PERMANEN dari sistem, dan barang temuan ("UNEXPECTED") akan DITAMBAHKAN sebagai stok baru. Apakah Anda yakin ingin mengunci transaksi ini?'))
                    ->visible(fn (StockTake $record) => $record->status === 'IN_PROGRESS')
                    ->action(function (StockTake $record) {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                            // 1. Bypass Freeze Check
                            \App\Services\WarehouseFreezeService::$bypassed = true;
                            
                            // 2. Handle MISSING items (Delete from BeefStock)
                            $missingItems = $record->items()->where('status', 'MISSING')->get();
                            foreach ($missingItems as $item) {
                                $stock = \App\Models\BeefStock::where('barcode', $item->barcode)
                                    ->where('warehouse_id', $item->warehouse_id)
                                    ->first();
                                    
                                if ($stock) {
                                    // Log movement
                                    \App\Models\BeefStockMovement::create([
                                        'product_id' => $stock->product_id,
                                        'warehouse_id' => $stock->warehouse_id,
                                        'condition' => $stock->grade_id,
                                        'barcode' => $stock->barcode,
                                        'transaction_type' => 'STOCK_TAKE_LOSS',
                                        'reference_document' => $record->document_number,
                                        'weight_in' => 0,
                                        'weight_out' => $stock->weight,
                                        'pcs_in' => 0,
                                        'pcs_out' => $stock->qty_pcs,
                                        'note' => 'Stock Take Loss (Missing)',
                                        'created_by' => auth()->id(),
                                    ]);
                                    
                                    $stock->delete();
                                }
                            }
                            
                            // 3. Handle UNEXPECTED items (Insert into BeefStock)
                            $unexpectedItems = $record->items()->where('status', 'UNEXPECTED')->get();
                            foreach ($unexpectedItems as $item) {
                                \App\Models\BeefStock::create([
                                    'barcode' => $item->barcode,
                                    'product_id' => $item->product_id,
                                    'warehouse_id' => $item->warehouse_id,
                                    'grade_id' => $item->grade_id,
                                    'weight' => $item->weight,
                                    'qty_pcs' => $item->qty_pcs,
                                    'ph_level' => $item->ph_level,
                                    'pack_date' => $item->pack_date,
                                    'origin' => \App\Helpers\BarcodeHelper::getOrigin($item->barcode),
                                    'status' => 'IN_STOCK',
                                    'note' => $item->note,
                                ]);
                                
                                // Log movement
                                \App\Models\BeefStockMovement::create([
                                    'product_id' => $item->product_id,
                                    'warehouse_id' => $item->warehouse_id,
                                    'condition' => $item->grade_id,
                                    'barcode' => $item->barcode,
                                    'transaction_type' => 'STOCK_TAKE_FOUND',
                                    'reference_document' => $record->document_number,
                                    'weight_in' => $item->weight,
                                    'weight_out' => 0,
                                    'pcs_in' => $item->qty_pcs,
                                    'pcs_out' => 0,
                                    'note' => 'Stock Take Found (Unexpected)',
                                    'created_by' => auth()->id(),
                                ]);
                            }
                            
                            // 4. Update Opname Status
                            $record->update(['status' => 'COMPLETED']);
                            
                            // Re-enable freeze check
                            \App\Services\WarehouseFreezeService::$bypassed = false;
                        });
                        
                        \Filament\Notifications\Notification::make()
                            ->title(__('Stock Opname Selesai'))
                            ->body(__('Rekonsiliasi stok berhasil dilakukan.'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('Stock Opname Details'))
                    ->description(__('Informasi dokumen dan status saat ini.'))
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('document_number')
                                        ->label(__('Document Number'))
                                        ->weight('bold')
                                        ->copyable(),
                                    Infolists\Components\TextEntry::make('status')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'DRAFT' => 'gray',
                                            'IN_PROGRESS' => 'warning',
                                            'COMPLETED' => 'success',
                                            default => 'primary',
                                        }),
                                    Infolists\Components\TextEntry::make('period')
                                        ->label(__('Periode')),
                                    Infolists\Components\TextEntry::make('date')
                                        ->label(__('Date'))
                                        ->date('d M Y'),
                                ]),
                            Infolists\Components\TextEntry::make('summary_note')
                                ->label(__('Note'))
                                ->placeholder('-')
                                ->markdown()
                        ])->from('md'),
                    ])->compact(),
                    
                Infolists\Components\Section::make(__('Opname Progress'))
                    ->schema([
                        Infolists\Components\ViewEntry::make('progress_stats')
                            ->hiddenLabel()
                            ->view('filament.infolists.components.progress-stats'),
                    ]),
            ]);
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
            'index' => Pages\ListStockTakes::route('/'),
            'create' => Pages\CreateStockTake::route('/create'),
            'view' => Pages\ViewStockTake::route('/{record}'),
            'edit' => Pages\EditStockTake::route('/{record}/edit'),
            'scan' => Pages\ScanStockTake::route('/{record}/scan'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'STOCKS';
    }

    public static function getNavigationLabel(): string
    {
        return __('Opname Beef');
    }

}
