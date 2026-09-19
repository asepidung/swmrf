<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ExpenseCategoryResource\Pages;
use App\Models\ExpenseCategory;
use App\Support\MasterDataDeletion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Master kecil, satu izin (`manage_expense_categories`) untuk seluruh aksi --
 * lihat ExpenseCategoryPolicy. Ditaruh di grup FINANCE karena modul
 * permission-nya sendiri (`Expenses`, lihat Permission::moduleGroups()) sama
 * dengan ExpenseResource, bukan MASTER DATA generik.
 */
class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 21;

    public static function getNavigationGroup(): ?string
    {
        return __('FINANCE');
    }

    public static function getModelLabel(): string
    {
        return __('Expense Category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Expense Categories');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->unique(ignoreRecord: true)
                    ->autofocus()
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('expenses_count')
                    ->label(__('Expenses'))
                    ->counts('expenses')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordUrl(fn (ExpenseCategory $record): string => Pages\EditExpenseCategory::getUrl([$record->id]))
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            foreach ($records as $record) {
                                MasterDataDeletion::attempt(
                                    fn () => $record->delete(),
                                    __('Expense Category').' '.$record->name,
                                );
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseCategories::route('/'),
            'create' => Pages\CreateExpenseCategory::route('/create'),
            'edit' => Pages\EditExpenseCategory::route('/{record}/edit'),
        ];
    }
}
