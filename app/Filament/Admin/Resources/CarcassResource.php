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
    protected static ?string $navigationGroup = 'Cattle Operations';
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
                        }),
                    Forms\Components\TextInput::make('po_number')
                        ->label('PO Number')
                        ->disabled()
                        ->dehydrated(false)
                        ->default(function() {
                            $weighingId = request()->query('weighing_id');
                            return $weighingId ? \App\Models\CattleWeighing::with('receiving.purchaseCattle')->find($weighingId)?->receiving?->purchaseCattle?->document_number : null;
                        }),
                    Forms\Components\TextInput::make('supplier_name')
                        ->label('Supplier')
                        ->disabled()
                        ->dehydrated(false)
                        ->default(function() {
                            $weighingId = request()->query('weighing_id');
                            return $weighingId ? \App\Models\CattleWeighing::with('receiving.supplier')->find($weighingId)?->receiving?->supplier?->name : null;
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
                                ->label('Eartag'),
                            Forms\Components\TextInput::make('carcass_1')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(350),
                            Forms\Components\TextInput::make('carcass_2')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(350)
                                ->rules([
                                    function () {
                                        return function (string $attribute, $value, \Closure $fail) {
                                            $carcass1 = request()->input(str_replace('carcass_2', 'carcass_1', $attribute));
                                            if ($carcass1 !== null && abs((float)$value - (float)$carcass1) > 100) {
                                                $fail('Selisih Carcass A dan B maksimal 100 KG.');
                                            }
                                        };
                                    },
                                ]),
                            Forms\Components\TextInput::make('hides')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100),
                            Forms\Components\TextInput::make('tail')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(0),
                            Forms\Components\TextInput::make('notes')
                                ->label('Note'),
                        ])
                        ->columns(7)
                        ->addable(false) // Disable adding new rows, only process existing eartags
                        ->deletable(true)
                        ->label('')
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('carcass_number')->label('Carcass No')->searchable(),
                Tables\Columns\TextColumn::make('weighing.weighing_number')->label('Weighing No')->searchable(),
                Tables\Columns\TextColumn::make('kill_date')->date(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Total Sapi'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListCarcasses::route('/'),
            'draft' => Pages\DraftCarcass::route('/draft'),
            'create' => Pages\CreateCarcass::route('/create'),
            'view' => Pages\ViewCarcass::route('/{record}'),
            'edit' => Pages\EditCarcass::route('/{record}/edit'),
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
