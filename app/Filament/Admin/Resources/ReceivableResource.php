<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReceivableResource\Pages;
use App\Models\Receivable;
use App\Models\Invoice;
use App\Filament\Admin\Resources\InvoiceResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReceivableResource extends Resource
{
    protected static ?string $model = \App\Models\CustomerGroup::class;

    protected static ?string $slug = 'receivables';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';



    public static function getNavigationGroup(): ?string
    {
        return __('ACCOUNTING');
    }

    public static function getNavigationLabel(): string
    {
        return __('Receivables');
    }

    public static function getModelLabel(): string
    {
        return __('Receivable');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Receivables');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Group Name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('total_piutang')
                    ->label(__('Total Piutang'))
                    ->getStateUsing(function (\App\Models\CustomerGroup $record) {
                        return $record->receivables()
                            ->join('invoices', 'receivables.invoice_id', '=', 'invoices.id')
                            ->where('invoices.status', '!=', 'Lunas')
                            ->sum('invoices.balance');
                    })
                    ->money('IDR', locale: 'id')
                    ->description(function (\App\Models\CustomerGroup $record) {
                        $count = $record->receivables()
                            ->whereHas('invoice', function ($q) {
                                $q->where('status', '!=', 'Lunas');
                            })->count();
                        return $count . ' Inv';
                    })
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('akan_jatuh_tempo')
                    ->label(__('Akan Jatuh Tempo'))
                    ->getStateUsing(function (\App\Models\CustomerGroup $record) {
                        return $record->receivables()
                            ->join('invoices', 'receivables.invoice_id', '=', 'invoices.id')
                            ->where('invoices.status', '!=', 'Lunas')
                            ->whereNotNull('invoices.due_date')
                            ->whereBetween('invoices.due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
                            ->sum('invoices.balance');
                    })
                    ->money('IDR', locale: 'id')
                    ->description(function (\App\Models\CustomerGroup $record) {
                        $count = $record->receivables()
                            ->whereHas('invoice', function ($q) {
                                $q->where('status', '!=', 'Lunas')
                                  ->whereNotNull('due_date')
                                  ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()]);
                            })->count();
                        return $count > 0 ? $count . ' Inv' : '';
                    })
                    ->alignEnd()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('sudah_jatuh_tempo')
                    ->label(__('Sudah Jatuh Tempo'))
                    ->getStateUsing(function (\App\Models\CustomerGroup $record) {
                        return $record->receivables()
                            ->join('invoices', 'receivables.invoice_id', '=', 'invoices.id')
                            ->where('invoices.status', '!=', 'Lunas')
                            ->whereNotNull('invoices.due_date')
                            ->whereDate('invoices.due_date', '<', now()->toDateString())
                            ->sum('invoices.balance');
                    })
                    ->money('IDR', locale: 'id')
                    ->description(function (\App\Models\CustomerGroup $record) {
                        $count = $record->receivables()
                            ->whereHas('invoice', function ($q) {
                                $q->where('status', '!=', 'Lunas')
                                  ->whereNotNull('due_date')
                                  ->whereDate('due_date', '<', now()->toDateString());
                            })->count();
                        return $count > 0 ? $count . ' Inv' : '';
                    })
                    ->alignEnd()
                    ->color('danger'),
            ])
            ->filters([
                // We can add simple filters if needed, but for now we keep it clean.
            ])
            ->actions([
                // No static actions, we only click the row to view details.
            ])
            ->headerActions([
                // 
            ])
            ->bulkActions([])
            ->recordUrl(function (\App\Models\CustomerGroup $record) {
                return Pages\ViewReceivable::getUrl([$record->id]);
            })
            ->defaultSort('name', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Admin\Resources\ReceivableResource\RelationManagers\ReceivablesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReceivables::route('/'),
            'view' => Pages\ViewReceivable::route('/{record}'),
            'payment' => Pages\ReceivePayment::route('/{record}/payment'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('receivables.invoice', function (Builder $query) {
                $query->where('status', '!=', 'Lunas');
            });
    }
}
