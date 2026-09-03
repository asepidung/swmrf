<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CattleReceivingResource\Pages;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class CattleReceivingResource extends Resource
{
    protected static ?string $model = CattleReceiving::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('CATTLE');
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
                            ->placeholder(__('E.g. SV/2026/001')),

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
                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Placeholder::make('col_cattle_class')->label(__('Class')),
                                Forms\Components\Placeholder::make('col_eartag')->label(__('Eartag')),
                                Forms\Components\Placeholder::make('col_initial_weight')->label(__('Weight')),
                                Forms\Components\Placeholder::make('col_notes')->label(__('Notes')),
                            ])
                            ->extraAttributes(['class' => 'swm-wide-only']),

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
                                                $fail(__('Duplicate eartag in this form'));
                                                return;
                                            }

                                            // 2. Database duplicate check
                                            $query = \App\Models\CattleReceivingItem::where('eartag', $eartag);

                                            if ($record) {
                                                $query->where('cattle_receiving_id', '!=', $record->id);
                                            }

                                            if ($query->exists()) {
                                                $fail(__('Eartag is already registered'));
                                            }
                                        },
                                    ])
                                    ->extraInputAttributes([
                                        'style' => 'text-transform: uppercase',
                                        'class' => 'enter-to-next-eartag',
                                        'onkeydown' => "
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                let inputs = Array.from(document.querySelectorAll('.enter-to-next-eartag'));
                                                let index = inputs.indexOf(this);
                                                if (index > -1 && index + 1 < inputs.length) {
                                                    inputs[index + 1].focus();
                                                }
                                            }
                                        "
                                    ])
                                    ->label('')
                                    ->hiddenLabel(),

                                Forms\Components\TextInput::make('initial_weight')
                                    ->placeholder(__('Weight (Max 800)'))
                                    ->required()
                                    /*
                                     * BUKAN `->numeric()`: itu menghasilkan
                                     * <input type="number"> beserta tombol
                                     * panahnya, yang gampang tertekan tanpa
                                     * sengaja sehingga berat berubah tanpa ada
                                     * yang menyadarinya. `inputmode` tetap
                                     * memunculkan papan ketik angka di ponsel.
                                     */
                                    /*
                                     * Batas 800 kg dijaga sebagai ATURAN, bukan
                                     * lewat `maxValue()`.
                                     *
                                     * `maxValue()` ikut memasang atribut HTML
                                     * `max` pada <input type="number">, dan
                                     * atribut itu membuka jalan bagi nilai
                                     * terjepit diam-diam ke 800 -- misalnya
                                     * lewat tombol panah. Operator tidak akan
                                     * pernah tahu ia salah ketik, dan yang
                                     * tersimpan adalah berat yang tidak pernah
                                     * ada sapinya.
                                     *
                                     * Sejak hutang dihitung dari berat ini,
                                     * kesalahan itu langsung menjadi tagihan
                                     * yang salah ke supplier, tanpa satu pun
                                     * gejala di layar. Jadi nilai di luar batas
                                     * DITOLAK dan dikembalikan ke operator
                                     * untuk diperbaiki sendiri.
                                     */
                                    ->rules(['integer', 'min:1', 'max:800'])
                                    ->validationMessages([
                                        'max' => __('Weight is above the :max kg limit. Please check the number again.', ['max' => 800]),
                                        'min' => __('Weight must be greater than zero.'),
                                    ])
                                    ->live(debounce: 500)
                                    ->suffix('Kg')
                                    ->extraInputAttributes([
                                        'inputmode' => 'numeric',
                                        'class' => 'text-center enter-to-next-weight',
                                        'onkeydown' => "
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                let inputs = Array.from(document.querySelectorAll('.enter-to-next-weight'));
                                                let index = inputs.indexOf(this);
                                                if (index > -1 && index + 1 < inputs.length) {
                                                    inputs[index + 1].focus();
                                                }
                                            }
                                        "
                                    ])
                                    ->label('')
                                    ->hiddenLabel(),

                                Forms\Components\TextInput::make('notes')
                                    ->placeholder(__('Notes'))
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
                                    ->label('')
                                    ->hiddenLabel(),
                            ])
                            ->columns(4)
                            ->minItems(1)
                            ->defaultItems(0)
                            ->default(function () {
                                $poId = request()->query('po_id');
                                if (!$poId) return [];
                                $po = \App\Models\PurchaseCattle::with('items')->find($poId);
                                if (!$po) return [];
                                
                                $generatedRows = [];
                                foreach ($po->items as $poItem) {
                                    for ($i = 0; $i < $poItem->qty; $i++) {
                                        $generatedRows[(string) \Illuminate\Support\Str::uuid()] = [
                                            'cattle_class_id' => $poItem->cattle_class_id,
                                            'eartag' => null,
                                            'initial_weight' => null,
                                            'notes' => null,
                                        ];
                                    }
                                }
                                return $generatedRows;
                            })
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
                                    $sum = collect($groupItems)->sum(fn ($item) => (int) ($item['initial_weight'] ?? 0));
                                    $overallTotal += $sum;
                                    
                                    if ($className) {
                                        $lines[] = "{$className}: " . number_format($sum) . ' Kg';
                                    }
                                }
                                
                                $lines[] = "<strong>Total: " . number_format($overallTotal) . ' Kg</strong>';
                                
                                return new HtmlString(implode('<br>', $lines));
                            }),
                    ]),
            ])
            ->disabled(fn (?CattleReceiving $record) => $record && $record->weighing()->exists());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receiving_number')
                    ->label(__('Receive Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),

                Tables\Columns\TextColumn::make('purchaseCattle.document_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),

                Tables\Columns\TextColumn::make('receive_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),

                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('Heads'))
                    ->counts('items')
                    ->suffix(' Heads')
                    ->alignCenter()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Received By'))
                    ->badge()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : 'gray'),
            ])
            ->recordUrl(
                fn (CattleReceiving $record): string => $record->trashed() 
                    ? Pages\ViewCattleReceiving::getUrl(['record' => $record]) 
                    : Pages\EditCattleReceiving::getUrl([$record->id]),
            )
            ->recordClasses(fn (CattleReceiving $record) => $record->trashed() ? 'border-s-2 border-danger-600 dark:border-danger-400 bg-danger-50 dark:bg-danger-900/50' : null)
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_cattle_receivings')),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label(__('Supplier')),
                Tables\Filters\Filter::make('receive_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('From')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('Until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['until'] ?? now()->toDateString();

                        return $query
                            ->when(
                                $from,
                                fn (Builder $query, $date): Builder => $query->whereDate('receive_date', '>=', $date),
                            )
                            ->when(
                                $until,
                                fn (Builder $query, $date): Builder => $query->whereDate('receive_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['from'])->format('d M Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->headerActions([])
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
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_cattle_receivings',
        );
    }
}
