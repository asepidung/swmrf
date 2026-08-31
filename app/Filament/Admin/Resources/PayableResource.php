<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PayableResource\Pages;
use App\Models\Payable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PayableResource extends Resource
{
    protected static ?string $model = Payable::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';



    public static function getNavigationGroup(): ?string
    {
        return __('ACCOUNTING');
    }

    public static function getNavigationLabel(): string
    {
        return __('Payables');
    }

    public static function getModelLabel(): string
    {
        return __('Payable');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payables');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Payable Details'))
                    ->schema([
                        Forms\Components\TextInput::make('document_number')
                            ->label(__('Document Number'))
                            ->disabled(),
                        Forms\Components\TextInput::make('payableable_type')
                            ->label(__('Source Document Type'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => Payable::sourceLabel($state)),
                        Forms\Components\TextInput::make('supplier_name')
                            ->label(__('Supplier'))
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => data_get($record, 'supplier.name')),
                        Forms\Components\DatePicker::make('due_date')
                            ->label(__('Due Date'))
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label(__('Total Amount'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => 'Rp ' . number_format((float)$state, 0, ',', '.')),
                        Forms\Components\TextInput::make('paid_amount')
                            ->label(__('Paid Amount'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => 'Rp ' . number_format((float)$state, 0, ',', '.')),
                        Forms\Components\TextInput::make('balance')
                            ->label(__('Outstanding Balance'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => 'Rp ' . number_format((float)$state, 0, ',', '.')),
                        Forms\Components\TextInput::make('status')
                            ->label(__('Status'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => __($state)),
                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(3)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label(__('Document Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                // Kategori pembelian: tanpa ini, hutang sapi tidak bisa
                // dipisahkan dari hutang daging atau barang sama sekali.
                Tables\Columns\TextColumn::make('payableable_type')
                    ->label(__('Category'))
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => Payable::sourceLabel($state))
                    ->color(fn (?string $state): string => Payable::sourceColors()[$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable(),
                // Total Amount dan Paid Amount sengaja TIDAK di sini.
                //
                // Keduanya bisa dihitung ulang dari sisa dan sudah tampil di
                // halaman detail. Yang dicari orang di daftar hutang adalah
                // siapa, berapa sisanya, dan kapan jatuh temponya.
                Tables\Columns\TextColumn::make('balance')
                    ->label(__('Outstanding Balance'))
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label(__('Due Date'))
                    ->date('d M Y')
                    ->sortable()
                    ->tooltip(function (Payable $record): ?string {
                        if (!$record->due_date) return null;
                        
                        $today = now()->startOfDay();
                        $due = \Carbon\Carbon::parse($record->due_date)->startOfDay();
                        $diff = $today->diffInDays($due, false);
                        
                        if ($record->status === 'paid') {
                            return __('Paid');
                        }

                        if ($diff < 0) {
                            return __('Overdue by :days days', ['days' => abs($diff)]);
                        } elseif ($diff === 0) {
                            return __('Due today');
                        } else {
                            return __('Remaining :days days', ['days' => $diff]);
                        }
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'danger',
                        'partial' => 'warning',
                        'paid' => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => __($state)),
            ])
            ->recordUrl(
                fn (Payable $record): string => Pages\ViewPayable::getUrl([$record->id]),
            )
            ->headerActions([
                \Filament\Tables\Actions\ActionGroup::make([
                    \Filament\Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'Date', 'Document No.', 'Category', 'Supplier', 'Total Amount (Rp)', 'Paid Amount (Rp)', 'Outstanding Balance (Rp)', 'Due Date', 'Status', 'Notes'
                                ]));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->created_at ?? '',
                                        $record->document_number ?? '',
                                        Payable::sourceLabel($record->payableable_type),
                                        $record->supplier?->name ?? '',
                                        $record->amount ?? '',
                                        $record->paid_amount ?? '',
                                        $record->balance ?? '',
                                        $record->due_date ?? '',
                                        $record->status ?? '',
                                        $record->note ?? ''
                                    ]));
                                }
                                $writer->close();
                            }, 'excel.xlsx');
                        }),
                    \Filament\Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.payables-pdf', [
                                'records' => $records,
                                'title' => __('Supplier Payables')
                              ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export_payables.pdf');
                        }),
                ])
                ->label('Export Data')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_payables')),
                Tables\Filters\SelectFilter::make('payableable_type')
                    ->label(__('Category'))
                    ->options(fn (): array => Payable::sourceLabels()),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label(__('Supplier')),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('From'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('Until'))
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(fn (array $data): array => []),
            ])
            ->actions([
                // Clickable rows handle view action, no need for action buttons
            ])
            ->bulkActions([
                // Read-only, no bulk delete
            ])
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
            'index' => Pages\ListPayables::route('/'),
            'view' => Pages\ViewPayable::route('/{record}'),
        ];
    }
}
