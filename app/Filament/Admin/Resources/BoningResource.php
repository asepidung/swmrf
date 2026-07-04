<?php

namespace App\Filament\Admin\Resources;

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
                                $prefix = 'BN' . date('y');
                                $count = Boning::withTrashed()->whereYear('created_at', $currentYear)->count();
                                $sequence = $count + 1;
                                return $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
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

                Forms\Components\Section::make(__('Material Usage (Bahan Penolong)'))
                    ->schema([
                        Forms\Components\Repeater::make('materialUsages')
                            ->relationship('materialUsages')
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->label(__('Material'))
                                    ->options(Material::where('is_active', true)->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                
                                Forms\Components\TextInput::make('qty')
                                    ->label(__('Qty'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01),

                                Forms\Components\TextInput::make('note')
                                    ->label(__('Note'))
                                    ->maxLength(255),
                            ])
                            ->columns(3)
                            ->addActionLabel(__('Add Material Usage'))
                            ->defaultItems(0)
                    ])
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
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['boning_from'])->format('d M Y');
                        }
                        if ($data['boning_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['boning_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->recordUrl(
                fn (Boning $record): ?string => $record->kunci == 1 ? null : static::getUrl('edit', ['record' => $record->id])
            )
            ->actions([
                /* 1. Tombol Lock */
                Tables\Actions\Action::make('toggleLock')
                    ->label(fn(Boning $record) => $record->kunci ? __('Unlock') : __('Lock'))
                    ->icon(fn(Boning $record) => $record->kunci ? 'heroicon-m-lock-closed' : 'heroicon-m-lock-open')
                    ->color(fn(Boning $record) => $record->kunci ? 'danger' : 'success')
                    ->iconButton()
                    ->tooltip(fn(Boning $record) => $record->kunci ? __('Unlock Data') : __('Lock Data'))
                    ->requiresConfirmation()
                    ->visible(function () {
                        /** @var \App\Models\User $user */
                        $user = Auth::user();
                        return $user?->hasPermission('lock_bonings');
                    })
                    ->action(function (Boning $record) {
                        $record->update(['kunci' => ! $record->kunci]);
                        Notification::make()
                            ->title($record->kunci ? __('Batch Locked Successfully') : __('Batch Unlocked Successfully'))
                            ->success()
                            ->send();
                    }),

                /* 2. Tombol Custom Labeling (Scan) */
                Tables\Actions\Action::make('labeling')
                    ->label(__('Scan'))
                    ->icon('heroicon-o-qr-code')
                    ->color('warning')
                    ->url(fn(Boning $record): string => static::getUrl('labeling', ['record' => $record]))
                    ->hidden(fn(Boning $record) => $record->kunci == 1),

                /* 3. Tombol Hapus */
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Delete Data'))
                    ->hidden(fn(Boning $record) => $record->kunci == 1 || $record->items()->exists()),
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
            'edit' => Pages\EditBoning::route('/{record}/edit'),
            'labeling' => Pages\LabelingBoning::route('/{record}/labeling'),
        ];
    }
}
