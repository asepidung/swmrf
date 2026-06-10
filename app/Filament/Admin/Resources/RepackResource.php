<?php

namespace App\Filament\Admin\Resources;

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
                                $prefix = 'RP#' . date('y');
                                $count = Repack::withTrashed()->whereYear('repack_date', $currentYear)->count();
                                $sequence = $count + 1;
                                return $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
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
                    /* Tombol Lock / Unlock */
                    Tables\Actions\Action::make('toggleLock')
                        ->label(fn(Repack $record) => $record->kunci ? __('Unlock') : __('Lock'))
                        ->icon(fn(Repack $record) => $record->kunci ? 'heroicon-m-lock-closed' : 'heroicon-m-lock-open')
                        ->color(fn(Repack $record) => $record->kunci ? 'danger' : 'success')
                        ->tooltip(fn(Repack $record) => $record->kunci ? __('Buka Kunci') : __('Kunci Repack (Final)'))
                        ->requiresConfirmation()
                        ->visible(function () {
                            /** @var \App\Models\User $user */
                            $user = Auth::user();
                            return $user?->hasPermission('lock_repacks');
                        })
                        ->action(function (Repack $record) {
                            $newLockState = !$record->kunci;
                            $record->update([
                                'kunci' => $newLockState,
                                'status' => $newLockState ? 'LOCKED' : 'OPEN'
                            ]);
                            Notification::make()
                                ->title($newLockState ? __('Repack Locked') : __('Repack Unlocked'))
                                ->success()
                                ->send();
                        }),

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
        ];
    }
}
