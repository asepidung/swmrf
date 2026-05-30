<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CattleReceivingResource\Pages;
use App\Models\CattleReceiving;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CattleReceivingResource extends Resource
{
    protected static ?string $model = CattleReceiving::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'CATTLE';
    protected static ?string $navigationLabel = 'Receive';
    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Receiving Header')
                    ->schema([
                        Forms\Components\Hidden::make('cattle_purchase_order_id')->required(),
                        Forms\Components\Hidden::make('supplier_id')->required(),

                        Forms\Components\TextInput::make('po_number_display')
                            ->label('PO Number')
                            ->disabled()->dehydrated(false),

                        Forms\Components\TextInput::make('supplier_name_display')
                            ->label('Supplier')
                            ->disabled()->dehydrated(false),

                        Forms\Components\DatePicker::make('receive_date')
                            ->label('Receive Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('doc_no')
                            ->label('Document Number')
                            ->placeholder('E.g. SV/2026/001'),

                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Toggle::make('sv_ok')
                                    ->label('SV OK')
                                    ->inline(false),
                                Forms\Components\Toggle::make('skkh_ok')
                                    ->label('SKKH OK')
                                    ->inline(false),
                            ])->columns(2),

                        Forms\Components\Textarea::make('note')
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Cattle Details (Per Head)')
                    ->schema([
                        Forms\Components\Repeater::make('receiving_items')
                            ->schema([
                                Forms\Components\Select::make('cattle_category_id')
                                    ->hiddenLabel()
                                    ->placeholder('Class')
                                    ->options(\App\Models\CattleCategory::pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\TextInput::make('eartag')
                                    ->hiddenLabel()
                                    ->placeholder('Eartag')
                                    ->required()
                                    ->live(onBlur: true)
                                    // PERBAIKAN: Fungsi ->unique() bawaan dihapus, diganti custom rules di bawah
                                    ->rules([
                                        // Inject ?Model $record buat deteksi ini lagi Create atau Edit
                                        fn(Forms\Get $get, ?\Illuminate\Database\Eloquent\Model $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                            $eartag = strtoupper(trim((string)$value));

                                            // 1. CEK DUPLIKAT DI DALAM FORM SAAT NGETIK
                                            $items = $get('../../receiving_items') ?? [];
                                            $allEartags = collect($items)
                                                ->pluck('eartag')
                                                ->map(fn($v) => strtoupper(trim((string)$v)))
                                                ->filter()
                                                ->toArray();

                                            if (collect($allEartags)->countBy()[$eartag] > 1) {
                                                $fail('Eartag duplikat di form ini!');
                                                return; // Stop eksekusi
                                            }

                                            // 2. CEK DUPLIKAT DI DATABASE
                                            $query = \App\Models\CattleReceivingItem::where('eartag', $eartag);

                                            // Kalau lagi EDIT, kecualikan eartag yang emang udah jadi milik form ini
                                            if ($record) {
                                                $query->where('cattle_receiving_id', '!=', $record->id);
                                            }

                                            if ($query->exists()) {
                                                $fail('Eartag sudah terdaftar di database!');
                                            }
                                        },
                                    ])
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                                Forms\Components\TextInput::make('initial_weight')
                                    ->hiddenLabel()
                                    ->placeholder('Weight (Max 800)')
                                    ->required()
                                    ->numeric()
                                    ->maxValue(800)
                                    ->live(onBlur: true)
                                    ->suffix('Kg'),

                                Forms\Components\TextInput::make('notes')
                                    ->hiddenLabel()
                                    ->placeholder('Notes'),
                            ])
                            ->columns(4)
                            ->minItems(1)
                            ->addActionLabel('Add Manual Row')
                            ->reorderable(false)
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receiving_number')
                    ->label('GRC Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('purchaseOrder.po_number')
                    ->label('PO Number'),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier'),

                Tables\Columns\TextColumn::make('receive_date')
                    ->label('Date')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Heads')
                    ->counts('items')
                    ->suffix(' Heads'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Received By')
                    ->badge()
                    ->color('gray'),
            ])
            ->recordUrl(
                fn(CattleReceiving $record): string => Pages\ViewCattleReceiving::getUrl([$record->id]),
            )
            ->actions([])
            ->defaultSort('id', 'desc');
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
}
