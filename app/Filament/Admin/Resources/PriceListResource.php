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
use Illuminate\Database\Eloquent\Model;
use Filament\Support\RawJs;

class PriceListResource extends Resource
{
    public static function getPluralModelLabel(): string
    {
        return __('Price Lists');
    }

    public static function getModelLabel(): string
    {
        return __('Price List');
    }

    public static function getNavigationLabel(): string
    {
        return __('Price List');
    }

    protected static ?string $model = CustomerGroup::class;

    /**
     * Hak akses WAJIB diperiksa di sini, tidak bisa mengandalkan Policy.
     *
     * Resource ini memakai model CustomerGroup, yang juga dipakai
     * ReceivableResource. Laravel menemukan Policy lewat nama MODEL, jadi
     * keduanya jatuh ke CustomerGroupPolicy -- PriceListPolicy tidak pernah
     * dipanggil sama sekali. Akibatnya siapa pun yang punya
     * view_customer_groups ikut melihat menu Price List, meski tidak diberi
     * hak Price List sedikit pun.
     *
     * Selama modelnya masih dipakai bersama, penjagaannya harus di Resource.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('view_price_lists') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('create_price_lists') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasPermission('edit_price_lists') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasPermission('delete_price_lists') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

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

                                // Harga di sini menentukan harga SATU GRUP
                                // PELANGGAN sekaligus, jadi tergeser sedikit
                                // saja dampaknya jauh lebih luas daripada
                                // salah ketik di satu dokumen.
                                //
                                // Karena itu ->numeric() dilepas: pemanggilan
                                // itu membuat input menjadi type=number lengkap
                                // dengan tombol panah yang gampang tersentuh
                                // tanpa disadari. Aturan angkanya ditulis
                                // manual supaya tetap terjaga, dan topengnya
                                // disamakan dengan kolom uang lain di aplikasi
                                // supaya 95000 terbaca sebagai 95.000.
                                Forms\Components\TextInput::make('price')
                                    ->label('')
                                    ->hiddenLabel()
                                    ->required()
                                    ->prefix('Rp')
                                    ->placeholder(__('Price'))
                                    ->default(0)
                                    ->formatStateUsing(fn ($state): ?string => $state === null || $state === ''
                                        ? null
                                        : number_format((float) $state, 0, ',', '.'))
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->dehydrateStateUsing(fn ($state): int => (int) str_replace('.', '', (string) $state))
                                    ->extraInputAttributes([
                                        'inputmode' => 'numeric',
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
                    ->label(__('#'))
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
            ->headerActions([
                Tables\Actions\Action::make('export_excel')
                    ->label(__('Excel'))
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->get();
                        return response()->streamDownload(function () use ($records) {
                            $writer = new \OpenSpout\Writer\XLSX\Writer();
                            $writer->openToFile('php://output');
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Customer Group', 'Last Updated', 'Created By']));
                            foreach ($records as $record) {
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    $record->name ?? '',
                                    ($record->priceList && $record->priceList->items()->exists()) ? $record->priceList->updated_at->format('d M Y, H:i') : '-',
                                    ($record->priceList && $record->priceList->creator) ? $record->priceList->creator->name : '-',
                                ]));
                            }
                            $writer->close();
                        }, 'PriceLists.xlsx');
                    }),
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
            'detail-list' => Pages\PriceListDetailList::route('/detail-list'),
            'view' => Pages\ViewPriceList::route('/{record}'),
            'edit' => Pages\EditPriceList::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('SALES');
    }
}
