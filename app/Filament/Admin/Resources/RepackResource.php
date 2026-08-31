<?php

namespace App\Filament\Admin\Resources;

use App\Support\DocumentNumber;

use App\Filament\Admin\Resources\RepackResource\Pages;
use App\Models\Repack;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use App\Models\Material;

class RepackResource extends Resource
{
    protected static ?string $model = Repack::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    public static function getNavigationGroup(): ?string
    {
        return __('PRODUCTION');
    }

    public static function getNavigationLabel(): string
    {
        return __('Repack');
    }

    public static function getModelLabel(): string
    {
        return __('Repack');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Repacks');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Repack Document'))
                    ->schema([
                        Forms\Components\TextInput::make('doc_no')
                            ->label(__('No. Proses (Batch)'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function () {
                                $currentYear = date('Y');
                                // Pratinjau nomor berikutnya. Memakai jalur yang SAMA dengan
                                // penyimpanannya, supaya yang ditampilkan tidak pernah
                                // berbeda dari yang akhirnya tersimpan.
                                return DocumentNumber::next(
                                    query: Repack::withTrashed(),
                                    column: 'doc_no',
                                    prefix: 'RP#'.date('y'),
                                    padding: 3,
                                );
                            })
                            ->readOnly()
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('repack_date')
                            ->label(__('Tgl. Proses'))
                            ->required()
                            ->default(now())
                            ->disabled(fn (?Repack $record) => $record?->kunci == 1)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Catatan / Keterangan'))
                            ->disabled(fn (?Repack $record) => $record?->kunci == 1)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('summary_data')
                            ->label(__('Data Bahan & Hasil (View Only)'))
                            ->visible(fn (?Repack $record) => $record !== null)
                            ->columnSpanFull()
                            ->content(fn (?Repack $record) => $record ? view('filament.resources.repack-resource.pages.edit-summary', ['record' => $record]) : null),

                        Forms\Components\Hidden::make('status')
                            ->default('OPEN'),

                        Forms\Components\Hidden::make('kunci')
                            ->default(0),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => Auth::id()),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Repack $record): string => static::getUrl('edit', ['record' => $record->id]))
            ->columns([
                Tables\Columns\TextColumn::make('doc_no')
                    ->label(__('No. Proses'))
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('repack_date')
                    ->label(__('Tgl. Proses'))
                    ->date('d-M-Y')
                    ->sortable(),

                /* Kalkulasi Total Bahan (Input) */
                Tables\Columns\TextColumn::make('total_bahan')
                    ->label(__('Total Bahan'))
                    ->getStateUsing(function (Repack $record) {
                        return DB::table('repack_materials')->where('repack_id', $record->id)->sum('weight');
                    })
                    ->numeric(2)
                    ->suffix(' Kg'),

                /* Kalkulasi Total Hasil (Output) */
                Tables\Columns\TextColumn::make('total_hasil')
                    ->label(__('Total Hasil'))
                    ->getStateUsing(function (Repack $record) {
                        return DB::table('repack_results')
                            ->where('repack_id', $record->id)
                            ->whereNull('deleted_at')
                            ->sum('weight');
                    })
                    ->numeric(2)
                    ->suffix(' Kg'),

                /* Kalkulasi Lost / Balance */
                Tables\Columns\TextColumn::make('lost')
                    ->label(__('Balance (Lost)'))
                    ->getStateUsing(function (Repack $record) {
                        $bahan = DB::table('repack_materials')->where('repack_id', $record->id)->sum('weight');
                        $hasil = DB::table('repack_results')
                            ->where('repack_id', $record->id)
                            ->whereNull('deleted_at')
                            ->sum('weight');
                        return $hasil - $bahan;
                    })
                    ->numeric(2)
                    ->suffix(' Kg')
                    ->badge()
                    ->color(fn($state) => $state < 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('note')
                    ->label(__('Catatan'))
                    ->limit(30),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    /* Tombol Lock */
                    Tables\Actions\Action::make('lock')
                        ->label(__('Lock'))
                        // Ikon dan warna menggambarkan KEADAAN, bukan aksinya --
                        // lihat catatan yang sama di BoningResource.
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->tooltip(__('Kunci Repack (Final)'))
                        ->requiresConfirmation()
                        ->modalHeading(__('Lock Repack Data'))
                        ->modalDescription(__('Are you sure you want to lock this data? Once locked, you cannot modify it.'))
                        ->action(function (Repack $record) {
                            $record->update(['kunci' => true, 'status' => 'LOCKED']);
                            Notification::make()->title(__('Repack Locked'))->success()->send();
                            return redirect(static::getUrl('index'));
                        })
                        ->hidden(fn(Repack $record) => !auth()->user()->hasPermission('lock_repacks') || $record->kunci || !$record->materialUsages()->exists()),

                    /* Tombol Unlock */
                    Tables\Actions\Action::make('unlock')
                        ->label(__('Unlock'))
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->tooltip(__('Buka Kunci'))
                        ->requiresConfirmation()
                        ->modalHeading(__('Unlock Repack Data'))
                        ->modalDescription(__('Are you sure you want to unlock this data? It will become editable again.'))
                        ->action(function (Repack $record) {
                            $record->update(['kunci' => false, 'status' => 'OPEN']);
                            Notification::make()->title(__('Repack Unlocked'))->success()->send();
                            return redirect(static::getUrl('index'));
                        })
                        ->hidden(fn(Repack $record) => !auth()->user()->hasPermission('lock_repacks') || !$record->kunci),

                    /* Tombol Material Usage */
                    Tables\Actions\Action::make('materialUsage')
                        ->label(__('Material Usage'))
                        ->icon('heroicon-o-square-3-stack-3d')
                        ->color('info')
                        ->tooltip(__('Input Material Usage'))
                        ->url(fn(Repack $record): string => static::getUrl('material-usage', ['record' => $record->id]))
                        ->hidden(fn(Repack $record) => $record->kunci),

                    /* Tombol Input Bahan */
                    Tables\Actions\Action::make('input_bahan')
                        ->label(__('Input Bahan (Scan)'))
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->hidden(fn(Repack $record) => $record->kunci == 1)
                        ->url(fn(Repack $record) => static::getUrl('input-bahan', ['record' => $record->id])),

                    /* Tombol Input Hasil */
                    Tables\Actions\Action::make('input_hasil')
                        ->label(__('Input Hasil & Labeling'))
                        ->icon('heroicon-o-qr-code')
                        ->color('info')
                        ->hidden(fn(Repack $record) => $record->kunci == 1)
                        ->url(fn(Repack $record) => static::getUrl('input-hasil', ['record' => $record->id])),
                ]),
            ])
            ->filters([
                Tables\Filters\Filter::make('repack_date')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        $from = $data['created_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['created_until'] ?? now()->toDateString();

                        return $query
                            ->when(
                                $from,
                                fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('repack_date', '>=', $date),
                            )
                            ->when(
                                $until,
                                fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('repack_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('From: ' . \Carbon\Carbon::parse($data['created_from'])->toFormattedDateString())
                                ->removeField('created_from');
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Until: ' . \Carbon\Carbon::parse($data['created_until'])->toFormattedDateString())
                                ->removeField('created_until');
                        }
                        return $indicators;
                    })
                    // ->toggleable(isToggledHiddenByDefault: true), // Removed because Filter doesn't have toggleable method
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_excel')
                    ->label(__('Excel'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->get();
                        return response()->streamDownload(function () use ($records) {
                            $writer = new \OpenSpout\Writer\XLSX\Writer();
                            $writer->openToFile('php://output');
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['No. Proses', 'Tgl. Proses', 'Total Bahan', 'Total Hasil', 'Lost', 'Catatan', 'Status']));
                            foreach ($records as $record) {
                                $bahan = \Illuminate\Support\Facades\DB::table('repack_materials')->where('repack_id', $record->id)->sum('weight');
                                $hasil = \Illuminate\Support\Facades\DB::table('repack_results')->where('repack_id', $record->id)->whereNull('deleted_at')->sum('weight');
                                $lost = $hasil - $bahan;
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    $record->doc_no ?? '',
                                    $record->repack_date ?? '',
                                    $bahan,
                                    $hasil,
                                    $lost,
                                    $record->note ?? '',
                                    $record->status ?? '',
                                ]));
                            }
                            $writer->close();
                        }, 'Repack.xlsx');
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepacks::route('/'),
            'create' => Pages\CreateRepack::route('/create'),
            'edit' => Pages\EditRepack::route('/{record}/edit'),
            'input-bahan' => Pages\InputBahanRepack::route('/{record}/input-bahan'),
            'input-hasil' => Pages\InputHasilRepack::route('/{record}/input-hasil'),
            'material-usage' => Pages\MaterialUsageRepack::route('/{record}/material-usage'),
        ];
    }
}
