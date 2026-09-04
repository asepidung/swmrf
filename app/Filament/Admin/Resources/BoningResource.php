<?php

namespace App\Filament\Admin\Resources;

use App\Support\DocumentNumber;

use App\Filament\Admin\Resources\BoningResource\Pages;
use App\Models\Boning;
use App\Models\Carcass;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class BoningResource extends Resource
{
    protected static ?string $model = Boning::class;

    protected static ?string $navigationIcon = 'heroicon-o-scissors';
    
    public static function getNavigationGroup(): ?string
    {
        return __('PRODUCTION');
    }

    public static function getNavigationLabel(): string
    {
        return __('Boning');
    }

    public static function getModelLabel(): string
    {
        return __('Boning');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Bonings');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Boning Document'))
                    ->schema([
                        Forms\Components\TextInput::make('doc_no')
                            ->label(__('Batch Number'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function () {
                                $currentYear = date('Y');
                                // Pratinjau nomor berikutnya. Memakai jalur yang SAMA dengan
                                // penyimpanannya, supaya yang ditampilkan tidak pernah
                                // berbeda dari yang akhirnya tersimpan.
                                return DocumentNumber::next(
                                    query: Boning::withTrashed(),
                                    column: 'doc_no',
                                    prefix: 'BN'.date('y'),
                                    padding: 3,
                                );
                            })
                            ->readOnly(),

                        Forms\Components\DatePicker::make('boning_date')
                            ->label(__('Boning Date'))
                            ->required()
                            ->default(now())
                            ->autofocus(),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('status')
                            ->default('OPEN'),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => Auth::id()),
                    ])->columns(2),

                Forms\Components\Section::make(__('Select Carcasses for Boning'))
                    ->schema([
                        Forms\Components\Repeater::make('carcasses')
                            ->relationship('carcasses')
                            ->disabledOn('edit')
                            ->schema([
                                Forms\Components\Select::make('carcass_id')
                                    ->label(__('Carcass Number'))
                                    ->options(function (\Filament\Forms\Get $get, ?\Illuminate\Database\Eloquent\Model $record) {
                                        $query = Carcass::query();
                                        
                                        $parentBoningId = $get('../../id');

                                        // 1. Saring karkas yang berada di batch boning yang sudah dikunci
                                        $query->whereDoesntHave('boningCarcasses.boning', function ($q) use ($parentBoningId) {
                                            $q->where('kunci', true);
                                            if ($parentBoningId) {
                                                $q->where('id', '!=', $parentBoningId);
                                            }
                                        });

                                        // 2. Batasi hanya karkas dalam 3 hari terakhir (mengakomodasi akhir pekan) agar dropdown bersih
                                        $threeDaysAgo = now()->subDays(3)->startOfDay();
                                        $query->where(function ($q) use ($threeDaysAgo, $record) {
                                            $q->where('kill_date', '>=', $threeDaysAgo);
                                            
                                            // 3. Selalu sertakan karkas yang saat ini sedang dipilih pada baris ini (untuk edit mode)
                                            if ($record && $record->carcass_id) {
                                                $q->orWhere('id', $record->carcass_id);
                                            }
                                        });

                                        return $query->pluck('carcass_number', 'id');
                                    })
                                    ->required()
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            ])
                            ->addActionLabel(__('Add Another Carcass'))
                            ->columns(1)
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('doc_no')
                    ->label(__('Batch Number'))
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('boning_date')
                    ->label(__('Boning Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier_names')
                    ->label(__('Supplier'))
                    ->getStateUsing(function (Boning $record) {
                        $carcassIds = $record->carcasses->pluck('carcass_id')->toArray();
                        if (empty($carcassIds)) return '-';
                        $suppliers = DB::table('carcasses')
                            ->whereIn('carcasses.id', $carcassIds)
                            ->join('cattle_weighings', 'carcasses.cattle_weighing_id', '=', 'cattle_weighings.id')
                            ->join('cattle_receivings', 'cattle_weighings.cattle_receiving_id', '=', 'cattle_receivings.id')
                            ->join('suppliers', 'cattle_receivings.supplier_id', '=', 'suppliers.id')
                            ->pluck('suppliers.name');
                        return implode(', ', array_unique($suppliers->toArray()));
                    }),

                Tables\Columns\TextColumn::make('total_cattle')
                    ->label(__('Total Cattle'))
                    ->getStateUsing(function (Boning $record) {
                        $carcassIds = $record->carcasses->pluck('carcass_id')->toArray();
                        if (empty($carcassIds)) return 0;
                        return DB::table('carcass_items')->whereIn('carcass_id', $carcassIds)->count();
                    })
                    ->badge()
                    ->color('info'),

                // Berat karkas yang masuk sudah tercatat di `carcass_items`
                // sejak lama -- per ekor, sebagai belahan A, belahan B, kulit,
                // dan buntut. Yang tidak ada hanyalah satu baris kode yang
                // membacanya: sampai 4 September 2026 kata `weight` cuma
                // muncul SEKALI di berkas ini, dan itu pun `->weight('bold')`.
                Tables\Columns\TextColumn::make('berat_karkas')
                    ->label(__('Carcass In'))
                    ->state(fn (Boning $record): float => $record->inputWeight())
                    ->numeric(2)
                    ->alignRight()
                    ->suffix(' Kg'),

                Tables\Columns\TextColumn::make('berat_hasil')
                    ->label(__('Products Out'))
                    ->state(fn (Boning $record): float => $record->outputWeight())
                    ->numeric(2)
                    ->alignRight()
                    ->suffix(' Kg'),

                Tables\Columns\TextColumn::make('susut')
                    ->label(__('Susut'))
                    ->state(function (Boning $record): string {
                        $persen = $record->shrinkPercent();

                        if ($persen === null) {
                            return '-';
                        }

                        return number_format($record->shrinkWeight(), 2, ',', '.')
                            .' Kg ('.number_format($persen, 2, ',', '.').'%)';
                    })
                    ->alignRight()
                    ->badge()
                    ->color(fn (Boning $record): string => $record->isWithinShrinkLimit() ? 'gray' : 'danger')
                    ->description(fn (Boning $record): ?string => $record->shrinkLimitWasOverridden()
                        ? __('Approved by :name', ['name' => $record->yieldOverriddenBy?->name ?? '-'])
                        : null),

                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Created By'))
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(50)
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('boning_date')
                    ->form([
                        Forms\Components\DatePicker::make('boning_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('boning_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        $from = $data['boning_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['boning_until'] ?? now()->toDateString();

                        return $query
                            ->when($from, fn($q, $date) => $q->whereDate('boning_date', '>=', $date))
                            ->when($until, fn($q, $date) => $q->whereDate('boning_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['boning_from'] ?? null) {
                            $indicators[] = __('From Date') . ': ' . \Carbon\Carbon::parse($data['boning_from'])->format('d M Y');
                        }
                        if ($data['boning_until'] ?? null) {
                            $indicators[] = __('Until Date') . ': ' . \Carbon\Carbon::parse($data['boning_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->recordUrl(
                fn (Boning $record): ?string => static::getUrl('view', ['record' => $record->id])
            )
            ->actions([
                /* 1. Tombol Lock */
                Tables\Actions\Action::make('lock')
                    // Ikon dan warna menggambarkan KEADAAN, bukan aksinya:
                    // hijau-terbuka berarti dokumen ini masih bisa diubah,
                    // merah-tertutup berarti sudah terkunci. Sama seperti
                    // GR Beef dan GR Material. Menggambarkan aksi justru
                    // menyesatkan -- gembok tertutup merah pada dokumen yang
                    // BELUM terkunci terbaca seolah dokumennya sudah dikunci.
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->iconButton()
                    ->tooltip(__('Lock Data'))
                    ->requiresConfirmation()
                    ->modalHeading(__('Lock Boning Data'))
                    ->modalDescription(__('Are you sure you want to lock this data? Once locked, you cannot modify it.'))
                    // Susut di luar batas TIDAK membuang dokumennya dan tidak
                    // menyembunyikan tombolnya. Tombolnya mati dengan
                    // keterangan, supaya yang mengerjakan tahu ia sedang
                    // menunggu siapa. Bentuknya sama persis dengan Repack.
                    ->disabled(fn (Boning $record): bool => ! $record->isWithinShrinkLimit()
                        && ! (auth()->user()?->hasPermission('override_boning_yield') ?? false))
                    ->form(fn (Boning $record): array => $record->isWithinShrinkLimit() ? [] : [
                        Forms\Components\Placeholder::make('ringkasan')
                            ->label(__('Shrinkage'))
                            ->content(fn (): string => number_format($record->shrinkWeight(), 2, ',', '.')
                                .' Kg ('.number_format((float) $record->shrinkPercent(), 2, ',', '.').'%) '
                                .__('of the :limit% limit', [
                                    'limit' => number_format((float) Boning::shrinkLimitPercent(), 2, ',', '.'),
                                ])),

                        Forms\Components\Textarea::make('yield_override_reason')
                            ->label(__('Reason for approving beyond the limit'))
                            ->required()
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->action(function (Boning $record, array $data) {
                        try {
                            $record->lock($data['yield_override_reason'] ?? null);
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('Data locked successfully'))
                            ->success()
                            ->send();

                        return redirect(static::getUrl('index'));
                    })
                    ->hidden(fn(Boning $record) => !auth()->user()->hasPermission('lock_bonings') || $record->kunci || !$record->materialUsages()->exists()),

                /* 2. Tombol Unlock */
                Tables\Actions\Action::make('unlock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip(__('Unlock Data'))
                    ->requiresConfirmation()
                    ->modalHeading(__('Unlock Boning Data'))
                    ->modalDescription(__('Are you sure you want to unlock this data? It will become editable again.'))
                    ->action(function (Boning $record) {
                        $record->unlock();
                        Notification::make()
                            ->title(__('Data unlocked successfully'))
                            ->success()
                            ->send();

                        return redirect(static::getUrl('index'));
                    })
                    ->hidden(fn(Boning $record) => !auth()->user()->hasPermission('lock_bonings') || !$record->kunci),

                /* 3. Tombol Input Material Usage */
                Tables\Actions\Action::make('materialUsage')
                    ->icon('heroicon-o-square-3-stack-3d')
                    ->color('info')
                    ->iconButton()
                    ->tooltip(__('Input Material Usage'))
                    ->url(fn(Boning $record): string => static::getUrl('material-usage', ['record' => $record->id]))
                    ->hidden(fn(Boning $record) => $record->kunci),

                /* 4. Tombol Custom Labeling (Detail Label/Buat Label) */
                Tables\Actions\Action::make('labeling')
                    ->icon('heroicon-o-qr-code')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip(fn(Boning $record) => $record->kunci ? __('Detail Label') : __('Buat Label'))
                    ->url(fn(Boning $record): string => static::getUrl('labeling', ['record' => $record])),

                /* 5. Tombol Hapus */
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Delete Data'))
                    ->hidden(fn(Boning $record) => $record->kunci || $record->items()->exists()),
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
            'index' => Pages\ListBonings::route('/'),
            'create' => Pages\CreateBoning::route('/create'),
            'view' => Pages\ViewBoning::route('/{record}'),
            'edit' => Pages\EditBoning::route('/{record}/edit'),
            'labeling' => Pages\LabelingBoning::route('/{record}/labeling'),
            'material-usage' => Pages\MaterialUsageBoning::route('/{record}/material-usage'),
        ];
    }
}
