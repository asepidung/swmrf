<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CattleWeighingResource\Pages;
use App\Filament\Admin\Resources\CattleWeighingResource\RelationManagers;
use App\Models\CattleWeighing;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CattleWeighingResource extends Resource
{
    protected static ?string $model = CattleWeighing::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    public static function getNavigationGroup(): ?string
    {
        return __('Cattle');
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

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Weighing Header')
                    ->schema([
                        Forms\Components\Hidden::make('cattle_receiving_id')
                            ->default(fn() => request()->query('receiving_id'))
                            ->unique(ignoreRecord: true, modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule) {
                                return $rule->whereNull('deleted_at');
                            }),
                        Forms\Components\TextInput::make('receiving_number')
                            ->label('Receive Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(function() {
                                $receivingId = request()->query('receiving_id');
                                return $receivingId ? \App\Models\CattleReceiving::find($receivingId)?->receiving_number : null;
                            }),
                        Forms\Components\TextInput::make('po_number')
                            ->label('PO Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(function() {
                                $receivingId = request()->query('receiving_id');
                                return $receivingId ? \App\Models\CattleReceiving::with('purchaseCattle')->find($receivingId)?->purchaseCattle?->document_number : null;
                            }),
                        Forms\Components\TextInput::make('supplier_name')
                            ->label('Supplier')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(function() {
                                $receivingId = request()->query('receiving_id');
                                return $receivingId ? \App\Models\CattleReceiving::with('supplier')->find($receivingId)?->supplier?->name : null;
                            }),
                        Forms\Components\DatePicker::make('weighing_date')
                            ->label('Weighing Date')
                            ->required()
                            ->default(now()),
                        Forms\Components\Textarea::make('note')
                            ->label('General Note')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Cattle List (Actual Weight)')
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
                                    ->label('Eartag')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('initial_weight')
                                    ->label('Initial Weight')
                                    ->numeric()
                                    ->suffix('Kg')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('actual_weight')
                                    ->label('Actual Weight')
                                    ->numeric()
                                    ->suffix('Kg')
                                    ->required()
                                    ->default(0)
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->extraInputAttributes(['x-on:focus' => '$el.select()', 'x-on:click' => '$el.select()']),
                                Forms\Components\TextInput::make('notes')
                                    ->label('Notes')
                                    ->nullable(),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull()
                            ->itemLabel(fn (array $state): ?string => $state['eartag'] ?? null),
                    ]),

                Forms\Components\Section::make('Calculation Results')
                    ->schema([
                        Forms\Components\Placeholder::make('total_initial')
                            ->label('Total Initial Weight')
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['initial_weight'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_actual')
                            ->label('Total Actual Weight')
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['actual_weight'] ?? 0);
                                }
                                return number_format($total, 2) . ' Kg';
                            }),
                        Forms\Components\Placeholder::make('total_variance')
                            ->label('Total Variance (Loss)')
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
                    ->label('Weighing No')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receiving.receiving_number')
                    ->label('Receive No')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receiving.purchaseCattle.document_number')
                    ->label('PO No')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receiving.supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weighing_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Weigher')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Heads')
                    ->formatStateUsing(fn ($state) => $state . ' Heads'),
            ])
            ->recordUrl(fn (CattleWeighing $record): string => Pages\EditCattleWeighing::getUrl(['record' => $record]))
            ->filters([
                Tables\Filters\TrashedFilter::make(),
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
            'index' => Pages\ListCattleWeighings::route('/'),
            'draft' => Pages\DraftCattleWeighing::route('/draft'),
            'create' => Pages\CreateCattleWeighing::route('/create'),
            'view' => Pages\ViewCattleWeighing::route('/{record}'),
            'edit' => Pages\EditCattleWeighing::route('/{record}/edit'),
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
