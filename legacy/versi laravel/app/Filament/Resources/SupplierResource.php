<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Supplier';
    protected static ?string $navigationGroup = 'SUPPLIERS';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Supplier';
    protected static ?string $pluralModelLabel = 'Suppliers';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Profil & Kontak')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Supplier Name')
                            ->required()
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->dehydrateStateUsing(fn($state) => strtoupper($state)),

                        Forms\Components\TextInput::make('contact_person')
                            ->label('Contact Person (PIC)')
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->dehydrateStateUsing(fn($state) => strtoupper($state)),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel(),

                        Forms\Components\TextInput::make('term_of_payment')
                            ->label('TOP (Days)')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('has_tax')
                            ->label('Taxable 11%')
                            ->default(false),

                        Forms\Components\Textarea::make('address')
                            ->label('Full Address')
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->dehydrateStateUsing(fn($state) => strtoupper($state))
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Informasi Rekening Bank')
                    ->description('Data ini penting untuk bagian keuangan saat pembayaran invoice.')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Nama Bank')
                            ->placeholder('Contoh: BCA / MANDIRI')
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->dehydrateStateUsing(fn($state) => strtoupper($state)),

                        Forms\Components\TextInput::make('bank_account_no')
                            ->label('Nomor Rekening'),

                        Forms\Components\TextInput::make('bank_account_name')
                            ->label('Atas Nama Rekening')
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->dehydrateStateUsing(fn($state) => strtoupper($state)),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama Supplier')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('contact_person')->label('PIC'),
                Tables\Columns\TextColumn::make('phone')->label('Telepon'),
                Tables\Columns\TextColumn::make('term_of_payment')->label('TOP')->suffix(' Hari'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Status Aktif'),
                Tables\Columns\TextColumn::make('term_of_payment')
                    ->label('TOP')
                    ->suffix(' Days')
                    ->sortable(),

                Tables\Columns\IconColumn::make('has_tax')
                    ->label('Tax (11%)')
                    ->boolean(),
            ])
            ->recordUrl(null)
            ->recordAction('view')
            ->actions([
                Tables\Actions\ViewAction::make()->extraAttributes(['style' => 'display: none !important;']),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('name', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
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
