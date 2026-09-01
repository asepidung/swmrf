<?php

namespace App\Filament\Admin\Resources\PurchaseProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\SupplierPayment;

class SupplierPaymentsRelationManager extends RelationManager
{
    protected static function getModelLabel(): ?string
    {
        return __('Payment');
    }

    protected static string $relationship = 'supplierPayments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Payment History');
    }


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
                Tables\Columns\TextColumn::make('payment_number')->label(__('Payment Number')),
                Tables\Columns\TextColumn::make('payment_date')->label(__('Date'))->date('d M Y'),
                Tables\Columns\TextColumn::make('method')->label(__('Method'))->formatStateUsing(fn ($state) => match($state) {
                    'cash' => __('Cash'),
                    'transfer' => __('Transfer'),
                    default => $state,
                }),
                Tables\Columns\TextColumn::make('bankAccount.bank_name')->label(__('Bank Account'))->default(__('Cash')),
                Tables\Columns\TextColumn::make('amount')->label(__('Amount'))->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('allocated_amount')->label(__('Allocated Amount'))->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('note')->label(__('Note')),
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
