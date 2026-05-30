<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankLedgerResource\Pages;
use App\Models\BankLedger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder; // <-- TAMBAHAN JAMU

class BankLedgerResource extends Resource
{
    protected static ?string $model = BankLedger::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'FINANCE';
    protected static ?string $navigationLabel = 'Bank Statement';
    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('companyBank.initial')
                    ->label('Account')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('debit')
                    ->label('In (Debit)')
                    ->money('IDR')
                    ->color('success')
                    ->alignEnd()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total In')),

                Tables\Columns\TextColumn::make('credit')
                    ->label('Out (Credit)')
                    ->money('IDR')
                    ->color('danger')
                    ->alignEnd()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total Out')),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Running Balance')
                    ->money('IDR')
                    ->weight('bold')
                    ->alignEnd()
                    ->summarize(
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('Balance')
                            // FIX DI SINI: Pake QueryBuilder, bukan Eloquent Builder
                            ->using(
                                fn(QueryBuilder $query) =>
                                $query->sum('debit') - $query->sum('credit')
                            )
                            ->formatStateUsing(fn($state) => 'IDR ' . number_format($state, 2, '.', ','))
                    ),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('company_bank_id')
                    ->label('Filter by Bank')
                    ->relationship('companyBank', 'initial'),

                Filter::make('transaction_date')
                    ->form([
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('until')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn($q) => $q->whereDate('transaction_date', '>=', $data['from']))
                            ->when($data['until'], fn($q) => $q->whereDate('transaction_date', '<=', $data['until']));
                    })
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankLedgers::route('/'),
        ];
    }
}
