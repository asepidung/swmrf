<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\QcReportResource\Pages;
use App\Models\QcReport;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Laporan QC yang mendampingi dokumen lain.
 *
 * Satu Resource untuk SEMUA titik QC. Jenis dokumen yang didampingi ada di
 * `QcReport::DOKUMEN`; menambah titik berarti menambah satu baris di sana,
 * bukan menambah satu Resource.
 */
class QcReportResource extends Resource
{
    protected static ?string $model = QcReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('QC');
    }

    public static function getModelLabel(): string
    {
        return __('QC Report');
    }

    public static function getPluralModelLabel(): string
    {
        return __('QC Reports');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Accompanied document'))
                ->schema([
                    Forms\Components\TextInput::make('jenis')
                        ->label(__('Document type'))
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn ($record): ?string => $record?->jenisDokumen()),

                    Forms\Components\TextInput::make('nomor')
                        ->label(__('Document number'))
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn ($record): ?string => $record?->nomorDokumen()),

                    Forms\Components\Hidden::make('reportable_type'),
                    Forms\Components\Hidden::make('reportable_id'),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Inspection'))
                ->schema([
                    /*
                     * Kapan hal ini BENAR-BENAR terjadi -- bukan kapan
                     * barisnya diketik.
                     *
                     * Laporan QC hampir selalu ditulis sesudah kejadiannya;
                     * QC memindahkannya dari catatan manual. Kalau yang
                     * tersimpan hanya waktu ketik, seluruh laporannya
                     * menunjuk jam yang salah -- dan justru jam itu yang
                     * ditanya kalau ada yang ditelusuri.
                     */
                    Forms\Components\DateTimePicker::make('occurred_at')
                        ->label(__('When it happened'))
                        ->helperText(__('The time of the event itself, not the time this form is filled in.'))
                        ->seconds(false)
                        ->native(false)
                        ->displayFormat('d M Y H:i')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),

                    // Satu-satunya bagian yang WAJIB. Proses yang tidak
                    // bermasalah tetap punya laporan, isinya kalimat ini.
                    Forms\Components\Textarea::make('note')
                        ->label(__('General note'))
                        ->helperText(__('What happened overall, even when nothing went wrong.'))
                        ->rows(3)
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Findings'))
                ->description(__('Leave this empty when nothing went wrong. Do not add a blank row.'))
                ->schema([
                    Forms\Components\Repeater::make('findings')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel(__('Add a finding'))
                        ->defaultItems(0)
                        ->schema([
                            // Wajib kalau barisnya ada: temuan tanpa
                            // keterangan bukan temuan.
                            Forms\Components\Textarea::make('description')
                                ->label(__('What was found'))
                                ->rows(2)
                                ->required()
                                ->columnSpan(['default' => 1, 'md' => 2]),

                            Forms\Components\TextInput::make('affected_count')
                                ->label(__('How many affected'))
                                ->helperText(__('Optional'))
                                ->integer()
                                ->minValue(1),

                            Forms\Components\Textarea::make('action_taken')
                                ->label(__('Action taken'))
                                ->helperText(__('Optional; it may still be unknown while writing.'))
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(['default' => 1, 'md' => 3]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label(__('Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('reportable_type')
                    ->label(__('Document type'))
                    ->badge()
                    ->formatStateUsing(fn (QcReport $record): string => $record->jenisDokumen()),

                Tables\Columns\TextColumn::make('reportable_id')
                    ->label(__('Document number'))
                    ->formatStateUsing(fn (QcReport $record): string => $record->nomorDokumen()),

                Tables\Columns\TextColumn::make('occurred_at')
                    ->label(__('When it happened'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                /*
                 * Berapa temuannya, bukan isinya.
                 *
                 * Nol berarti prosesnya berjalan tanpa masalah, dan itu
                 * jawaban yang sah -- bukan laporan yang belum diisi.
                 */
                Tables\Columns\TextColumn::make('findings_count')
                    ->label(__('Findings'))
                    ->counts('findings')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Inspector'))
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reportable_type')
                    ->label(__('Document type'))
                    ->options(fn (): array => collect(QcReport::DOKUMEN)
                        ->mapWithKeys(fn (string $kelas): array => [$kelas => __(class_basename($kelas))])
                        ->all()),

                Tables\Filters\TernaryFilter::make('findings')
                    ->label(__('Has findings'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->has('findings'),
                        false: fn (Builder $query): Builder => $query->doesntHave('findings'),
                        blank: fn (Builder $query): Builder => $query,
                    ),

                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()?->hasPermission('view_deleted_qc_reports') ?? false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('occurred_at', 'desc');
    }

    /** Alasannya di `App\Support\TrashedRecords`. */
    public static function getEloquentQuery(): Builder
    {
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_qc_reports',
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQcReports::route('/'),
            'create' => Pages\CreateQcReport::route('/create'),
            'view' => Pages\ViewQcReport::route('/{record}'),
            'edit' => Pages\EditQcReport::route('/{record}/edit'),
        ];
    }
}
