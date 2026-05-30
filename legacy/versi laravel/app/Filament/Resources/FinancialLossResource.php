<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinancialLossResource\Pages;
use App\Models\FinancialLoss;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class FinancialLossResource extends Resource
{
    protected static ?string $model = FinancialLoss::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationGroup = 'FINANCE';
    protected static ?string $navigationLabel = 'Financial Loss';
    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Loss Details')
                    ->schema([
                        Forms\Components\TextInput::make('reference_number')
                            ->label('Document Reference')
                            ->disabled(),

                        Forms\Components\TextInput::make('lossable_type')
                            ->label('Source Module')
                            ->formatStateUsing(fn($state) => match ($state) {
                                'App\Models\CattleWeighingLoss' => 'Cattle Weighing Module',
                                'App\Models\BeefRepack' => 'Beef Repack Module',
                                default => str_replace('App\\Models\\', '', $state),
                            })
                            ->disabled(),

                        Forms\Components\DatePicker::make('loss_date')
                            ->label('Loss Date')
                            ->disabled(),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Financial Loss')
                            ->prefix('Rp')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->disabled(),

                        Forms\Components\Textarea::make('note')
                            ->columnSpanFull()
                            ->disabled(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordAction(Tables\Actions\ViewAction::class)
            ->columns([
                Tables\Columns\TextColumn::make('loss_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Ref. Number')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('lossable_type')
                    ->label('Source')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'App\Models\CattleWeighingLoss' => 'Weighing',
                        'App\Models\BeefRepack' => 'Repack',
                        default => str_replace('App\\Models\\', '', $state),
                    }),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Loss')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold')
                    ->color('danger')
                    // INI OBATNYA BUAT TOTAL DI BAWAH TABEL
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total')
                    ]),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'POSTED' => 'success',
                        'DRAFT' => 'warning',
                        'ADJUSTED' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('note')
                    ->limit(30)
                    ->searchable()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lossable_type')
                    ->label('Filter Source')
                    ->options(function () {
                        $types = FinancialLoss::query()->distinct()->pluck('lossable_type')->filter();
                        $options = [];
                        foreach ($types as $type) {
                            $options[$type] = match ($type) {
                                'App\Models\CattleWeighingLoss' => 'Weighing',
                                'App\Models\BeefRepack' => 'Repack',
                                default => str_replace('App\\Models\\', '', $type),
                            };
                        }
                        return $options;
                    }),

                Tables\Filters\Filter::make('loss_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date')
                            ->default(now()->startOfMonth()), // Default 1 bulan ini
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date')
                            ->default(now()), // Default hari ini
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // Jika filter tidak diisi (kondisi awal), tetap ter-filter pakai default
                        $from = $data['from'] ?? now()->startOfMonth()->format('Y-m-d');
                        $until = $data['until'] ?? now()->format('Y-m-d');

                        return $query
                            ->whereDate('loss_date', '>=', $from)
                            ->whereDate('loss_date', '<=', $until);
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        // Definisikan nilai default untuk pembanding
                        $defaultFrom = now()->startOfMonth()->format('Y-m-d');
                        $defaultUntil = now()->format('Y-m-d');

                        // Indikator HANYA muncul jika user mengubah dari default
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
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinancialLosses::route('/'),
            'view' => Pages\ViewFinancialLoss::route('/{record}'),
        ];
    }
}
