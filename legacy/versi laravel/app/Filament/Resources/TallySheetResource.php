<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TallySheetResource\Pages;
use App\Models\TallySheet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TallySheetResource extends Resource
{
    protected static ?string $model = TallySheet::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'WAREHOUSE';
    protected static ?string $navigationLabel = 'Tally Sheet';
    protected static ?int $navigationSort = 10;

    // MATIKAN TOMBOL CREATE BAWAAN
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Tally')
                    ->compact()
                    ->schema([
                        // Nomor Tally (Auto Generate)
                        Forms\Components\TextInput::make('tally_number')
                            ->label('Nomor Tally')
                            ->default('TL-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)))
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        // Pilih Sales Order yang statusnya masih WAITING atau PROCESSING
                        Forms\Components\Select::make('sales_order_id')
                            ->label('Nomor Sales Order')
                            ->relationship('salesOrder', 'so_number', fn(Builder $query) => $query->whereIn('status', ['waiting', 'processing']))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'), // Tidak boleh ganti SO kalau Tally sudah dibuat

                        Forms\Components\DatePicker::make('tally_date')
                            ->label('Tanggal Timbang')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status Tally')
                            ->options([
                                'DRAFT' => 'DRAFT',
                                'POSTED' => 'POSTED',
                            ])
                            ->default('DRAFT')
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Forms\Components\Textarea::make('note')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),

                        // Simpan ID User yang login sebagai Operator
                        Forms\Components\Hidden::make('operator_id')
                            ->default(fn() => auth()->id()),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tally_number')
                    ->label('No. Tally')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('salesOrder.so_number')
                    ->label('No. SO')
                    ->searchable()
                    ->sortable()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('salesOrder.customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tally_date')
                    ->label('Tgl Timbang')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('operator.name')
                    ->label('Operator')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => 'DRAFT',
                        'success' => 'POSTED',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'DRAFT' => 'DRAFT',
                        'POSTED' => 'POSTED',
                    ]),
            ])
            // POIN 2: MENYEDIAKAN HANYA 4 BUTTON DI INDEX (Fungsi diubah nanti)
            ->actions([
                // 1. View Button
                Tables\Actions\ViewAction::make()->iconButton(),

                // 2. Mulai Scan Button
                Tables\Actions\Action::make('mulai_scan')
                    ->label('Mulai Scan')
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->iconButton()
                    ->url(fn(TallySheet $record) => TallySheetResource::getUrl('scan', ['record' => $record->id])),

                // 3. Approve Button
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->iconButton()
                    ->action(function (TallySheet $record) {
                        // Logika diubah nanti sesuai instruksi lu selanjutnya
                    }),

                // 4. Delete Button
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc')

            // POIN 3: MATIKAN FUNGSI BAWAAN FILAMENT (Klik baris tidak akan membuka halaman edit)
            ->recordUrl(null)
            ->recordAction(null);
    }

    public static function getRelations(): array
    {
        return [
            // Nanti kita akan pasang RelationManager untuk Barcode Scanner di sini
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTallySheets::route('/'),
            'create' => Pages\CreateTallySheet::route('/create'),
            'view' => Pages\ViewTallySheet::route('/{record}'),
            'edit' => Pages\EditTallySheet::route('/{record}/edit'),
            'scan' => Pages\ScanTally::route('/{record}/scan'),
        ];
    }
}
