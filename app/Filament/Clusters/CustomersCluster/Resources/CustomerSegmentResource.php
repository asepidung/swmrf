<?php

namespace App\Filament\Clusters\CustomersCluster\Resources;

use App\Filament\Clusters\CustomersCluster;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerSegmentResource\Pages;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerSegmentResource\RelationManagers;
use App\Models\CustomerSegment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerSegmentResource extends Resource
{
    protected static ?string $model = CustomerSegment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $cluster = CustomersCluster::class;

    public static function getModelLabel(): string
    {
        return __('Customer Segment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Customer Segments');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->recordUrl(
                fn (CustomerSegment $record): string => Pages\EditCustomerSegment::getUrl([$record->id])
            )
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListCustomerSegments::route('/'),
            'create' => Pages\CreateCustomerSegment::route('/create'),
            'edit' => Pages\EditCustomerSegment::route('/{record}/edit'),
        ];
    }
}
