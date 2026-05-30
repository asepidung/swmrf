<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyBankResource\Pages;
use App\Models\CompanyBank;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanyBankResource extends Resource
{
    protected static ?string $model = CompanyBank::class;

    // Biar logonya nyambung sama tema keuangan & kumpul di menu Finance
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'FINANCE';
    protected static ?string $navigationLabel = 'Bank Master';
    protected static ?int $navigationSort = 18;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Rekening')
                    ->description('Masukkan detail bank atau kas yang digunakan perusahaan.')
                    ->schema([
                        Forms\Components\TextInput::make('initial')
                            ->label('Initial (Nama Internal)')
                            ->placeholder('Contoh: BCA PT')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('bank_name')
                            ->label('Nama Bank Resmi (Untuk Invoice)')
                            ->placeholder('Contoh: BCA KCP BEKASI CITRA GRAND')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('account_number')
                            ->label('Nomor Rekening')
                            ->placeholder('Kosongkan jika Kas Tunai')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('account_name')
                            ->label('Atas Nama')
                            ->placeholder('Contoh: PT SANTI WIJAYA MEAT')
                            ->maxLength(255),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif Digunakan?')
                            ->default(true)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('initial')
                    ->label('Initial')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank / Metode')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('account_number')
                    ->label('Acc. Number')
                    ->copyable() // Biar Finance gampang copy-paste no rek
                    ->searchable()
                    ->default('-'),

                Tables\Columns\TextColumn::make('account_name')
                    ->label('Acc. Name')
                    ->searchable()
                    ->default('-'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Is Active?')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanyBanks::route('/'),
            'create' => Pages\CreateCompanyBank::route('/create'),
            'edit' => Pages\EditCompanyBank::route('/{record}/edit'),
        ];
    }
}
