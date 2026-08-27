<?php

namespace App\Filament\Admin\Resources\PurchaseProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\SupplierPayment;

class SupplierPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierPayments';

    protected static ?string $title = 'Riwayat Pembayaran Uang Muka (DP)';
    protected static ?string $modelLabel = 'Pembayaran';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('payment_number')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payment_number')
            ->columns([
                Tables\Columns\TextColumn::make('payment_number')->label('No. Pembayaran'),
                Tables\Columns\TextColumn::make('payment_date')->label('Tanggal')->date('d M Y'),
                Tables\Columns\TextColumn::make('method')->label('Metode')->formatStateUsing(fn ($state) => match($state) {
                    'cash' => 'Tunai',
                    'transfer' => 'Transfer',
                    default => $state,
                }),
                Tables\Columns\TextColumn::make('bankAccount.bank_name')->label('Rekening')->default('Kas Tunai'),
                Tables\Columns\TextColumn::make('amount')->label('Nominal')->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('allocated_amount')->label('Terpotong (Dipakai)')->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('note')->label('Catatan'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}
