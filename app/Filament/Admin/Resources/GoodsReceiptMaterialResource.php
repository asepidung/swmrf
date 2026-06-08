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
    protected static ?string $navigationGroup = 'Goods Receipt';
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
                    ->sortable(),
                Tables\Columns\TextColumn::make('receive_date')
                    ->label('Receive Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sj_number')
                    ->label('Surat Jalan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('purchaseMaterial.po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(
                fn (GoodsReceiptMaterial $record): string => Pages\ViewGoodsReceiptMaterial::getUrl(['record' => $record]),
            )
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_gr_materials')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->color('info'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn (GoodsReceiptMaterial $record) => match ($record->trashed()) {
                true => 'bg-danger-100/50 dark:bg-danger-900/50',
                false => null,
            });
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
            // 'edit' => Pages\EditGoodsReceiptMaterial::route('/{record}/edit'), // No Edit for GR
        ];
    }
}
