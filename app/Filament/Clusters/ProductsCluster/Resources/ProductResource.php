<?php

namespace App\Filament\Clusters\ProductsCluster\Resources;

use App\Filament\Clusters\ProductsCluster;
use App\Filament\Clusters\ProductsCluster\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $cluster = ProductsCluster::class;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Beef');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Beef');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Basic Information'))
                    ->schema([
                        Forms\Components\Radio::make('structure_type')
                            ->label(fn() => __('Product Structure'))
                            ->options([
                                'main' => __('Main Beef'),
                                'sub' => __('Sub Beef / Variant'),
                            ])
                            ->default('main')
                            ->live()
                            ->disabled(fn ($context) => $context === 'edit')
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                if ($get('structure_type') === 'main') {
                                    $set('parent_id', null);
                                }
                                static::updateCode($set, $get);
                            }),

                        Forms\Components\Select::make('parent_id')
                            ->label(fn() => __('Parent Beef'))
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereNull('parent_id')->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disabled(fn ($context) => $context === 'edit')
                            ->visible(fn (Forms\Get $get) => $get('structure_type') === 'sub')
                            ->required(fn (Forms\Get $get) => $get('structure_type') === 'sub')
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state) {
                                    $parent = Product::find($state);
                                    if ($parent) {
                                        $set('category_id', $parent->category_id);
                                    }
                                }
                                static::updateCode($set, $get);
                            }),

                        Forms\Components\Select::make('category_id')
                            ->label(fn() => __('Category'))
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (Forms\Get $get, $context) => $context === 'edit' || $get('structure_type') === 'sub')
                            ->dehydrated()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                static::updateCode($set, $get);
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label(fn() => __('Category Name'))
                                    ->required()
                                    ->unique('product_categories', 'name')
                                    ->maxLength(255)
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                                Forms\Components\TextInput::make('prefix')
                                    ->label(fn() => __('Prefix (Code)'))
                                    ->required()
                                    ->numeric()
                                    ->unique('product_categories', 'prefix')
                                    // Usulkan prefix berikutnya supaya operator tidak perlu
                                    // membuka daftar kategori dulu untuk mencari yang terpakai.
                                    ->default(fn () => \App\Models\ProductCategory::max('prefix') + 1)
                                    ->helperText(fn () => __('Suggested from the highest existing prefix. Change it if needed.')),
                            ])
                            ->createOptionAction(
                                fn (Forms\Components\Actions\Action $action) => $action->modalWidth('md')->color('warning')
                            ),

                        Forms\Components\TextInput::make('name')
                            ->label(fn() => __('Beef Name'))
                            ->autofocus()
                            ->required()
                            ->unique(ignorable: fn ($record) => $record)
                            ->validationMessages([
                                'unique' => 'Nama barang ini sudah terdaftar di sistem, pakai nama lain!',
                            ])
                            ->maxLength(255)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                        Forms\Components\TextInput::make('code')
                            ->label(fn() => __('Beef Code'))
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->unique(ignorable: fn ($record) => $record),

                        Forms\Components\Toggle::make('is_active')
                            ->label(fn() => __('Set Active'))
                            ->default(true)
                            ->visibleOn('edit'),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(fn() => __('Beef Code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(fn() => __('Beef Name'))
                    ->searchable()
                    ->sortable()
                    ->description(function (Product $record) {
                        if ($record->structure_type === 'main') {
                            return __('Main Beef');
                        }
                        if ($record->structure_type === 'sub' && $record->parent) {
                            return __('Variant of') . ': ' . $record->parent->name;
                        }
                        return null;
                    }),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(fn() => __('Category'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(fn() => __('Set Active')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label(fn() => __('Category')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_excel')
                    ->label(fn() => __('Excel'))
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->get();
                        return response()->streamDownload(function () use ($records) {
                            $writer = new \OpenSpout\Writer\XLSX\Writer();
                            $writer->openToFile('php://output');
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Beef Code', 'Beef Name', 'Structure', 'Category', 'Active']));
                            foreach ($records as $record) {
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    $record->code ?? '',
                                    $record->name ?? '',
                                    $record->structure_type === 'main' ? 'Main' : 'Variant of: ' . ($record->parent?->name ?? ''),
                                    $record->category?->name ?? '',
                                    $record->is_active ? 'Yes' : 'No',
                                ]));
                            }
                            $writer->close();
                        }, 'Beefs.xlsx');
                    }),
            ])
            ->actions([
                // Clickable rows handles edit redirection, actions left clean per project rules
            ])
            ->recordUrl(
                fn (Product $record): string => Pages\EditProduct::getUrl([$record->id])
            )
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function updateCode(callable $set, callable $get): void
    {
        $structureType = $get('structure_type');

        if ($structureType === 'main') {
            $categoryId = $get('category_id');
            if (!$categoryId) {
                $set('code', null);
                return;
            }

            $category = \App\Models\ProductCategory::find($categoryId);
            if (!$category || !$category->prefix) {
                $set('code', null);
                return;
            }

            $prefix = (string) $category->prefix;
            $prefixLen = strlen($prefix);

            $maxUrut = Product::where('category_id', $categoryId)
                ->whereNull('parent_id')
                ->get()
                ->map(function ($product) use ($prefix, $prefixLen) {
                    $code = $product->code;
                    if (str_starts_with($code, $prefix) && str_ends_with($code, '00') && strlen($code) === ($prefixLen + 5)) {
                        return (int) substr($code, $prefixLen, 3);
                    }
                    return 0;
                })
                ->max() ?? 0;

            $nomorUrut = $maxUrut + 1;
            // e.g. prefix 1, urut 1 -> 100100 (prefix + 001 + 00)
            $code = $prefix . sprintf('%03d', $nomorUrut) . '00';
            $set('code', $code);
        } elseif ($structureType === 'sub') {
            $parentId = $get('parent_id');
            if (!$parentId) {
                $set('code', null);
                return;
            }

            $parent = Product::find($parentId);
            if (!$parent || !$parent->code) {
                $set('code', null);
                return;
            }

            $parentCode = (int) $parent->code;
            $count = Product::where('parent_id', $parentId)->count();

            $nextCode = (string) ($parentCode + $count + 1);

            // Collision check
            while (Product::where('code', $nextCode)->exists()) {
                $count++;
                $nextCode = (string) ($parentCode + $count + 1);
            }

            $set('code', $nextCode);
        } else {
            $set('code', null);
        }
    }
}
