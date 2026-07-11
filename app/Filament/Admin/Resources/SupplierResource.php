<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SupplierResource\Pages;
use App\Filament\Admin\Resources\SupplierResource\RelationManagers;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationGroup(): ?string
    {
        return __('MASTER DATA');
    }

    public static function getNavigationLabel(): string
    {
        return __('Suppliers');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Suppliers');
    }

    public static function getModelLabel(): string
    {
        return __('Supplier');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('Supplier Information'))
                            ->schema([
                                Forms\Components\TextInput::make('name')->unique(ignoreRecord: true)
                                    ->label(fn() => __('Supplier Name'))
                                    ->autofocus()
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('pic')
                                    ->label(fn() => __('PIC / Person In Charge'))
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('phone')
                                    ->label(fn() => __('Phone Number'))
                                    ->tel()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('supplied_goods')
                                    ->label(fn() => __('Supplied Goods'))
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('address')
                                    ->label(fn() => __('Address'))
                                    ->required()
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Forms\Components\Section::make(__('Bank Account Details'))
                            ->description(__('Supplier bank transfer account information'))
                            ->schema([
                                Forms\Components\TextInput::make('bank_name')
                                    ->label(fn() => __('Bank Name'))
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('account_number')
                                    ->label(fn() => __('Account Number'))
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('account_name')
                                    ->label(fn() => __('Account Name'))
                                    ->maxLength(255),
                            ])->columns(3),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('Settings & Tax'))
                            ->schema([
                                Forms\Components\TextInput::make('top_days')
                                    ->label(fn() => __('Term of Payment (Days)'))
                                    ->numeric()
                                    ->required(),
                                                                Forms\Components\Radio::make('is_tax_11')
                                    ->label(fn() => __('Tax 11%'))
                                    ->boolean()
                                    ->required()
                                    ->inline()
                                    ->helperText(fn() => __('Enable 11% value-added tax for this supplier')),
                                Forms\Components\Toggle::make('is_active')
                                    ->label(fn() => __('Active Status'))
                                    ->helperText(__('Toggle supplier active state'))
                                    ->default(true),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(fn() => __('Supplier Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pic')
                    ->label(fn() => __('PIC'))
                    ->searchable()
                    ->sortable(),
                                Tables\Columns\TextColumn::make('supplied_goods')
                    ->label(fn() => __('Supplied Goods'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(fn() => __('Phone Number'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('top_days')
                    ->label(fn() => __('T.O.P (Days)'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_tax_11')
                    ->label(fn() => __('Tax 11%'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(fn() => __('Active Status'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(fn() => __('Active Status')),
                Tables\Filters\TernaryFilter::make('is_tax_11')
                    ->label(fn() => __('Tax 11%')),
            ])
            ->actions([
                // No action columns to keep clean, clickable rows are used instead.
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (Supplier $record): string => Pages\EditSupplier::getUrl(['record' => $record]));
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
