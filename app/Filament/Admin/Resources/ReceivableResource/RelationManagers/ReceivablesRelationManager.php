<?php

namespace App\Filament\Admin\Resources\ReceivableResource\RelationManagers;

use App\Models\Receivable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReceivablesRelationManager extends RelationManager
{
    protected static string $relationship = 'receivables';

    protected static ?string $title = 'Rincian Invoice (Piutang)';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_id')
            ->columns([
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label(__('Invoice Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer (Cabang)'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.invoice_date')
                    ->label(__('Invoice Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label(__('Due Date'))
                    ->getStateUsing(function (Receivable $record) {
                        $invoice = $record->invoice;
                        if (!$invoice) return '-';
                        
                        $tukarfaktur = $record->customer->invoice_exchange ?? false;
                        $status = $invoice->status;
                        $tgltf = $invoice->invoice_exchange_date;

                        if ($tukarfaktur && empty($tgltf) && $status === 'Belum TF') {
                            return 'Belum TF';
                        }
                        
                        return $invoice->due_date ? $invoice->due_date->format('d M Y') : '-';
                    })
                    ->badge()
                    ->color(function (Receivable $record) {
                        $invoice = $record->invoice;
                        if (!$invoice || $invoice->status === 'Lunas') return 'gray';
                        
                        $tukarfaktur = $record->customer->invoice_exchange ?? false;
                        $tgltf = $invoice->invoice_exchange_date;
                        if ($tukarfaktur && empty($tgltf) && $invoice->status === 'Belum TF') {
                            return 'danger';
                        }
                        
                        if (!$invoice->due_date) return 'gray';

                        $today = now()->startOfDay();
                        $due = \Carbon\Carbon::parse($invoice->due_date)->startOfDay();
                        $diff = $today->diffInDays($due, false);

                        if ($diff < 0) {
                            return 'danger';
                        } elseif ($diff === 0) {
                            return 'warning';
                        }
                        return 'success';
                    })
                    ->tooltip(function (Receivable $record): ?string {
                        $invoice = $record->invoice;
                        if (!$invoice || !$invoice->due_date || $invoice->status === 'Lunas') return null;
                        
                        $tukarfaktur = $record->customer->invoice_exchange ?? false;
                        $tgltf = $invoice->invoice_exchange_date;
                        if ($tukarfaktur && empty($tgltf) && $invoice->status === 'Belum TF') {
                            return __('Belum Tukar Faktur');
                        }

                        $today = now()->startOfDay();
                        $due = \Carbon\Carbon::parse($invoice->due_date)->startOfDay();
                        $diff = $today->diffInDays($due, false);
                        
                        if ($diff < 0) {
                            return __('Overdue by :days days', ['days' => abs($diff)]);
                        } elseif ($diff === 0) {
                            return __('Due today');
                        } else {
                            return __('Remaining :days days', ['days' => $diff]);
                        }
                    }),

                Tables\Columns\TextColumn::make('invoice.balance')
                    ->label(__('Outstanding Balance'))
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('invoice.status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors([
                        'danger' => 'Belum TF',
                        'success' => 'Sudah TF',
                        'primary' => 'Lunas',
                        'gray' => '-',
                    ])
                    ->formatStateUsing(fn ($state) => __($state)),
            ])
            ->filters([
                // Simple filters can be added here if needed
            ])
            ->headerActions([
                // Remove create action
            ])
            ->actions([
                // No static actions like Tukar Faktur here. Read-only overview for now.
                // Later, Payment actions will be added here.
            ])
            ->bulkActions([
                // 
            ])
            ->defaultSort('invoice_id', 'desc');
    }
}
