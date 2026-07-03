<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MutationResource\Pages;
use App\Filament\Admin\Resources\MutationResource\RelationManagers;
use App\Models\Mutation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MutationResource extends Resource
{
    protected static ?string $model = Mutation::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'WAREHOUSE';
    public static function getModelLabel(): string
    {
        return __('Mutation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Mutations');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Mutation Header'))
                    ->schema([
                        Forms\Components\DatePicker::make('mutation_date')
                            ->label(__('Mutation Date'))
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('from_warehouse_id')
                            ->label(__('From Warehouse'))
                            ->options(\App\Models\Warehouse::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->disabled(fn (?\App\Models\Mutation $record) => $record && $record->items()->exists()),
                        Forms\Components\Select::make('to_warehouse_id')
                            ->label(__('To Warehouse'))
                            ->options(\App\Models\Warehouse::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mutation_number')
                    ->label(__('Mutation Number'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mutation_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fromWarehouse.name')
                    ->label(__('From Warehouse'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('toWarehouse.name')
                    ->label(__('To Warehouse'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'SENT' => 'warning',
                        'COMPLETED' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->check() && auth()->user()->can('view_deleted_mutations')),
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
            ])
            ->recordClasses(fn (Mutation $record) => match ($record->trashed()) {
                true => 'border-s-2 border-red-600 dark:border-red-500',
                false => null,
            });
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make(__('Mutation Header'))
                    ->compact()
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('mutation_number')->label(__('Mutation Number')),
                        \Filament\Infolists\Components\TextEntry::make('mutation_date')->label(__('Date'))->date('d M Y'),
                        \Filament\Infolists\Components\TextEntry::make('fromWarehouse.name')->label(__('From Warehouse')),
                        \Filament\Infolists\Components\TextEntry::make('toWarehouse.name')->label(__('To Warehouse')),
                        \Filament\Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->color(fn (string $state): string => match ($state) {
                            'DRAFT' => 'gray',
                            'SENT' => 'warning',
                            'COMPLETED' => 'success',
                            default => 'gray',
                        }),
                        \Filament\Infolists\Components\TextEntry::make('note')->label(__('Note'))->columnSpanFull(),
                    ])->columns(5),

                \Filament\Infolists\Components\Section::make(__('Item Summary'))
                    ->schema([
                        \Filament\Infolists\Components\ViewEntry::make('items_summary')
                            ->hiddenLabel()
                            ->view('filament.admin.resources.mutation-resource.summary')
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
            'index' => Pages\ListMutations::route('/'),
            'create' => Pages\CreateMutation::route('/create'),
            'view' => Pages\ViewMutation::route('/{record}'),
            'edit' => Pages\EditMutation::route('/{record}/edit'),
            'scan' => Pages\ScanMutation::route('/{record}/scan'),
            'receive' => Pages\ReceiveMutation::route('/{record}/receive'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (auth()->check() && auth()->user()->can('view_deleted_mutations')) {
            $query->withoutGlobalScopes([SoftDeletingScope::class]);
        }
        
        return $query;
    }
}
