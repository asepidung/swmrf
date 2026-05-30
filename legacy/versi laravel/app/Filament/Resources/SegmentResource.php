<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SegmentResource\Pages;
use App\Models\Segment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SegmentResource extends Resource
{
    protected static ?string $model = Segment::class;

    // --- BIAR BOS SENENG: Pengaturan Tampilan Sidebar ---
    protected static ?string $navigationIcon = 'heroicon-o-tag'; // Ikon Label/Tag
    protected static ?string $navigationGroup = 'CUSTOMERS';
    protected static ?int $navigationSort = 6; // Terakhir

    protected static ?string $navigationLabel = 'Customer Segment'; // Label di sidebar
    protected static ?string $modelLabel = 'Customer Segment'; // Label di sidebar
    protected static ?string $pluralModelLabel = 'Customer Segments'; // Label di sidebar
    // ----------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Master Segmentasi')
                    ->description('Tentukan kategori pelanggan (Horeka, Retail, dll)')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Segment')
                            ->placeholder('Contoh: Horeka, Industri, Retail')
                            ->required()
                            ->unique(ignoreRecord: true),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Segment')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSegments::route('/'),
            'create' => Pages\CreateSegment::route('/create'),
            'edit' => Pages\EditSegment::route('/{record}/edit'),
        ];
    }
}
