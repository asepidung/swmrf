<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CattleReceivingResource\Pages;
use App\Models\CattleReceiving;
use App\Models\CattleClass;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class CattleReceivingResource extends Resource
{
    protected static ?string $model = CattleReceiving::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationGroup(): ?string
    {
        return __('Cattle');
    }

    public static function getNavigationLabel(): string
    {
        return __('Receive');
    }

    public static function getModelLabel(): string
    {
        return __('Cattle Receiving');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Cattle Receivings');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Receiving Header'))
                    ->schema([
                        Forms\Components\Hidden::make('purchase_cattle_id')->required(),
                        Forms\Components\Hidden::make('supplier_id')->required(),

                        Forms\Components\TextInput::make('po_number_display')
                            ->label(__('PO Number'))
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record, $state) => $state ?: $record?->purchaseCattle?->document_number),

                        Forms\Components\TextInput::make('supplier_name_display')
                            ->label(__('Supplier'))
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record, $state) => $state ?: $record?->supplier?->name),

                        Forms\Components\DatePicker::make('receive_date')
                            ->label(__('Receive Date'))
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('doc_no')
                            ->label(__('Document Number'))
                            ->placeholder('E.g. SV/2026/001'),

                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Toggle::make('sv_ok')
                                    ->label(__('SV OK'))
                                    ->inline(false),
                                Forms\Components\Toggle::make('skkh_ok')
                                    ->label(__('SKKH OK'))
                                    ->inline(false),
                            ])->columns(2),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make(__('Cattle Details (Per Head)'))
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('cattle_class_id')
                                    ->relationship('cattleClass', 'name')
                                    ->placeholder(__('Class'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->label('')
                                    ->hiddenLabel(),

                                Forms\Components\TextInput::make('eartag')
                                    ->placeholder(__('Eartag'))
                                    ->required()
                                    ->live(debounce: 500)
                                    ->rules([
                                        fn (Forms\Get $get, ?Model $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                            $eartag = strtoupper(trim((string)$value));

                                            // 1. Live duplicate check in form state
                                            $items = $get('../../items') ?? [];
                                            $allEartags = collect($items)
                                                ->pluck('eartag')
                                                ->map(fn($v) => strtoupper(trim((string)$v)))
                                                ->filter()
                                                ->toArray();

                                            if (collect($allEartags)->countBy()[$eartag] > 1) {
                                                $fail(__('Eartag duplikat di form ini!'));
                                                return;
                                            }

                                            // 2. Database duplicate check
                                            $query = \App\Models\CattleReceivingItem::where('eartag', $eartag);

                                            if ($record) {
                                                $query->where('cattle_receiving_id', '!=', $record->id);
                                            }

                                            if ($query->exists()) {
                                                $fail(__('Eartag sudah terdaftar di database!'));
                                            }
                                        },
                                    ])
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->label('')
                                    ->hiddenLabel(),

                                Forms\Components\TextInput::make('initial_weight')
                                    ->placeholder(__('Weight (Max 800)'))
                                    ->required()
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(800)
                                    ->live(debounce: 500)
                                    ->suffix('Kg')
                                    ->label('')
                                    ->hiddenLabel(),

                                Forms\Components\TextInput::make('notes')
                                    ->placeholder(__('Notes'))
                                    ->label('')
                                    ->hiddenLabel(),
                            ])
                            ->columns(4)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel(__('Add Manual Row'))
                            ->reorderable(false)
                            ->label('')
                            ->hiddenLabel(),

                        Forms\Components\Placeholder::make('total_weight')
                            ->label(__('Total Weight'))
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                
                                // Group by cattle class name
                                $classIds = collect($items)->pluck('cattle_class_id')->filter()->unique();
                                $classes = CattleClass::whereIn('id', $classIds)->pluck('name', 'id');
                                
                                $groups = collect($items)->groupBy('cattle_class_id');
                                $lines = [];
                                $overallTotal = 0;
                                
                                foreach ($groups as $classId => $groupItems) {
                                    $className = $classes[$classId] ?? __('Unknown');
                                    $sum = collect($groupItems)->sum('initial_weight');
                                    $overallTotal += $sum;
                                    
                                    if ($className) {
                                        $lines[] = "{$className}: " . number_format($sum) . ' Kg';
                                    }
                                }
                                
                                $lines[] = "<strong>Total: " . number_format($overallTotal) . ' Kg</strong>';
                                
                                return new HtmlString(implode('<br>', $lines));
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receiving_number')
                    ->label(__('Receive Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('purchaseCattle.document_number')
                    ->label(__('PO Number'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('receive_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('Heads'))
                    ->counts('items')
                    ->suffix(' Heads')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Received By'))
                    ->badge()
                    ->color('gray'),
            ])
            ->recordUrl(
                fn (CattleReceiving $record): string => Pages\EditCattleReceiving::getUrl([$record->id]),
            )
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label(__('Supplier')),
                Tables\Filters\Filter::make('receive_date')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('receive_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('receive_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
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
            'index' => Pages\ListCattleReceivings::route('/'),
            'create' => Pages\CreateCattleReceiving::route('/create'),
            'draft' => Pages\DraftCattleReceiving::route('/draft'),
            'view' => Pages\ViewCattleReceiving::route('/{record}'),
            'edit' => Pages\EditCattleReceiving::route('/{record}/edit'),
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
