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
        return __('ACCOUNTING');
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
                            /*
                             * Nilai dari kolom decimal(15,2) berbentuk
                             * "1200000.00". Mask uang membuang karakter
                             * non-digit, jadi dua nol di belakang titik ikut
                             * terbaca sebagai digit dan angkanya tampil
                             * SERATUS KALI LIPAT -- Rp 1,2 juta menjadi 120
                             * juta, tanpa error apa pun.
                             *
                             * Desimalnya dibuang di sini, sebelum mask bekerja.
                             */
                            ->formatStateUsing(fn ($state): ?string => $state === null
                                ? null
                                : number_format((float) $state, 0, ',', '.'))
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

                // Berapa BANYAK yang hilang, di sebelah berapa rupiahnya.
                //
                // Susut kirim tahu persis kilogramnya tetapi belum tahu
                // rupiahnya -- menilainya butuh HPP, dan HPP menunggu B.O.M.
                // Tanpa kolom ini satu-satunya jejak kuantitasnya ada di dalam
                // kalimat catatan, tidak bisa dijumlah maupun diurut.
                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('Quantity Lost'))
                    ->alignRight()
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state, $record): string => $state === null
                        ? '-'
                        : number_format((float) $state, 2, ',', '.').' '.($record->unit ?? ''))
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->numeric(decimalPlaces: 2)
                            ->label(__('Total')),
                    ]),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Total Loss'))
                    ->money('IDR', locale: 'id')
                    // Nol yang berarti "belum dinilai" tidak boleh terbaca sama
                    // dengan nol yang berarti "memang tidak rugi".
                    ->description(fn ($record): ?string => $record->isNotPricedYet()
                        ? __('Not valued yet, waiting for cost price')
                        : null)
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
                // Pilihannya dibaca dari daftar di model, bukan ditulis
                // ulang di sini. Daftar yang ditulis tangan sudah sekali
                // ketinggalan: susut kirim tidak pernah bisa dipilih.
                Tables\Filters\SelectFilter::make('transaction_type')
                    ->label(__('Filter Source'))
                    ->options(collect(FinancialLoss::SEMUA_SUMBER)
                        ->mapWithKeys(fn (string $sumber): array => [$sumber => __($sumber)])
                        ->all()),

                // Keputusan Owner, 7 September 2026 (dikoreksi ulang setelah
                // rujukan CashBookResource ditemukan): bukan soal "ada
                // default" atau "tidak ada default" -- soal form dan query
                // TIDAK SEPAKAT. Perbaikan pertama menghapus default-nya sama
                // sekali (kosong = tanpa batasan), padahal `project.md:112`
                // mewajibkan silent date filter, dan Owner sendiri minta
                // "dari tanggal 1 bulan ini sampai hari ini" tetap jadi
                // tampilan bawaan -- cuma badge-nya yang tidak boleh terlihat
                // aktif sejak awal. Sekarang ikut pola CashBookResource:
                // default ADA di form (jujur kalau panel dibuka), badge cuma
                // tampil kalau user mengubahnya dari default.
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
                            ->when($from, fn (Builder $q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($until, fn (Builder $q, $date) => $q->whereDate('date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        $defaultFrom = now()->startOfMonth()->format('Y-m-d');
                        $defaultUntil = now()->format('Y-m-d');

                        if (($data['from'] ?? null) && $data['from'] !== $defaultFrom) {
                            $indicators[] = Tables\Filters\Indicator::make(__('From').': '.Carbon::parse($data['from'])->format('d M Y'))
                                ->removeField('from');
                        }

                        if (($data['until'] ?? null) && $data['until'] !== $defaultUntil) {
                            $indicators[] = Tables\Filters\Indicator::make(__('Until').': '.Carbon::parse($data['until'])->format('d M Y'))
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
