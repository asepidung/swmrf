<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseCattleResource\Pages;
use App\Filament\Admin\Resources\PurchaseCattleResource\RelationManagers;
use App\Models\PurchaseCattle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Carbon\Carbon;

class PurchaseCattleResource extends Resource
{
    protected static ?string $model = PurchaseCattle::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Purchase Order';

    public static function getModelLabel(): string
    {
        return __('PO Cattle');
    }

    public static function getPluralModelLabel(): string
    {
        return __('PO Cattles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Header Info')->schema([
                    Forms\Components\Select::make('supplier_id')
                        ->relationship('supplier', 'name')
                        ->required()
                        ->label(__('Supplier')),
                    Forms\Components\DatePicker::make('shipping_date')
                        ->required()
                        ->autofocus()
                        ->label(__('Shipping Date')),
                    Forms\Components\Textarea::make('summary_note')
                        ->label(__('Summary Note'))
                        ->columnSpanFull(),
                ])->columns(2),
                
                Forms\Components\Section::make('Items')->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('cattle_class_id')
                                ->relationship('cattleClass', 'name')
                                ->required()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(table: 'cattle_classes', column: 'name')
                                        ->label(__('Name')),
                                ])
                                ->label(__('Cattle Class')),
                            Forms\Components\TextInput::make('qty')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->label(__('Qty (Head)')),
                            Forms\Components\TextInput::make('price')
                                ->required()
                                ->default(0)
                                ->extraAlpineAttributes(['x-mask:dynamic' => "\$money(\$input, ',', '', 0)"])
                                ->stripCharacters(',')
                                ->numeric()
                                ->minValue(0)
                                ->extraInputAttributes(['onfocus' => 'this.select()'])
                                ->label(__('Price')),
                            Forms\Components\Textarea::make('item_notes')
                                ->label(__('Item Notes'))
                                ->rows(1),
                        ])
                        ->columns(4)
                        ->defaultItems(1)
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('PO Date'))
                    ->date('d-M-Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shipping_date')
                    ->label(__('Shipping Date'))
                    ->date('d-M-Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('summary_note')
                    ->label(__('Note'))
                    ->limit(50)
                    ->searchable(),
            ])
            ->recordUrl(
                fn (Model $record): string => Pages\EditPurchaseCattle::getUrl([$record->getKey()]),
            )
            ->filters([
                SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label(__('Supplier')),
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('From'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('Until'))
                            ->default(now()),
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
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label('')
                    ->tooltip('Print PO')
                    ->icon('heroicon-o-printer')
                    ->url(fn (PurchaseCattle $record): string => route('po-cattle.print', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPurchaseCattle::route('/'),
            'create' => Pages\CreatePurchaseCattle::route('/create'),
            'edit' => Pages\EditPurchaseCattle::route('/{record}/edit'),
        ];
    }
}
