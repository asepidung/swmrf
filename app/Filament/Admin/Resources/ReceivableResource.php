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
    protected static ?string $model = Receivable::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function getNavigationGroup(): ?string
    {
        return __('FINANCE');
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
        return $form
            ->schema([
                Forms\Components\Section::make(__('Receivable Details'))
                    ->schema([
                        Forms\Components\TextInput::make('invoice.invoice_number')
                            ->label(__('Invoice Number'))
                            ->disabled(),
                        Forms\Components\TextInput::make('customer.name')
                            ->label(__('Customer'))
                            ->disabled(),
                        Forms\Components\DatePicker::make('invoice.invoice_date')
                            ->label(__('Invoice Date'))
                            ->disabled(),
                        Forms\Components\TextInput::make('invoice.term_of_payment')
                            ->label(__('T.O.P (Days)'))
                            ->disabled(),
                        Forms\Components\DatePicker::make('invoice.due_date')
                            ->label(__('Due Date'))
                            ->disabled(),
                        Forms\Components\TextInput::make('invoice.balance')
                            ->label(__('Outstanding Balance'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => 'Rp ' . number_format((float)$state, 0, ',', '.')),
                        Forms\Components\TextInput::make('invoice.status')
                            ->label(__('Status'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => __($state)),
                    ])->columns(3)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label(__('Invoice Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.invoice_date')
                    ->label(__('Invoice Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.term_of_payment')
                    ->label(__('T.O.P (Days)'))
                    ->numeric()
                    ->alignCenter()
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
                            return 'BTF';
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
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_receivables')),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('invoice_date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('date_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['date_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['date_until'] ?? now()->toDateString();

                        return $query->whereHas('invoice', function ($q) use ($from, $until) {
                            $q->when($from, fn ($q, $date) => $q->whereDate('invoice_date', '>=', $date))
                              ->when($until, fn ($q, $date) => $q->whereDate('invoice_date', '<=', $date));
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['date_from'])->format('d M Y');
                        }
                        if ($data['date_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['date_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('tukar_faktur')
                    ->label(__('Tukar Faktur'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Receivable $record) => $record->invoice && $record->invoice->status === 'Belum TF' && auth()->user()->hasPermission('tukar_faktur') && !$record->trashed())
                    ->form([
                        Forms\Components\DatePicker::make('invoice_exchange_date')
                            ->label(__('Tanggal Tukar Faktur'))
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('note')
                            ->label(__('Bukti (Resi, Email, dll)'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Receivable $record, array $data) {
                        $invoice = $record->invoice;
                        if ($invoice) {
                            $tgltf = $data['invoice_exchange_date'];
                            $note = $data['note'];
                            
                            $top = (int)$invoice->term_of_payment;
                            $dueDate = \Carbon\Carbon::parse($tgltf)->addDays($top)->toDateString();
                            
                            $newNote = $invoice->note ? $invoice->note . ' | ' . $note : $note;

                            $invoice->update([
                                'status' => 'Sudah TF',
                                'invoice_exchange_date' => $tgltf,
                                'due_date' => $dueDate,
                                'note' => $newNote,
                            ]);
                        }
                    }),

                Tables\Actions\Action::make('print')
                    ->label(__('Print'))
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (Receivable $record) => route('print.invoice', $record->invoice_id))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ExportAction::make('excel')
                        ->label('Excel')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->exporter(\App\Filament\Exports\ReceivableExporter::class)
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.receivables-pdf', [
                                'records' => $records,
                                'title' => __('Daftar Piutang Customer')
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export_receivables.pdf');
                        }),
                ])
                ->label('Export Data')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->bulkActions([])
            ->recordUrl(function (Receivable $record) {
                return Pages\ViewReceivable::getUrl([$record->id]);
            })
            ->defaultSort('id', 'desc');
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
            'index' => Pages\ListReceivables::route('/'),
            'view' => Pages\ViewReceivable::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
