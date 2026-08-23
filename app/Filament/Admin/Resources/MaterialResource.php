<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialResource\Pages;
use App\Filament\Admin\Resources\MaterialResource\RelationManagers;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static ?string $cluster = \App\Filament\Clusters\Materials::class;

    protected static \Filament\Pages\SubNavigationPosition $subNavigationPosition = \Filament\Pages\SubNavigationPosition::Top;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    
    protected static ?string $navigationLabel = 'Material Items';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Material Item';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Logistic Item Information'))
                    ->schema([
                        Forms\Components\TextInput::make('code')->unique(ignoreRecord: true)
                            ->label(fn() => __('Item Code'))
                            ->placeholder(__('Auto-generated'))
                            ->visibleOn('view')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('name')->unique(ignoreRecord: true)
                            ->label(fn() => __('Item Name'))
                            ->autofocus()
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
                        Forms\Components\Select::make('material_unit_id')
                            ->label(fn() => __('Unit'))
                            ->relationship('unit', 'name')
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->unique(ignoreRecord: true)
                                    ->required()
                                    ->maxLength(255),
                            ]),
                        Forms\Components\Select::make('material_category_id')
                            ->label(fn() => __('Category'))
                            ->relationship('category', 'name')
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->unique(ignoreRecord: true)
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
                            ]),
                        Forms\Components\TextInput::make('min_stock')
                            ->label(fn() => __('Min. Stock'))
                            ->numeric()
                            ->required(),
                        Forms\Components\Toggle::make('show_in_stock')
                            ->label(fn() => __('Show in Stock List?'))
                            ->helperText(__('Turn OFF for non-inventory items like Office Supplies.'))
                            ->default(true)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label(fn() => __('Is Active'))
                            ->default(true)
                            ->columnSpanFull()
                            ->visibleOn('edit'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(fn() => __('Item Code'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(fn() => __('Item Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label(fn() => __('Category'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label(fn() => __('Unit'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_stock')
                    ->label(fn() => __('Min. Stock'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(fn() => __('Active'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('show_in_stock')
                    ->label(fn() => __('Show in Stock'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('material_category_id')
                    ->relationship('category', 'name')
                    ->label(fn() => __('Category')),
                Tables\Filters\TernaryFilter::make('show_in_stock')
                    ->label(fn() => __('Show In Stock')),
            ])
            ->actions([
                //
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
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Item Code', 'Item Name', 'Category', 'Unit', 'Min. Stock', 'Active', 'Show in Stock']));
                            foreach ($records as $record) {
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    $record->code ?? '',
                                    $record->name ?? '',
                                    $record->category?->name ?? '',
                                    $record->unit?->name ?? '',
                                    $record->min_stock ?? 0,
                                    $record->is_active ? 'Yes' : 'No',
                                    $record->show_in_stock ? 'Yes' : 'No',
                                ]));
                            }
                            $writer->close();
                        }, 'Materials.xlsx');
                    }),
            ])
            ->recordUrl(
                fn (\Illuminate\Database\Eloquent\Model $record): string => Pages\EditMaterial::getUrl([$record->id])
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
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
