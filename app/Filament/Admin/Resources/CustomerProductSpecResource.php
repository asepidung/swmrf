<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CustomerProductSpecResource\Pages;
use App\Filament\Support\MasterSelect;
use App\Models\Customer;
use App\Models\CustomerProductSpec;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Spek tetap customer per produk (#579). Sumber isian otomatis di baris
 * Sales Order; lihat {@see CustomerProductSpec}.
 */
class CustomerProductSpecResource extends Resource
{
    protected static ?string $model = CustomerProductSpec::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('QC');
    }

    public static function getModelLabel(): string
    {
        return __('Customer Spec');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Customer Specs');
    }

    /** Isian spek untuk satu produk (dipakai form buat per baris dan form ubah). */
    public static function specFields(): array
    {
        return [
            Forms\Components\TextInput::make('weight_per_pack')
                ->label(__('Weight per pack'))
                ->placeholder('500 gr / pack')
                ->maxLength(255),

            Forms\Components\TextInput::make('cut')
                ->label(__('Cut'))
                ->placeholder('slice 1,5 mm')
                ->maxLength(255),

            Forms\Components\Select::make('condition')
                ->label(__('Condition'))
                ->options(CustomerProductSpec::CONDITIONS),

            Forms\Components\TextInput::make('packaging')
                ->label(__('Packaging'))
                ->placeholder('vacuum per pcs')
                ->maxLength(255),

            Forms\Components\Textarea::make('note')
                ->label(__('Spec Note'))
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // BUAT: satu customer, banyak produk sekaligus.
                Forms\Components\Section::make()
                    ->visibleOn('create')
                    ->schema([
                        MasterSelect::customer('customer_id')
                            ->label(__('Customer'))
                            ->required()
                            ->live()
                            ->autofocus(),

                        Forms\Components\Repeater::make('items')
                            ->label(__('Products and specs'))
                            ->addActionLabel(__('Add product'))
                            ->minItems(1)
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => filled($state['product_id'] ?? null) ? Product::query()->whereKey($state['product_id'])->value('name') : null)
                            ->columns(['default' => 1, 'lg' => 2])
                            ->schema([
                                MasterSelect::product('product_id')
                                    ->label(__('Product'))
                                    ->required()
                                    ->distinct()
                                    ->columnSpanFull()
                                    ->rules([
                                        fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get): void {
                                            $exists = CustomerProductSpec::query()
                                                ->where('customer_id', $get('../../customer_id'))
                                                ->where('product_id', $value)
                                                ->exists();

                                            if ($exists) {
                                                $fail(__('This customer already has a spec for this product. Edit that one instead.'));
                                            }
                                        },
                                    ]),
                                ...static::specFields(),
                            ]),
                    ]),

                // UBAH: satu pasangan customer + produk (identitasnya tidak diubah).
                Forms\Components\Section::make()
                    ->visibleOn('edit')
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        MasterSelect::customer('customer_id')
                            ->label(__('Customer'))
                            ->disabled()
                            ->dehydrated(false),

                        MasterSelect::product('product_id')
                            ->label(__('Product'))
                            ->disabled()
                            ->dehydrated(false),

                        ...static::specFields(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'product']))
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight_per_pack')
                    ->label(__('Weight per pack'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('cut')
                    ->label(__('Cut'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('condition')
                    ->label(__('Condition'))
                    ->formatStateUsing(fn (?string $state): ?string => CustomerProductSpec::conditionLabel($state)),
                Tables\Columns\TextColumn::make('packaging')
                    ->label(__('Packaging'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('note')
                    ->label(__('Spec Note'))
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                MasterSelect::filter(
                    Tables\Filters\SelectFilter::make('customer_id')
                        ->label(__('Customer')),
                    Customer::class,
                ),
                MasterSelect::filter(
                    Tables\Filters\SelectFilter::make('product_id')
                        ->label(__('Product')),
                    Product::class,
                ),
            ])
            ->defaultGroup(
                Tables\Grouping\Group::make('customer.name')
                    ->label(__('Customer'))
                    ->collapsible()
            )
            ->groupingSettingsHidden()
            ->defaultSort('id', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('excel')
                    ->label(__('Excel'))
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->with(['customer', 'product'])->get();

                        return response()->streamDownload(function () use ($records) {
                            $writer = new \OpenSpout\Writer\XLSX\Writer();
                            $writer->openToFile('php://output');
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                __('Customer'), __('Product'), __('Weight per pack'), __('Cut'),
                                __('Condition'), __('Packaging'), __('Spec Note'),
                            ]));
                            foreach ($records as $record) {
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    $record->customer?->name, $record->product?->name, $record->weight_per_pack,
                                    $record->cut, CustomerProductSpec::conditionLabel($record->condition),
                                    $record->packaging, $record->note,
                                ]));
                            }
                            $writer->close();
                        }, 'customer-specs.xlsx');
                    }),
            ])
            ->actions([
                // Clickable rows handle editing.
            ])
            ->recordUrl(
                fn (CustomerProductSpec $record): string => Pages\EditCustomerProductSpec::getUrl([$record->id])
            )
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerProductSpecs::route('/'),
            'create' => Pages\CreateCustomerProductSpec::route('/create'),
            'edit' => Pages\EditCustomerProductSpec::route('/{record}/edit'),
        ];
    }
}
