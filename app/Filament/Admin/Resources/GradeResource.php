<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GradeResource\Pages;
use App\Models\Grade;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GradeResource extends Resource
{
    protected static ?string $model = Grade::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermission('view_grades');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('view_grades');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('MASTER DATA');
    }

    public static function getModelLabel(): string
    {
        return __('Grade');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Grades');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(fn () => __('Name'))
                    ->autofocus()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                Forms\Components\Toggle::make('is_active')
                    ->label(fn () => __('Active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(fn () => __('Grade Digit'))
                    ->sortable()
                    ->tooltip(fn () => __('This number is embedded in the barcode. Do not change it.')),
                Tables\Columns\TextColumn::make('name')
                    ->label(fn () => __('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(fn () => __('Active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn () => __('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id')
            ->headerActions([
                Tables\Actions\Action::make('export_excel')
                    ->label(fn () => __('Excel'))
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->get();

                        return response()->streamDownload(function () use ($records) {
                            $writer = new \OpenSpout\Writer\XLSX\Writer();
                            $writer->openToFile('php://output');
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['ID', 'Name', 'Active']));
                            foreach ($records as $record) {
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([$record->id, $record->name ?? '', $record->is_active ? 'YES' : 'NO']));
                            }
                            $writer->close();
                        }, 'Grades.xlsx');
                    }),
            ])
            ->actions([
                // Baris tabel dibuat clickable, tombol Edit statis tidak diperlukan
            ])
            ->recordUrl(
                fn (Grade $record): string => Pages\EditGrade::getUrl([$record->id])
            )
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGrades::route('/'),
            'create' => Pages\CreateGrade::route('/create'),
            'edit' => Pages\EditGrade::route('/{record}/edit'),
        ];
    }
}
