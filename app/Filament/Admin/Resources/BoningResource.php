<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BoningResource\Pages;
use App\Models\Boning;
use App\Models\Carcass;
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
                                    ->options(function (?Boning $record) {
                                        $query = Carcass::query();
                                        
                                        // 1. Saring karkas yang berada di batch boning yang sudah dikunci
                                        $query->whereDoesntHave('boningCarcasses.boning', function ($q) use ($record) {
                                            $q->where('kunci', true);
                                            if ($record) {
                                                $q->where('id', '!=', $record->id);
                                            }
                                        });

                                        // 2. Batasi hanya karkas dalam 3 hari terakhir (mengakomodasi akhir pekan) agar dropdown bersih
                                        $threeDaysAgo = now()->subDays(3)->startOfDay();
                                        $query->where(function ($q) use ($threeDaysAgo, $record) {
                                            $q->where('kill_date', '>=', $threeDaysAgo);
                                            if ($record) {
                                                $currentCarcassIds = $record->carcasses()->pluck('carcass_id')->toArray();
                                                if (!empty($currentCarcassIds)) {
                                                    $q->orWhereIn('id', $currentCarcassIds);
                                                }
                                            }
                                        });

                                        // 3. Selalu sertakan karkas yang saat ini sedang dipilih pada batch ini (untuk edit mode)
                                        if ($record) {
                                            $currentCarcassIds = $record->carcasses()->pluck('carcass_id')->toArray();
                                            if (!empty($currentCarcassIds)) {
                                                $query->orWhereIn('id', $currentCarcassIds);
                                            }
                                        }

                                        return $query->pluck('carcass_number', 'id');
                                    })
                                    ->required()
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            ])
                            ->addActionLabel(__('Add Another Carcass'))
                            ->columns(1)
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

                /* 2. Tombol Custom Labeling */
                Tables\Actions\Action::make('labeling')
                    ->icon('heroicon-o-qr-code')
                    ->iconButton()
                    ->color('warning')
                    ->tooltip(__('Production Labeling'))
                    ->url(fn(Boning $record): string => static::getUrl('labeling', ['record' => $record]))
                    ->hidden(fn(Boning $record) => $record->kunci == 1),

                /* 3. Tombol View Summary Hasil Produksi (Sekaligus buat Export) */
                Tables\Actions\Action::make('summary_view')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->color('info')
                    ->tooltip(__('View Production Results'))
                    ->modalHeading(fn(Boning $record) => __('Production Summary') . ' - ' . $record->doc_no)
                    ->modalWidth('4xl')
                    ->modalSubmitActionLabel(__('Export to Excel'))
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(function (Boning $record) {
                        $summary = \App\Models\BoningItem::with('product')
                            ->where('boning_id', $record->id)
                            ->get()
                            ->groupBy('product_id')
                            ->map(function ($items) {
                                return [
                                    'product_name' => $items->first()->product->name ?? 'Unknown',
                                    'box' => $items->count(),
                                    'pcs' => $items->sum('qty_pcs'),
                                    'qty' => $items->sum('weight'),
                                ];
                            })->sortBy('product_name');

                        return view('filament.resources.boning-resource.pages.view-summary', [
                            'summary' => $summary,
                        ]);
                    })
                    ->action(function (Boning $record) {
                        $summary = \App\Models\BoningItem::with('product')
                            ->where('boning_id', $record->id)
                            ->get()
                            ->groupBy('product_id')
                            ->map(function ($items) {
                                return [
                                    'product_name' => $items->first()->product->name ?? 'Unknown',
                                    'box' => $items->count(),
                                    'pcs' => $items->sum('qty_pcs'),
                                    'qty' => $items->sum('weight'),
                                ];
                            })->sortBy('product_name');

                        $csvData = "Product,Box,Pcs,Qty (Kg)\n";
                        $totalBox = 0;
                        $totalPcs = 0;
                        $totalQty = 0;

                        foreach ($summary as $row) {
                            $csvData .= "\"{$row['product_name']}\",{$row['box']},{$row['pcs']},{$row['qty']}\n";
                            $totalBox += $row['box'];
                            $totalPcs += $row['pcs'];
                            $totalQty += $row['qty'];
                        }

                        $csvData .= "\"GRAND TOTAL\",{$totalBox},{$totalPcs},{$totalQty}\n";

                        return response()->streamDownload(function () use ($csvData) {
                            echo $csvData;
                        }, 'Hasil_Produksi_' . $record->doc_no . '.csv');
                    }),

                /* 4. Tombol Edit Header */
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->color('success')
                    ->tooltip(__('Edit Boning'))
                    ->hidden(fn(Boning $record) => $record->kunci == 1),

                /* 5. Tombol Hapus */
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Delete Data'))
                    ->disabled(fn(Boning $record) => $record->items()->exists())
                    ->hidden(fn(Boning $record) => $record->kunci == 1),
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
