<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerGroupResource\Pages;
use App\Models\CustomerGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerGroupResource extends Resource
{
    protected static ?string $model = CustomerGroup::class;

    // --- PENGATURAN SIDEBAR ---
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack'; // Ikon Tumpukan
    protected static ?string $navigationGroup = 'CUSTOMERS';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Group Customer'; // Label di sidebar
    protected static ?string $modelLabel = 'Group Customer'; // Label di sidebar
    protected static ?string $pluralModelLabel = 'Group Customers'; // Label di sidebar
    // --------------------------

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer Group Information')
                    ->description('Grup ini digunakan untuk pengelompokan harga (Pricelist)')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Group Name')
                            ->placeholder('Contoh: AEON, ABUBA, RETAIL UMUM')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Catatan tambahan jika ada...'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Group Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50),
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
            'index' => Pages\ListCustomerGroups::route('/'),
            'create' => Pages\CreateCustomerGroup::route('/create'),
            'edit' => Pages\EditCustomerGroup::route('/{record}/edit'),
        ];
    }
}
