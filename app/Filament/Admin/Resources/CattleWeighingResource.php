<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CattleWeighingResource\Pages;
use App\Filament\Admin\Resources\CattleWeighingResource\RelationManagers;
use App\Models\CattleWeighing;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CattleWeighingResource extends Resource
{
    protected static ?string $model = CattleWeighing::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?int $navigationSort = 2;
    public static function getNavigationGroup(): ?string
    {
        return __('CATTLE');
    }

    public static function getNavigationLabel(): string
    {
        return __('Weighing');
    }

    public static function getModelLabel(): string
    {
        return __('Cattle Weighing');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Cattle Weighings');
    }



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Weighing Header'))
                    ->schema([
                        Forms\Components\Hidden::make('cattle_receiving_id')
                            ->default(fn() => request()->query('receiving_id'))
                            ->unique(ignoreRecord: true, modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule) {
                                return $rule->whereNull('deleted_at');
                            }),
                        Forms\Components\TextInput::make('receiving_number')
                            ->label(__('Receive Number'))
                            ->disabled()
                            ->dehydrated(false)
                            ->default(function() {
                                $receivingId = request()->query('receiving_id');
                                return $receivingId ? \App\Models\CattleReceiving::find($receivingId)?->receiving_number : null;
                            }),
                        Forms\Components\TextInput::make('po_number')
                            ->label(__('PO Number'))
                            ->disabled()
                            ->dehydrated(false)
                            ->default(function() {
                                $receivingId = request()->query('receiving_id');
                                return $receivingId ? \App\Models\CattleReceiving::with('purchaseCattle')->find($receivingId)?->purchaseCattle?->document_number : null;
                            }),
                        Forms\Components\TextInput::make('supplier_name')
                            ->label(__('Supplier'))
                            ->disabled()
                            ->dehydrated(false)
                            ->default(function() {
                                $receivingId = request()->query('receiving_id');
                                return $receivingId ? \App\Models\CattleReceiving::with('supplier')->find($receivingId)?->supplier?->name : null;
                            }),
                        Forms\Components\DatePicker::make('weighing_date')
                            ->label(__('Weighing Date'))
                            ->required()
                            ->default(now()),
                        Forms\Components\Textarea::make('note')
                            ->label(__('General Note'))
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make(__('Cattle List (Actual Weight)'))
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->default(function () {
                                $receivingId = request()->query('receiving_id');
                                if ($receivingId) {
                                    $receiving = \App\Models\CattleReceiving::with('items')->find($receivingId);
                                    if ($receiving) {
                                        return $receiving->items->map(function ($item) {
                                            return [
                                                'cattle_receiving_item_id' => $item->id,
                                                'eartag' => $item->eartag,
                                                'initial_weight' => $item->initial_weight,
                                                'actual_weight' => 0,
                                                'notes' => null,
                                                'cattle_class_id' => $item->cattle_class_id,
                                            ];
                                        })->toArray();
                                    }
                                }
                                return [];
                            })
                            ->schema([
                                Forms\Components\Hidden::make('cattle_receiving_item_id'),
                                Forms\Components\Hidden::make('cattle_class_id')->dehydrated(false),
                                Forms\Components\TextInput::make('eartag')
                                    ->label(__('Eartag'))
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('initial_weight')
                                    ->label(__('Initial Weight'))
                                    ->numeric()
                                    ->suffix('Kg')
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('actual_weight')
                                    ->label(__('Actual Weight'))
                                    /*
                                     * Batasnya sengaja berupa ATURAN, bukan
                                     * komponen angka bawaan Filament -- yang
                                     * terakhir menghasilkan input bertipe
                                     * number beserta tombol panahnya, gampang
                                     * tertekan tanpa sengaja -- berat sapi
                                     * berubah satu kilo tanpa ada yang
                                     * menyadarinya. `inputmode` tetap
                                     * memunculkan papan ketik angka di ponsel.
                                     *
                                     * Batas bawah 1, BUKAN 0. Baris ini terisi
                                     * otomatis dengan 0 saat draft dibuka, dan
                                     * perhitungan susut menghitung selisih tiap
                                     * kali berat aktual lebih kecil dari berat
                                     * terima. Satu ekor yang terlewat -- masih
                                     * bernilai 0 -- tercatat sebagai kerugian
                                     * sebesar SELURUH bobot sapi itu dikali
                                     * harga, tanpa error dan tanpa gejala.
                                     */
                                    ->rules(['numeric', 'min:1', 'max:800'])
                                    ->validationMessages([
                                        'min' => __('Actual weight must be filled in; a cattle left at zero is recorded as a total loss.'),
                                        'max' => __('Weight is above the :max kg limit. Please check the number again.', ['max' => 800]),
                                    ])
                                    ->suffix('Kg')
                                    ->required()
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->extraInputAttributes([
                                        'inputmode' => 'decimal',
                                        'x-on:focus' => '$el.select()',
                                        'x-on:click' => '$el.select()',
                                        'class' => 'text-center enter-to-next-actual-weight',
                                        'onkeydown' => "
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                let inputs = Array.from(document.querySelectorAll('.enter-to-next-actual-weight'));
                                                let index = inputs.indexOf(this);
                                                if (index > -1 && index + 1 < inputs.length) {
                                                    inputs[index + 1].focus();
                                                }
                                            }
                                        "
                                    ]),
                                Forms\Components\TextInput::make('notes')
                                    ->label(__('Notes'))
                                    ->extraInputAttributes([
                                        'class' => 'enter-to-next-notes',
                                        'onkeydown' => "
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                let inputs = Array.from(document.querySelectorAll('.enter-to-next-notes'));
                                                let index = inputs.indexOf(this);
                                                if (index > -1 && index + 1 < inputs.length) {
                                                    inputs[index + 1].focus();
                                                }
                                            }
                                        "
                                    ])
                                    ->nullable(),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull()
                            ->itemLabel(fn (array $state): ?string => $state['eartag'] ?? null),
                    ]),

                Forms\Components\Section::make(__('Calculation Results'))
                    ->schema([
                        Forms\Components\Placeholder::make('total_initial')
                            ->label(__('Total Initial Weight'))
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['initial_weight'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_actual')
                            ->label(__('Total Actual Weight'))
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['actual_weight'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_variance')
                            ->label(__('Total Shrinkage'))
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $totalInitial = 0;
                                $totalActual = 0;
                                foreach ($items as $item) {
                                    $totalInitial += floatval($item['initial_weight'] ?? 0);
                                    $totalActual += floatval($item['actual_weight'] ?? 0);
                                }
                                $variance = $totalActual - $totalInitial;
                                $color = $variance < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400';
                                return new \Illuminate\Support\HtmlString("<span class='font-bold {$color}'>" . number_format($variance, 2) . " Kg</span>");
                            }),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('weighing_number')
                    ->label(__('Weighing No'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('receiving.receiving_number')
                    ->label(__('Receive No'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('receiving.purchaseCattle.document_number')
                    ->label(__('PO No'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('receiving.supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('weighing_date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Weigher'))
                    ->badge()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label(__('Heads'))
                    ->formatStateUsing(fn ($state) => $state . ' Heads')
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
            ])
            ->recordUrl(
                fn (CattleWeighing $record): string => $record->trashed()
                    ? Pages\ViewCattleWeighing::getUrl(['record' => $record])
                    : Pages\EditCattleWeighing::getUrl(['record' => $record])
            )
            ->recordClasses(fn (CattleWeighing $record) => $record->trashed() ? 'border-s-2 border-danger-600 dark:border-danger-400 bg-danger-50 dark:bg-danger-900/50' : null)
            // Ekspor Excel dan PDF wajib untuk modul transaksional
            // (project.md). Halaman ini sebelumnya tidak punya sama sekali.
            //
            // Excel sengaja TIDAK memakai Filament Exporter -- ia memicu queue
            // yang lambat, dan di lingkungan ini tidak ada worker sama sekali.
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()
                                ->with(['items', 'receiving.supplier'])->get();

                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'Weighing No', 'Date', 'Receiving No', 'Supplier', 'Heads',
                                    'Initial Weight (Kg)', 'Actual Weight (Kg)', 'Shrinkage (Kg)',
                                ]));

                                foreach ($records as $record) {
                                    $initial = (float) $record->items->sum('initial_weight');
                                    $actual = (float) $record->items->sum('actual_weight');

                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->weighing_number ?? '',
                                        optional($record->weighing_date)->format('Y-m-d') ?? '',
                                        $record->receiving?->receiving_number ?? '',
                                        $record->receiving?->supplier?->name ?? '',
                                        $record->items->count(),
                                        $initial,
                                        $actual,
                                        $initial - $actual,
                                    ]));
                                }

                                $writer->close();
                            }, 'cattle-weighings.xlsx');
                        }),

                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()
                                ->with(['items', 'receiving.supplier'])->get();

                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.cattle-weighings-pdf', [
                                'records' => $records,
                                'title' => __('Cattle Weighings'),
                            ]);

                            return response()->streamDownload(fn () => print($pdf->output()), 'cattle-weighings.pdf');
                        }),
                ])
                    ->label(__('Export Data'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->button()
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_cattle_weighings')),
                Tables\Filters\SelectFilter::make('supplier')
                    ->relationship('receiving.supplier', 'name')
                    ->label(__('Supplier')),
                Tables\Filters\Filter::make('weighing_date')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('weighing_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('weighing_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                // Actions moved to Edit/View page header actions per project guidelines
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListCattleWeighings::route('/'),
            'draft' => Pages\DraftCattleWeighing::route('/draft'),
            'create' => Pages\CreateCattleWeighing::route('/create'),
            'view' => Pages\ViewCattleWeighing::route('/{record}'),
            'edit' => Pages\EditCattleWeighing::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_cattle_weighings',
        );
    }
}
