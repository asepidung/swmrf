<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CarcassResource\Pages;
use App\Models\Carcass;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class CarcassResource extends Resource
{
    protected static ?string $model = Carcass::class;
    protected static ?string $navigationIcon = 'heroicon-o-scissors'; // Icon potong daging
    protected static ?string $navigationGroup = 'CATTLE';
    protected static ?string $navigationLabel = 'Carcass';
    protected static ?int $navigationSort = 22; // Setelah Cattle Weighing

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pemotongan')
                    ->schema([
                        Forms\Components\Hidden::make('cattle_weighing_id')->required(),

                        Forms\Components\TextInput::make('weigh_no_display')
                            ->label('Nomor Timbangan')
                            ->disabled()
                            ->dehydrated(false), // Gak disave, cuma numpang mejeng

                        Forms\Components\DatePicker::make('kill_date')
                            ->label('Tanggal Potong (Kill Date)')
                            ->default(now())
                            ->required(),

                        Forms\Components\Textarea::make('note')
                            ->label('Catatan Tambahan')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Daftar Sapi Belum Dipotong')
                    ->description('Isi berat karkas pada sapi yang dipotong hari ini. Sapi yang tidak dipotong biarkan kosong, otomatis akan kembali ke Draft.')
                    ->schema([
                        Forms\Components\Repeater::make('carcass_items')
                            ->schema([
                                Forms\Components\Hidden::make('cattle_weighing_item_id'),

                                Forms\Components\TextInput::make('eartag_display')
                                    ->label('Eartag')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('weight_display')
                                    ->label('Live (Kg)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('carcass_1')
                                    ->label('Karkas 1')
                                    ->numeric()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('carcass_2')
                                    ->label('Karkas 2')
                                    ->numeric()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('hides')
                                    ->label('Kulit')
                                    ->numeric()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('tail')
                                    ->label('Buntut')
                                    ->numeric()
                                    ->columnSpan(2),
                            ])
                            ->columns(12)
                            ->addable(false)   // Cegah nambah baris manual
                            ->deletable(false) // Cegah hapus baris manual, cukup kosongin angka
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('carcass_no')
                    ->label('Carcass No')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('weighing.weigh_no')
                    ->label('Weighing No')
                    ->searchable(),
                Tables\Columns\TextColumn::make('weighing.receiving.supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kill_date')
                    ->label('Kill Date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Heads')
                    ->counts('items')
                    ->suffix(' Heads')
                    ->badge(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Prepared By'),
            ])
            ->filters([
                //
            ])
            ->actions([
                // KOSONGKAN INI: Biar tombol Edit & View ilang dari pojok kanan
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            // KUNCI: Bikin satu baris full bisa diklik dan langsung lari ke halaman View
            ->recordUrl(
                fn($record): string => static::getUrl('view', ['record' => $record]),
            )
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCarcasses::route('/'),
            'create' => Pages\CreateCarcass::route('/create'),
            'draft' => Pages\DraftCarcass::route('/draft'), // Halaman Antrean Potong
            'view' => Pages\ViewCarcass::route('/{record}'),
            'edit' => Pages\EditCarcass::route('/{record}/edit'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // SECTION 1: HEADER
                Infolists\Components\Section::make('Informasi Karkas')
                    ->schema([
                        Infolists\Components\TextEntry::make('carcass_no')->label('Carcass No')->weight('bold')->color('primary'),
                        Infolists\Components\TextEntry::make('kill_date')->label('Tanggal Potong')->date('d M Y'),
                        Infolists\Components\TextEntry::make('weighing.weigh_no')->label('Nomor Timbangan'),
                        Infolists\Components\TextEntry::make('weighing.receiving.supplier.name')->label('Supplier'),
                        Infolists\Components\TextEntry::make('creator.name')->label('Prepared By'),
                        Infolists\Components\TextEntry::make('note')->label('Catatan')->columnSpanFull()->placeholder('-'),
                    ])->columns(3),

                // SECTION 2: RINGKASAN TOTAL (Sesuai Permintaan Lu)
                Infolists\Components\Section::make('Ringkasan Total (Summary)')
                    ->schema([
                        Infolists\Components\Grid::make(6) // Bagi jadi 6 kolom biar sejajar
                            ->schema([
                                // TOTAL LIVE
                                Infolists\Components\TextEntry::make('total_live')
                                    ->label('Total Live (Kg)')
                                    ->getStateUsing(fn($record) => $record->items->sum(fn($item) => $item->weighingItem->weight ?? 0))
                                    ->numeric(2, ',', '.')
                                    ->weight('bold'),

                                // TOTAL CARCASS 1
                                Infolists\Components\TextEntry::make('total_c1')
                                    ->label('Carcass 1')
                                    ->getStateUsing(fn($record) => $record->items->sum('carcass_1'))
                                    ->numeric(2, ',', '.'),

                                // TOTAL CARCASS 2
                                Infolists\Components\TextEntry::make('total_c2')
                                    ->label('Carcass 2')
                                    ->getStateUsing(fn($record) => $record->items->sum('carcass_2'))
                                    ->numeric(2, ',', '.'),

                                // TOTAL OFFAL (C1 + C2 + TAIL)
                                Infolists\Components\TextEntry::make('offal')
                                    ->label('Offal')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->items->sum('carcass_1') +
                                            $record->items->sum('carcass_2') +
                                            $record->items->sum('tail')
                                    )
                                    ->numeric(2, ',', '.')
                                    ->color('success')
                                    ->weight('bold'),

                                // TOTAL KULIT (HIDES)
                                Infolists\Components\TextEntry::make('total_hides')
                                    ->label('Hides')
                                    ->getStateUsing(fn($record) => $record->items->sum('hides'))
                                    ->numeric(2, ',', '.'),

                                // TOTAL TAIL
                                Infolists\Components\TextEntry::make('total_tail')
                                    ->label('Tail')
                                    ->getStateUsing(fn($record) => $record->items->sum('tail'))
                                    ->numeric(2, ',', '.'),
                            ]),

                        // TAMBAHAN: CARCASE YIELD (%)
                        Infolists\Components\TextEntry::make('yield')
                            ->label('Carcase Yield (%)')
                            ->getStateUsing(function ($record) {
                                $live = $record->items->sum(fn($item) => $item->weighingItem->weight ?? 0);
                                $carc = $record->items->sum('carcass_1') + $record->items->sum('carcass_2');
                                return $live > 0 ? ($carc / $live) * 100 : 0;
                            })
                            ->numeric(2, ',', '.')
                            ->suffix(' %')
                            ->color('warning')
                            ->weight('bold'),
                    ]),

                // SECTION 3: TABEL DETAIL
                Infolists\Components\Section::make('Rincian Per Eartag')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label(false)
                            ->schema([
                                Infolists\Components\TextEntry::make('weighingItem.receivingItem.eartag')->label('Eartag')->weight('bold'),
                                Infolists\Components\TextEntry::make('weighingItem.weight')->label('Live (Kg)')->numeric(2)->badge()->color('info'),
                                Infolists\Components\TextEntry::make('carcass_1')->label('Carcass1'),
                                Infolists\Components\TextEntry::make('carcass_2')->label('Carcass2'),
                                Infolists\Components\TextEntry::make('hides')->label('Hides'),
                                Infolists\Components\TextEntry::make('tail')->label('Tail'),
                                Infolists\Components\TextEntry::make('yield_item')
                                    ->label('Yield')
                                    ->getStateUsing(fn($record) => $record->weighingItem->weight > 0 ? (($record->carcass_1 + $record->carcass_2) / $record->weighingItem->weight) * 100 : 0)
                                    ->numeric(2)
                                    ->suffix('%'),
                            ])->columns(7)
                    ])
            ]);
    }
}
