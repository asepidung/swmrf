<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialAdjustmentResource\Pages;
use App\Models\MaterialAdjustment;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Filters\TrashedFilter;

class MaterialAdjustmentResource extends Resource
{
    protected static ?string $model = MaterialAdjustment::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    public static function getNavigationGroup(): ?string
    {
        return __('WAREHOUSE');
    }

    public static function getNavigationLabel(): string
    {
        return __('Material Adjustment (Lain-lain)');
    }

    public static function getModelLabel(): string
    {
        return __('Material Adjustment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Material Adjustments');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Adjustment Info'))
                    ->schema([
                        Forms\Components\TextInput::make('doc_no')
                            ->label(__('Nomor Dokumen'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function () {
                                $currentYear = date('Y');
                                $prefix = 'MA#' . date('y');
                                $count = MaterialAdjustment::withTrashed()->whereYear('adjustment_date', $currentYear)->count();
                                $sequence = $count + 1;
                                return $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
                            })
                            ->readOnly()
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('adjustment_date')
                            ->label(__('Tanggal'))
                            ->required()
                            ->default(now())
                            ->autofocus()
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Catatan / Keterangan'))
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('status')
                            ->default('OPEN'),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => Auth::id()),
                    ])->columns(2),

                Forms\Components\Section::make(__('Material Usage / Expense'))
                    ->schema([
                        Forms\Components\Repeater::make('materialUsages')
                            ->relationship('materialUsages')
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->label(__('Material'))
                                    ->options(Material::where('is_active', true)->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                
                                Forms\Components\TextInput::make('qty')
                                    ->label(__('Qty'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01),

                                Forms\Components\TextInput::make('note')
                                    ->label(__('Note'))
                                    ->maxLength(255),
                            ])
                            ->columns(3)
                            ->addActionLabel(__('Add Material Usage'))
                            ->defaultItems(1)
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (MaterialAdjustment $record): string => static::getUrl('edit', ['record' => $record->id]))
            ->recordClasses(fn (MaterialAdjustment $record) => match (true) {
                $record->trashed() => 'border-s-2 border-red-600 dark:border-red-500 bg-red-50/50 dark:bg-red-900/10',
                default => null,
            })
            ->columns([
                Tables\Columns\TextColumn::make('doc_no')
                    ->label(__('No. Dokumen'))
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('adjustment_date')
                    ->label(__('Tanggal'))
                    ->date('d-M-Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('note')
                    ->label(__('Catatan'))
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Dibuat Oleh'))
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
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
            'index' => Pages\ListMaterialAdjustments::route('/'),
            'create' => Pages\CreateMaterialAdjustment::route('/create'),
            'edit' => Pages\EditMaterialAdjustment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
            
        // Silent default filter to current month to today
        if (request()->routeIs('*.index') && empty(request()->query('tableFilters'))) {
            $query->whereBetween('adjustment_date', [
                now()->startOfMonth(),
                now()->endOfDay(),
            ]);
        }
        
        return $query;
    }
}
