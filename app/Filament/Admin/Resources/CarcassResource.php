<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CarcassResource\Pages;
use App\Filament\Admin\Resources\CarcassResource\RelationManagers;
use App\Models\Carcass;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CarcassResource extends Resource
{
    protected static ?string $model = Carcass::class;

    protected static ?string $navigationIcon = 'heroicon-o-scissors';
    public static function getNavigationGroup(): ?string
    {
        return __('CATTLE');
    }
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Carcass Information'))->schema([
                    Forms\Components\Hidden::make('cattle_weighing_id')
                        ->default(fn() => request()->query('weighing_id')),
                    Forms\Components\TextInput::make('weighing_number')
                        ->label(__('Weighing Number'))
                        ->disabled()
                        ->dehydrated(false)
                        ->default(function() {
                            $weighingId = request()->query('weighing_id');
                            return $weighingId ? \App\Models\CattleWeighing::find($weighingId)?->weighing_number : null;
                        })
                        ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record) {
                            if ($record && ! $state) {
                                $component->state($record->weighing->weighing_number);
                            }
                        }),
                    Forms\Components\TextInput::make('po_number')
                        ->label(__('PO Number'))
                        ->disabled()
                        ->dehydrated(false)
                        ->default(function() {
                            $weighingId = request()->query('weighing_id');
                            return $weighingId ? \App\Models\CattleWeighing::with('receiving.purchaseCattle')->find($weighingId)?->receiving?->purchaseCattle?->document_number : null;
                        })
                        ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record) {
                            if ($record && ! $state) {
                                $component->state(optional(optional($record->weighing)->receiving)->purchaseCattle?->document_number);
                            }
                        }),
                    Forms\Components\TextInput::make('supplier_name')
                        ->label(__('Supplier'))
                        ->disabled()
                        ->dehydrated(false)
                        ->default(function() {
                            $weighingId = request()->query('weighing_id');
                            return $weighingId ? \App\Models\CattleWeighing::with('receiving.supplier')->find($weighingId)?->receiving?->supplier?->name : null;
                        })
                        ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record) {
                            if ($record && ! $state) {
                                $component->state(optional(optional($record->weighing)->receiving)->supplier?->name);
                            }
                        }),
                    Forms\Components\DatePicker::make('kill_date')
                        ->required()
                        ->default(now()),
                    Forms\Components\Textarea::make('note')
                        ->columnSpanFull(),
                ])->columns(2),

                Forms\Components\Section::make(__('Carcass Details'))->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->default(function () {
                            $weighingId = request()->query('weighing_id');
                            if ($weighingId) {
                                $weighing = \App\Models\CattleWeighing::with(['items' => function ($q) {
                                    $q->whereDoesntHave('carcassItems');
                                }])->find($weighingId);
                                
                                if ($weighing) {
                                    return $weighing->items->map(function ($item) {
                                        return [
                                            'cattle_weighing_item_id' => $item->id,
                                            'eartag' => $item->eartag,
                                            'carcass_1' => 0,
                                            'carcass_2' => 0,
                                            'hides' => 0,
                                            'tail' => 0,
                                        ];
                                    })->toArray();
                                }
                            }
                            return [];
                        })
                        ->schema([
                            Forms\Components\Hidden::make('cattle_weighing_item_id'),
                            Forms\Components\TextInput::make('eartag')
                                ->disabled()
                                ->dehydrated(false)
                                ->label(__('Eartag'))
                                ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record) {
                                    if ($record && ! $state) {
                                        $component->state($record->weighingItem?->eartag);
                                    }
                                }),
                            Forms\Components\TextInput::make('carcass_1')
                                ->default(0)
                                // Tanpa komponen angka bawaan: ia menghasilkan input
                                // bertipe number beserta tombol panahnya, yang gampang
                                // tertekan tanpa sengaja sehingga bobot berubah tanpa
                                // ada yang menyadarinya. Batasnya jadi aturan biasa.
                                ->extraInputAttributes(['inputmode' => 'decimal'])
                                ->rules(['numeric', 'min:0', 'max:350'])
                                ->live(onBlur: true)
                                ->extraInputAttributes([
                                    'x-on:focus' => '$el.select()',
                                    'x-on:click' => '$el.select()',
                                    'class' => 'text-center enter-to-next-carcass-1',
                                    'onkeydown' => "
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            let inputs = Array.from(document.querySelectorAll('.enter-to-next-carcass-1'));
                                            let index = inputs.indexOf(this);
                                            if (index > -1 && index + 1 < inputs.length) {
                                                inputs[index + 1].focus();
                                            }
                                        }
                                    "
                                ])
                                ->rules([
                                    fn (\Filament\Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $c1 = (float) $value;
                                        $c2 = (float) $get('carcass_2');
                                        $h = (float) $get('hides');
                                        if ($c1 > 0 || $c2 > 0 || $h > 0) {
                                            if ($c1 <= 0 || $c2 <= 0 || $h <= 0) {
                                                $fail(__('Carcass 1, Carcass 2, and Hides must all be filled in.'));
                                            }
                                        }
                                    }
                                ]),
                            Forms\Components\TextInput::make('carcass_2')
                                ->default(0)
                                // Tanpa komponen angka bawaan: ia menghasilkan input
                                // bertipe number beserta tombol panahnya, yang gampang
                                // tertekan tanpa sengaja sehingga bobot berubah tanpa
                                // ada yang menyadarinya. Batasnya jadi aturan biasa.
                                ->extraInputAttributes(['inputmode' => 'decimal'])
                                ->rules(['numeric', 'min:0', 'max:350'])
                                ->live(onBlur: true)
                                ->extraInputAttributes([
                                    'x-on:focus' => '$el.select()',
                                    'x-on:click' => '$el.select()',
                                    'class' => 'text-center enter-to-next-carcass-2',
                                    'onkeydown' => "
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            let inputs = Array.from(document.querySelectorAll('.enter-to-next-carcass-2'));
                                            let index = inputs.indexOf(this);
                                            if (index > -1 && index + 1 < inputs.length) {
                                                inputs[index + 1].focus();
                                            }
                                        }
                                    "
                                ])
                                ->rules([
                                    fn (\Filament\Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $c1 = (float) $get('carcass_1');
                                        $c2 = (float) $value;
                                        $h = (float) $get('hides');
                                        if ($c1 > 0 || $c2 > 0 || $h > 0) {
                                            if ($c1 <= 0 || $c2 <= 0 || $h <= 0) {
                                                $fail(__('Carcass 1, Carcass 2, and Hides must all be filled in.'));
                                            }
                                            if (abs($c1 - $c2) > 100) {
                                                $fail(__('The two carcass halves differ by more than :max kg; one of them is likely mistyped.', ['max' => 100]));
                                            }
                                        }
                                    }
                                ]),
                            Forms\Components\TextInput::make('hides')
                                ->default(0)
                                // Tanpa komponen angka bawaan: ia menghasilkan input
                                // bertipe number beserta tombol panahnya, yang gampang
                                // tertekan tanpa sengaja sehingga bobot berubah tanpa
                                // ada yang menyadarinya. Batasnya jadi aturan biasa.
                                ->extraInputAttributes(['inputmode' => 'decimal'])
                                ->rules(['numeric', 'min:0', 'max:100'])
                                ->live(onBlur: true)
                                ->extraInputAttributes([
                                    'x-on:focus' => '$el.select()',
                                    'x-on:click' => '$el.select()',
                                    'class' => 'text-center enter-to-next-hides',
                                    'onkeydown' => "
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            let inputs = Array.from(document.querySelectorAll('.enter-to-next-hides'));
                                            let index = inputs.indexOf(this);
                                            if (index > -1 && index + 1 < inputs.length) {
                                                inputs[index + 1].focus();
                                            }
                                        }
                                    "
                                ])
                                ->rules([
                                    fn (\Filament\Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $c1 = (float) $get('carcass_1');
                                        $c2 = (float) $get('carcass_2');
                                        $h = (float) $value;
                                        if ($c1 > 0 || $c2 > 0 || $h > 0) {
                                            if ($c1 <= 0 || $c2 <= 0 || $h <= 0) {
                                                $fail(__('Carcass 1, Carcass 2, and Hides must all be filled in.'));
                                            }
                                        }
                                    }
                                ]),
                            Forms\Components\TextInput::make('tail')
                                ->default(0)
                                ->extraInputAttributes(['inputmode' => 'decimal'])
                                ->rules(['numeric', 'min:0', 'max:100'])
                                /*
                                 * Karkas 1, Karkas 2, Hides, dan Tail berasal
                                 * dari SATU ekor sapi, jadi jumlahnya mustahil
                                 * melebihi bobot sapi itu. Tanpa pemeriksaan
                                 * ini, salah ketik satu digit menghasilkan
                                 * karkas yang lebih berat daripada sapinya --
                                 * tanpa error, dan baru terasa saat neraca
                                 * hasil potong tidak masuk akal.
                                 *
                                 * Diperiksa di field TERAKHIR dari satu baris
                                 * supaya keempat nilainya sudah terisi.
                                 */
                                ->rules([
                                    fn (\Filament\Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $total = (float) $get('carcass_1')
                                            + (float) $get('carcass_2')
                                            + (float) $get('hides')
                                            + (float) $value;

                                        if ($total <= 0) {
                                            return;
                                        }

                                        $weighingItem = \App\Models\CattleWeighingItem::find($get('cattle_weighing_item_id'));
                                        $reference = (float) ($weighingItem->reference_weight ?? 0);

                                        // Bobot acuannya tidak diketahui sama
                                        // sekali; menolak di sini hanya akan
                                        // menghalangi pekerjaan tanpa dasar.
                                        if ($reference <= 0) {
                                            return;
                                        }

                                        if ($total > $reference) {
                                            $fail(__('Total :total kg is heavier than the cattle itself (:reference kg).', [
                                                'total' => number_format($total, 0, ',', '.'),
                                                'reference' => number_format($reference, 0, ',', '.'),
                                            ]));
                                        }
                                    },
                                ])
                                ->live(onBlur: true)
                                ->extraInputAttributes([
                                    'x-on:focus' => '$el.select()',
                                    'x-on:click' => '$el.select()',
                                    'class' => 'text-center enter-to-next-tail',
                                    'onkeydown' => "
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            let inputs = Array.from(document.querySelectorAll('.enter-to-next-tail'));
                                            let index = inputs.indexOf(this);
                                            if (index > -1 && index + 1 < inputs.length) {
                                                inputs[index + 1].focus();
                                            }
                                        }
                                    "
                                ]),
                            Forms\Components\TextInput::make('notes')
                                ->label(__('Note'))
                                ->columnSpan(2)
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
                                ]),
                        ])
                        ->columns(7)
                        ->addable(false)
                        ->deletable(true)
                        ->label('')
                ]),

                Forms\Components\Section::make(__('Calculation Results'))
                    ->schema([
                        Forms\Components\Placeholder::make('total_carcass_1')
                            ->label(__('Total Carcass 1'))
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['carcass_1'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_carcass_2')
                            ->label(__('Total Carcass 2'))
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['carcass_2'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_hides')
                            ->label(__('Total Hides'))
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['hides'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_tail')
                            ->label(__('Total Tail'))
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['tail'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_offal')
                            ->label(__('Total Offal'))
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['carcass_1'] ?? 0) + floatval($item['carcass_2'] ?? 0) + floatval($item['tail'] ?? 0);
                                }
                                return new \Illuminate\Support\HtmlString("<span class='font-bold text-primary-600'>" . number_format($total, 2) . " Kg</span>");
                            }),
                    ])->columns(5),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('carcass_number')
                    ->label(__('Carcass No'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weighing.weighing_number')
                    ->label(__('Weighing No'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weighing.receiving.supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kill_date')
                    ->label(__('Kill Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label(__('Heads'))
                    ->formatStateUsing(fn ($state) => $state . ' Heads')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Prepared By'))
                    ->sortable(),
            ])
            ->recordUrl(
                fn (Carcass $record): string => Pages\ViewCarcass::getUrl(['record' => $record])
            )
            // Ekspor Excel dan PDF wajib untuk modul transaksional (project.md);
            // halaman ini sebelumnya tidak punya sama sekali. Excel sengaja
            // tidak memakai Filament Exporter -- ia memicu queue yang lambat,
            // dan di lingkungan ini tidak ada worker sama sekali.
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->with('items')->get();

                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'Carcass No', 'Kill Date', 'Heads',
                                    'Carcass 1 (Kg)', 'Carcass 2 (Kg)', 'Hides (Kg)', 'Tail (Kg)', 'Total (Kg)', 'Note',
                                ]));

                                foreach ($records as $record) {
                                    $c1 = (float) $record->items->sum('carcass_1');
                                    $c2 = (float) $record->items->sum('carcass_2');
                                    $hides = (float) $record->items->sum('hides');
                                    $tail = (float) $record->items->sum('tail');

                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->carcass_number ?? '',
                                        optional($record->kill_date)->format('Y-m-d') ?? '',
                                        $record->items->count(),
                                        $c1, $c2, $hides, $tail, $c1 + $c2 + $hides + $tail,
                                        $record->note ?? '',
                                    ]));
                                }

                                $writer->close();
                            }, 'carcasses.xlsx');
                        }),

                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->with('items')->get();

                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.carcasses-pdf', [
                                'records' => $records,
                                'title' => __('Carcasses'),
                            ]);

                            return response()->streamDownload(fn () => print($pdf->output()), 'carcasses.pdf');
                        }),
                ])
                    ->label(__('Export Data'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->button()
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()?->hasPermission('view_deleted_carcasses') ?? false),
            ])
            ->actions([
                \App\Filament\Admin\Resources\QcReportResource\Actions\LihatLaporanQc::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListCarcasses::route('/'),
            'draft' => Pages\DraftCarcass::route('/draft'),
            'create' => Pages\CreateCarcass::route('/create'),
            'view' => Pages\ViewCarcass::route('/{record}'),
            'edit' => Pages\EditCarcass::route('/{record}/edit'),
            'print' => Pages\PrintCarcass::route('/{record}/print'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_carcasses',
        );
    }
}
