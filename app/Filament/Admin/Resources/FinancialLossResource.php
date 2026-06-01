<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\FinancialLossResource\Pages;
use App\Models\FinancialLoss;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Filament\Support\RawJs;

class FinancialLossResource extends Resource
{
    protected static ?string $model = FinancialLoss::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    
    public static function getNavigationGroup(): ?string
    {
        return __('Finance');
    }

    public static function getModelLabel(): string
    {
        return __('Financial Loss');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Financial Losses');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Loss Details'))
                    ->schema([
                        Forms\Components\TextInput::make('reference_number')
                            ->label(__('Document Reference'))
                            ->disabled(),

                        Forms\Components\TextInput::make('transaction_type')
                            ->label(__('Source Module'))
                            ->disabled(),

                        Forms\Components\DatePicker::make('date')
                            ->label(__('Loss Date'))
                            ->disabled(),

                        Forms\Components\TextInput::make('amount')
                            ->label(__('Total Financial Loss'))
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->disabled(),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->columnSpanFull()
                            ->disabled(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label(__('Ref. Number'))
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('transaction_type')
                    ->label(__('Source'))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Total Loss'))
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->weight('bold')
                    ->color('danger')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR', locale: 'id')
                            ->label(__('Total'))
                    ]),

                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(30)
                    ->searchable()
                    ->color('gray'),
            ])
            ->recordUrl(
                fn (Model $record): string => Pages\ViewFinancialLoss::getUrl([$record->getKey()]),
            )
            ->filters([
                Tables\Filters\SelectFilter::make('transaction_type')
                    ->label(__('Filter Source'))
                    ->options([
                        'Cattle Weighing' => 'Cattle Weighing',
                        // More can be added here later
                    ]),

                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('From'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('Until'))
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? now()->startOfMonth()->format('Y-m-d');
                        $until = $data['until'] ?? now()->format('Y-m-d');

                        return $query
                            ->whereDate('date', '>=', $from)
                            ->whereDate('date', '<=', $until);
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        $defaultFrom = now()->startOfMonth()->format('Y-m-d');
                        $defaultUntil = now()->format('Y-m-d');

                        if (($data['from'] ?? null) && $data['from'] !== $defaultFrom) {
                            $indicators[] = Tables\Filters\Indicator::make('From: ' . Carbon::parse($data['from'])->format('d M Y'))
                                ->removeField('from');
                        }
                        if (($data['until'] ?? null) && $data['until'] !== $defaultUntil) {
                            $indicators[] = Tables\Filters\Indicator::make('Until: ' . Carbon::parse($data['until'])->format('d M Y'))
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinancialLosses::route('/'),
            'view' => Pages\ViewFinancialLoss::route('/{record}'),
        ];
    }
}
