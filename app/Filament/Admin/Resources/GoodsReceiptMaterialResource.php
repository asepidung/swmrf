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

class GoodsReceiptMaterialResource extends Resource
{
    protected static ?string $model = GoodsReceiptMaterial::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'GOODS RECEIPT';
    protected static ?int $navigationSort = 15;
    protected static ?string $navigationLabel = 'Material Receipt';
    protected static ?string $modelLabel = 'Material Receipt';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Goods Receipt Information')
                    ->schema([
                        Forms\Components\TextInput::make('gr_number')
                            ->label('GR Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('view'),
                        Forms\Components\DatePicker::make('receive_date')
                            ->label('Receive Date')
                            ->required(),
                        Forms\Components\TextInput::make('sj_number')
                            ->label('Surat Jalan Number')
                            ->required(),
                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ])->columns(2),
                
                Forms\Components\Section::make('Materials Received')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->relationship('material', 'name')
                                    ->label('Material')
                                    ->hiddenLabel()
                                    ->placeholder('Material')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('qty_received')
                                    ->label('Qty Received')
                                    ->hiddenLabel()
                                    ->placeholder('Qty Received')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),
                            ])
                            ->columns(2)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gr_number')
                    ->label('GR Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('receive_date')
                    ->label('Receive Date')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('sj_number')
                    ->label('Surat Jalan')
                    ->searchable()
                    ->color(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('purchaseMaterial.po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable()
                    ->color(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->color(fn (GoodsReceiptMaterial $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
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
                    \Filament\Tables\Actions\ExportAction::make('excel')
                        ->label('Excel')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->exporter(\App\Filament\Exports\GoodsReceiptMaterialExporter::class)
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    \Filament\Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            // Optional: Implement PDF generation
                            // return response()->streamDownload(fn () => print($pdf->output()), 'export.pdf');
                        }),
                ])
                ->label('Export Data')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_gr_materials')),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label('Supplier'),
                Tables\Filters\Filter::make('receive_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
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
            'view' => Pages\ViewGoodsReceiptMaterial::route('/{record}'),
            'edit' => Pages\EditGoodsReceiptMaterial::route('/{record}/edit'),
        ];
    }
}
