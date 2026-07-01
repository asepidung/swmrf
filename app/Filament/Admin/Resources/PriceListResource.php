<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PriceListResource\Pages;
use App\Models\CustomerGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\RawJs;

class PriceListResource extends Resource
{
    protected static ?string $model = CustomerGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'SALES';
    protected static ?string $navigationLabel = 'Price List';
    protected static ?string $pluralModelLabel = 'Price Lists';
    protected static ?string $modelLabel = 'Price List';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Price List Information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Customer Group'))
                            ->disabled(),
                    ]),

                Forms\Components\Section::make(__('Products Pricing'))
                    ->relationship('priceList')
                    ->schema([
                        Forms\Components\Hidden::make('created_by')
                            ->default(fn () => auth()->id() ?: 1),

                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('col_product')
                                    ->label(__('Product'))
                                    ->columnSpan(4),
                                Forms\Components\Placeholder::make('col_price')
                                    ->label(__('Price'))
                                    ->columnSpan(4),
                                Forms\Components\Placeholder::make('col_note')
                                    ->label(__('Note (Optional)'))
                                    ->columnSpan(4),
                            ])
                            ->extraAttributes(['class' => 'hidden md:grid']),

                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
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
                                    ->extraAttributes([
                                        'class' => 'product-select-column',
                                    ])
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('price')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->required()
                                    ->prefix('Rp')
                                    ->placeholder(__('Price'))
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->stripCharacters('.')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->extraInputAttributes([
                                        'class' => 'text-right price-input-column enter-to-next-price',
                                        'onclick' => 'this.select()',
                                        'onkeydown' => "
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                let inputs = Array.from(document.querySelectorAll('.enter-to-next-price'));
                                                let index = inputs.indexOf(this);
                                                if (index > -1 && index + 1 < inputs.length) {
                                                    inputs[index + 1].focus();
                                                    inputs[index + 1].select();
                                                }
                                            }
                                        "
                                    ])
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('note')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->placeholder(__('Note (Optional)'))
                                    ->maxLength(255)
                                    ->extraInputAttributes([
                                        'class' => 'note-input-column enter-to-next-note',
                                        'onkeydown' => "
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                let inputs = Array.from(document.querySelectorAll('.enter-to-next-note'));
                                                let index = inputs.indexOf(this);
                                                if (index > -1 && index + 1 < inputs.length) {
                                                    inputs[index + 1].focus();
                                                }
                                            }
                                        "
                                    ])
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
                Tables\Columns\TextColumn::make('row_index')
                    ->label('#')
                    ->rowIndex(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Customer Group'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('priceList.updated_at')
                    ->label(__('Last Updated'))
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->formatStateUsing(fn ($record, $state) => ($record->priceList && $record->priceList->items()->exists()) ? $state : '-'),

                Tables\Columns\TextColumn::make('priceList.creator.name')
                    ->label(__('Created By'))
                    ->sortable()
                    ->formatStateUsing(fn ($record, $state) => ($record->priceList && $record->priceList->creator) ? $state : '-'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('manage_pricelist')
                    ->label(fn (CustomerGroup $record) => ($record->priceList && $record->priceList->items()->exists()) ? __('Edit') : __('Create'))
                    ->icon(fn (CustomerGroup $record) => ($record->priceList && $record->priceList->items()->exists()) ? 'heroicon-o-pencil-square' : 'heroicon-o-document-plus')
                    ->color(fn (CustomerGroup $record) => ($record->priceList && $record->priceList->items()->exists()) ? 'warning' : 'success')
                    ->button()
                    ->url(fn (CustomerGroup $record): string => Pages\EditPriceList::getUrl([$record->id])),
            ])
            ->recordUrl(fn (CustomerGroup $record) => Pages\ViewPriceList::getUrl([$record->id]))
            ->bulkActions([
                //
            ])
            ->defaultSort('name', 'asc');
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
            'view' => Pages\ViewPriceList::route('/{record}'),
            'edit' => Pages\EditPriceList::route('/{record}/edit'),
        ];
    }
}
