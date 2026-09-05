<?php

namespace App\Filament\Clusters\MaterialsStock\Resources;

use App\Filament\Clusters\MaterialsStock;
use App\Filament\Clusters\MaterialsStock\Resources\MaterialFindingResource\Pages;
use App\Models\MaterialFinding;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;

class MaterialFindingResource extends Resource
{
    protected static ?string $model = MaterialFinding::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?string $cluster = MaterialsStock::class;

    protected static \Filament\Pages\SubNavigationPosition $subNavigationPosition = \Filament\Pages\SubNavigationPosition::Top;

    protected static ?int $navigationSort = 3;

    /**
     * Halaman ini MENCETAK STOK, jadi izinnya sendiri.
     *
     * Ia menambah `material_stocks` dari isian orang, tanpa dokumen asal --
     * padanan `FoundItemScanner` di sisi bahan, yang sudah diberi izin
     * `record_found_items` pada #269.
     *
     * Sebelum ini modul ini TIDAK PUNYA policy dan TIDAK PUNYA satu pun izin.
     * Laravel mengizinkan apa saja ketika tidak ada policy, jadi siapa pun
     * yang bisa membuka rumpun Materials Stock -- termasuk yang hanya diberi
     * `view_material_stocks` -- bisa menambah stok bahan sebanyak apa pun,
     * dan menghapusnya lagi.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->isProgrammer()
            || (auth()->user()?->hasPermission('record_material_findings') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    /** Temuan tidak boleh DISUNTING: stoknya sudah terlanjur bergerak. */
    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('Material Finding');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Material Finding');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->label(__('Date'))
                    ->default(now())
                    ->required(),
                Forms\Components\Select::make('material_id')
                    ->label(__('Material'))
                    ->relationship('material', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                // Bilangan BULAT, sama dengan isian hitungan opname material.
                //
                // Keputusan Owner: "material itu gak ada qty koma-komaan".
                // Layar ini dan layar opname sama-sama mengubah stok bahan;
                // membiarkan yang satu menerima pecahan sementara yang lain
                // menolaknya membuat dua angka yang seharusnya sama menjadi
                // berbeda.
                Forms\Components\TextInput::make('qty')
                    ->label(__('Qty (counted)'))
                    ->integer()
                    ->required()
                    ->minValue(1)
                    ->step(1)
                    ->suffix(function (Forms\Get $get) {
                        if ($get('material_id')) {
                            return \App\Models\Material::find($get('material_id'))?->unit?->name;
                        }
                        return null;
                    }),
                Forms\Components\Textarea::make('note')
                    ->label(__('Note'))
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label(__('Document No'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label(__('Date'))
                    ->date('d-M-Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Material Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label(__('Qty'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label(__('Start Date')),
                        Forms\Components\DatePicker::make('created_until')->label(__('End Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->modalHeading(__('Delete this material finding?'))
                    ->modalDescription(__('Deleting this finding takes the stock back to what it was.'))
                    ->modalSubmitActionLabel(__('Yes, delete it and take the stock back')),
            ])
            ->bulkActions([
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMaterialFindings::route('/'),
        ];
    }
}
