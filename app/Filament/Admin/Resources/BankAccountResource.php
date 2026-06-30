<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankAccountResource\Pages;
use App\Filament\Admin\Resources\BankAccountResource\RelationManagers;
use App\Models\BankAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function canAccess(): bool
    {
        // Fitur bank accounts disembunyikan atas instruksi owner
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('MASTER DATA');
    }

    public static function getModelLabel(): string
    {
        return __('Bank Account');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Bank Accounts');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('initial')
                    ->label(__('Bank Initial'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->autofocus(),
                Forms\Components\TextInput::make('bank_name')
                    ->label(__('Bank Name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('account_number')
                    ->label(__('Account Number'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('account_holder')
                    ->label(__('Account Holder'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('Is Active'))
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('initial')
                    ->label(__('Bank Initial'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('bank_name')
                    ->label(__('Bank Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->label(__('Account Number'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('account_holder')
                    ->label(__('Account Holder'))
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Is Active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(
                fn (BankAccount $record): string => Pages\EditBankAccount::getUrl([$record->getKey()]),
            )
            ->filters([
                //
            ])
            ->actions([
                //
            ])
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
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}
