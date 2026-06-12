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
                            ->disabled()
                            ->dehydrated()
                            ->searchable()
                            ->preload(),

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
                                    ->stripCharacters('.')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->extraInputAttributes([
                                        'class' => 'text-right',
                                        'onclick' => 'this.select()'
                                    ])
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
                Tables\Columns\TextColumn::make('row_index')
                    ->label('#')
                    ->rowIndex(),

                Tables\Columns\TextColumn::make('customerGroup.name')
                    ->label(__('Customer Group'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Last Updated'))
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->formatStateUsing(fn ($record, $state) => $record->items()->exists() ? $state : '-'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->sortable()
                    ->formatStateUsing(fn ($record, $state) => $record->items()->exists() ? $state : '-'),
            ])
            ->filters([
                // No soft deletes or TrashedFilter per instruction
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label(__('Print'))
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->iconButton()
                    ->tooltip(__('Print Price List'))
                    ->visible(fn (PriceList $record) => $record->items()->exists())
                    ->url(fn (PriceList $record): string => route('print.pricelist', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('manage_pricelist')
                    ->label(fn (PriceList $record) => $record->items()->exists() ? __('Edit') : __('Create'))
                    ->icon(fn (PriceList $record) => $record->items()->exists() ? 'heroicon-o-pencil-square' : 'heroicon-o-document-plus')
                    ->color(fn (PriceList $record) => $record->items()->exists() ? 'primary' : 'warning')
                    ->button()
                    ->url(fn (PriceList $record): string => Pages\EditPriceList::getUrl([$record->id])),
            ])
            ->recordUrl(fn (PriceList $record) => Pages\ViewPriceList::getUrl([$record->id]))
            ->bulkActions([
                // Delete bulk actions disabled per instruction
            ])
            ->defaultSort('customer_groups.name', 'asc');
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

    public static function getEloquentQuery(): Builder
    {
        // Self-healing: auto-create missing price lists for any existing customer groups
        try {
            $groupsWithoutPriceList = \App\Models\CustomerGroup::whereDoesntHave('priceList')->get();
            foreach ($groupsWithoutPriceList as $group) {
                \App\Models\PriceList::create([
                    'customer_group_id' => $group->id,
                    'created_by' => auth()->id() ?: 1, // Fallback to programmer
                ]);
            }
        } catch (\Exception $e) {
            // Silently ignore during migration/seeding if tables do not exist yet
        }

        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->join('customer_groups', 'price_lists.customer_group_id', '=', 'customer_groups.id')
            ->select('price_lists.*');
    }
}
