<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CarcassResource\Pages;
use App\Filament\Admin\Resources\CarcassResource\RelationManagers;
use App\Models\Carcass;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CarcassResource extends Resource
{
    protected static ?string $model = Carcass::class;

    protected static ?string $navigationIcon = 'heroicon-o-scissors';
    public static function getNavigationGroup(): ?string
    {
        return 'CATTLE';
    }
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Carcass Information')->schema([
                    Forms\Components\Hidden::make('cattle_weighing_id')
                        ->default(fn() => request()->query('weighing_id')),
                    Forms\Components\TextInput::make('weighing_number')
                        ->label('Weighing Number')
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
                        ->label('PO Number')
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
                        ->label('Supplier')
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

                Forms\Components\Section::make('Carcass Details')->schema([
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
                                ->label('Eartag')
                                ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record) {
                                    if ($record && ! $state) {
                                        $component->state($record->weighingItem?->eartag);
                                    }
                                }),
                            Forms\Components\TextInput::make('carcass_1')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(350)
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
                                                $fail('Carcass 1, 2, dan Hides wajib diisi (>0).');
                                            }
                                        }
                                    }
                                ]),
                            Forms\Components\TextInput::make('carcass_2')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(350)
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
                                                $fail('Carcass 1, 2, dan Hides wajib diisi (>0).');
                                            }
                                            if (abs($c1 - $c2) > 100) {
                                                $fail('Selisih maksimal 100 KG.');
                                            }
                                        }
                                    }
                                ]),
                            Forms\Components\TextInput::make('hides')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(100)
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
                                                $fail('Carcass 1, 2, dan Hides wajib diisi (>0).');
                                            }
                                        }
                                    }
                                ]),
                            Forms\Components\TextInput::make('tail')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(0)
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
                                ->label('Note')
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

                Forms\Components\Section::make('Calculation Results')
                    ->schema([
                        Forms\Components\Placeholder::make('total_carcass_1')
                            ->label('Total Carcass 1')
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['carcass_1'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_carcass_2')
                            ->label('Total Carcass 2')
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['carcass_2'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_hides')
                            ->label('Total Hides')
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['hides'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_tail')
                            ->label('Total Tail')
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['tail'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_offal')
                            ->label('Total Offal')
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
                    ->label('Carcass No')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weighing.weighing_number')
                    ->label('Weighing No')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weighing.receiving.supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kill_date')
                    ->label('Kill Date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Heads')
                    ->formatStateUsing(fn ($state) => $state . ' Heads')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Prepared By')
                    ->sortable(),
            ])
            ->recordUrl(
                fn (Carcass $record): string => Pages\ViewCarcass::getUrl(['record' => $record])
            )
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([])
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
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
