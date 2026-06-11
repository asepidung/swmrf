<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PriceListResource\Pages;
use App\Models\PriceList;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\RawJs;

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'SALES';
    protected static ?string $navigationLabel = 'Price List';
    protected static ?string $pluralModelLabel = 'Price Lists';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Price List Information'))
                    ->schema([
                        Forms\Components\Select::make('customer_group_id')
                            ->label(__('Customer Group'))
                            ->relationship('customerGroup', 'name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->searchable()
                            ->preload()
                            ->autofocus(),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn () => auth()->id()),
                    ]),

                Forms\Components\Section::make(__('Products Pricing'))
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->placeholder(__('Select Product'))
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('price')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->required()
                                    ->prefix('Rp')
                                    ->placeholder(__('Price'))
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->stripCharacters(',')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('note')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->placeholder(__('Note (Optional)'))
                                    ->maxLength(255)
                                    ->columnSpan(4),
                            ])
                            ->columns(12)
                            ->addActionLabel(__('Add Product'))
                            ->defaultItems(1)
                            ->reorderableWithButtons(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customerGroup.name')
                    ->label(__('Customer Group'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Last Updated'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordClasses(fn (PriceList $record) => match (true) {
                $record->trashed() => 'bg-danger-50 dark:bg-danger-900/20',
                default => null,
            })
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label(__('Print'))
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->iconButton()
                    ->tooltip(__('Print Price List'))
                    ->url(fn (PriceList $record): string => route('print.pricelist', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit')),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Delete')),
                Tables\Actions\ForceDeleteAction::make()
                    ->iconButton(),
                Tables\Actions\RestoreAction::make()
                    ->iconButton(),
            ])
            ->recordUrl(fn (PriceList $record) => $record->trashed() ? null : Pages\EditPriceList::getUrl([$record->id]))
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListPriceLists::route('/'),
            'create' => Pages\CreatePriceList::route('/create'),
            'edit' => Pages\EditPriceList::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
