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
                            ->label(__('Process No. (batch)'))
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
                            ->label(__('Process date'))
                            ->required()
                            ->default(now())
                            ->disabled(fn (?Repack $record) => $record?->kunci == 1)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note / description'))
                            ->disabled(fn (?Repack $record) => $record?->kunci == 1)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('summary_data')
                            ->label(__('Materials & output (read only)'))
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
                    ->label(__('Process No.'))
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('repack_date')
                    ->label(__('Process date'))
                    ->date('d-M-Y')
                    ->sortable(),

                /* Kalkulasi Total Bahan (Input) */
                // Ketiga angka ini dulu dihitung dengan `DB::table()` mentah,
                // masing-masing mengulang rumusnya sendiri. Salah satunya
                // menyaring `deleted_at` dan yang lain tidak -- padahal hanya
                // `repack_results` yang punya hapus lunak, jadi penyaringnya
                // ditulis ulang dengan tangan di tempat yang kebetulan tepat.
                // Sekarang semuanya lewat model, dan penyaringnya datang
                // sendiri dari relasinya.
                Tables\Columns\TextColumn::make('total_bahan')
                    ->label(__('Total materials'))
                    ->state(fn (Repack $record): float => $record->inputWeight())
                    ->numeric(2)
                    ->alignRight()
                    ->suffix(' Kg'),

                Tables\Columns\TextColumn::make('total_hasil')
                    ->label(__('Total output'))
                    ->state(fn (Repack $record): float => $record->outputWeight())
                    ->numeric(2)
                    ->alignRight()
                    ->suffix(' Kg'),

                // Warnanya dulu TERBALIK. Kolomnya menghitung `hasil - bahan`,
                // lalu memberi warna merah pada nilai negatif dan hijau pada
                // positif -- sehingga dokumen yang hasilnya LEBIH BERAT
                // daripada bahannya, yang mustahil secara fisik dan hampir
                // pasti salah ketik, terbaca sebagai kabar baik.
                //
                // Sekarang yang ditampilkan susutnya (bahan - hasil), dan
                // warnanya mengikuti arti: wajar abu-abu, di luar batas merah,
                // hasil lebih berat daripada bahan juga merah.
                Tables\Columns\TextColumn::make('susut')
                    ->label(__('Shrink'))
                    ->state(function (Repack $record): string {
                        $persen = $record->shrinkPercent();

                        if ($persen === null) {
                            return '-';
                        }

                        return number_format($record->shrinkWeight(), 2, ',', '.')
                            .' Kg ('.number_format($persen, 2, ',', '.').'%)';
                    })
                    ->alignRight()
                    ->badge()
                    ->color(fn (Repack $record): string => $record->isWithinShrinkLimit() ? 'gray' : 'danger')
                    ->description(fn (Repack $record): ?string => $record->shrinkLimitWasOverridden()
                        ? __('Approved by :name', ['name' => $record->yieldOverriddenBy?->name ?? '-'])
                        : null),

                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
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
                        ->tooltip(fn (Repack $record): string => $record->isWithinShrinkLimit()
                            ? __('Lock repack (final)')
                            : __('Shrinkage is outside the reasonable limit; QC approval is needed.'))
                        ->requiresConfirmation()
                        ->modalHeading(__('Lock Repack Data'))
                        ->modalDescription(__('Are you sure you want to lock this data? Once locked, you cannot modify it.'))
                        // Susut di luar batas TIDAK membuang dokumennya dan
                        // tidak menyembunyikan tombolnya. Tombolnya mati dengan
                        // keterangan, supaya yang mengerjakan tahu ia sedang
                        // menunggu siapa -- bukan menghadapi tombol yang lenyap
                        // tanpa penjelasan.
                        ->disabled(fn (Repack $record): bool => ! $record->isWithinShrinkLimit()
                            && ! (auth()->user()?->hasPermission('override_repack_yield') ?? false))
                        // Alasannya hanya diminta ketika memang menembus.
                        ->form(fn (Repack $record): array => $record->isWithinShrinkLimit() ? [] : [
                            Forms\Components\Placeholder::make('ringkasan')
                                ->label(__('Shrinkage'))
                                ->content(fn (): string => number_format($record->shrinkWeight(), 2, ',', '.')
                                    .' Kg ('.number_format((float) $record->shrinkPercent(), 2, ',', '.').'%) '
                                    .__('of the :limit% limit', [
                                        'limit' => number_format((float) Repack::shrinkLimitPercent(), 2, ',', '.'),
                                    ])),

                            Forms\Components\Textarea::make('yield_override_reason')
                                ->label(__('Reason for approving beyond the limit'))
                                ->required()
                                ->maxLength(500)
                                ->rows(3),
                        ])
                        ->action(function (Repack $record, array $data) {
                            try {
                                $record->lock($data['yield_override_reason'] ?? null);
                            } catch (\Throwable $e) {
                                Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                                return;
                            }

                            Notification::make()->title(__('Repack Locked'))->success()->send();

                            return redirect(static::getUrl('index'));
                        })
                        ->hidden(fn (Repack $record) => ! auth()->user()->hasPermission('lock_repacks') || $record->kunci || ! $record->materialUsages()->exists()),

                    /* Tombol Unlock */
                    Tables\Actions\Action::make('unlock')
                        ->label(__('Unlock'))
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->tooltip(__('Unlock'))
                        ->requiresConfirmation()
                        ->modalHeading(__('Unlock Repack Data'))
                        ->modalDescription(__('Are you sure you want to unlock this data? It will become editable again.'))
                        ->action(function (Repack $record) {
                            $record->unlock();
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
                        ->label(__('Enter materials (scan)'))
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->hidden(fn(Repack $record) => $record->kunci == 1)
                        ->url(fn(Repack $record) => static::getUrl('input-bahan', ['record' => $record->id])),

                    /* Tombol Input Hasil */
                    Tables\Actions\Action::make('input_hasil')
                        ->label(__('Output & labelling'))
                        ->icon('heroicon-o-qr-code')
                        ->color('info')
                        // Hasil tidak bisa diinput sebelum bahannya ada.
                        //
                        // Aturan ini sudah ada di aplikasi lama dan HILANG saat
                        // ditulis ulang -- tombolnya hanya disembunyikan ketika
                        // dokumennya terkunci. Tanpa bahan, hasil yang diinput
                        // menjadi stok yang tidak berasal dari mana pun, dan
                        // susutnya tidak bisa dihitung sama sekali.
                        ->disabled(fn (Repack $record): bool => $record->materials()->doesntExist())
                        ->tooltip(fn (Repack $record): ?string => $record->materials()->doesntExist()
                            ? __('Input the goods first before entering the results.')
                            : null)
                        ->hidden(fn(Repack $record) => $record->kunci == 1)
                        ->url(fn(Repack $record) => static::getUrl('input-hasil', ['record' => $record->id])),
                ]),
            ])
            ->filters([
                // Repack memakai hapus lunak dan `RepackPolicy` punya
                // `restore()`, tetapi tidak ada satu pun layar yang
                // menampilkan baris terhapusnya -- jadi izin memulihkan itu
                // tidak pernah bisa dipakai, dan repack yang telanjur
                // terhapus hilang untuk selamanya dari pandangan.
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()?->hasPermission('view_deleted_repacks') ?? false),

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
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([__('Process No.'), __('Process date'), __('Total materials'), __('Total output'), __('Lost'), __('Note'), __('Status')]));
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

    /**
     * Baris terhapus hanya ikut terbawa untuk yang memang berhak melihatnya.
     *
     * Bukan `withoutGlobalScopes` telanjang lalu diandalkan disaring kembali
     * oleh `TrashedFilter`: filter yang tidak terlihat TIDAK menyaring apa
     * pun -- Filament membuangnya sebelum query dijalankan. Batasnya harus
     * izin itu sendiri. Alasan lengkapnya di `App\Support\TrashedRecords`.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Support\TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_repacks',
        );
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
