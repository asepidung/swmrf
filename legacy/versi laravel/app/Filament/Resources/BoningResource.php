<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BoningResource\Pages;
use App\Models\Boning;
use App\Models\Carcass;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class BoningResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Boning::class;

    protected static ?string $navigationIcon = 'heroicon-o-scissors';
    protected static ?string $navigationGroup = 'PRODUCTION';
    protected static ?string $navigationLabel = 'Boning';

    /* 1. DAFTAR PERMISSION (Disamakan polanya dengan Beef Request agar tidak kepotong di UI) */
    public static function getPermissionPrefixes(): array
    {
        return [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'lock',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Boning Document')
                    ->schema([
                        Forms\Components\TextInput::make('doc_no')
                            ->label('Batch Number')
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
                            ->label('Boning Date')
                            ->required()
                            ->default(now()),

                        Forms\Components\Textarea::make('note')
                            ->label('Remarks / Note')
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('status')
                            ->default('OPEN'),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => Auth::id()),
                    ])->columns(2),

                Forms\Components\Section::make('Select Carcasses for Boning')
                    ->schema([
                        Forms\Components\Repeater::make('carcasses')
                            ->relationship('carcasses')
                            ->schema([
                                Forms\Components\Select::make('slaughter_id')
                                    ->label('Carcass Number')
                                    ->options(fn() => Carcass::pluck('carcass_no', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            ])
                            ->addActionLabel('Add Another Carcass')
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
                    ->label('Batch Number')
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('boning_date')
                    ->label('Boning Date')
                    ->date('d-M-Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier_names')
                    ->label('Supplier')
                    ->getStateUsing(function (Boning $record) {
                        $carcassIds = $record->carcasses->pluck('slaughter_id')->toArray();
                        if (empty($carcassIds)) return '-';
                        $suppliers = DB::table('carcasses')
                            ->whereIn('carcasses.id', $carcassIds)
                            ->join('cattle_weighings', 'carcasses.cattle_weighing_id', '=', 'cattle_weighings.id')
                            ->join('cattle_receivings', 'cattle_weighings.cattle_receiving_id', '=', 'cattle_receivings.id')
                            ->join('cattle_purchase_orders', 'cattle_receivings.cattle_purchase_order_id', '=', 'cattle_purchase_orders.id')
                            ->join('suppliers', 'cattle_purchase_orders.supplier_id', '=', 'suppliers.id')
                            ->pluck('suppliers.name');
                        return implode(', ', array_unique($suppliers->toArray()));
                    }),

                Tables\Columns\TextColumn::make('total_cattle')
                    ->label('Total Cattle')
                    ->getStateUsing(function (Boning $record) {
                        $carcassIds = $record->carcasses->pluck('slaughter_id')->toArray();
                        if (empty($carcassIds)) return 0;
                        return DB::table('carcass_items')->whereIn('carcass_id', $carcassIds)->count();
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('user.name')->label('Created By')->sortable(),
                Tables\Columns\TextColumn::make('note')->label('Note')->limit(50)->searchable(),
            ])
            ->actions([
                /* 1. Tombol Lock */
                Tables\Actions\Action::make('toggleLock')
                    ->label(fn(Boning $record) => $record->kunci ? 'Unlock' : 'Lock')
                    ->icon(fn(Boning $record) => $record->kunci ? 'heroicon-m-lock-closed' : 'heroicon-m-lock-open')
                    ->color(fn(Boning $record) => $record->kunci ? 'danger' : 'success')
                    ->iconButton()
                    ->tooltip(fn(Boning $record) => $record->kunci ? 'Buka Kunci' : 'Kunci Data')
                    ->requiresConfirmation()
                    ->visible(function () {
                        /** @var \App\Models\User $user */
                        $user = Auth::user();
                        return $user?->can('lock_boning');
                    })
                    ->action(fn(Boning $record) => $record->update(['kunci' => ! $record->kunci])),

                /* 2. Tombol Custom Labeling */
                Tables\Actions\Action::make('labeling')
                    ->icon('heroicon-o-qr-code')
                    ->iconButton()
                    ->color('warning')
                    ->tooltip('Production Labeling')
                    ->url(fn(Boning $record): string => static::getUrl('labeling', ['record' => $record]))
                    ->hidden(fn(Boning $record) => $record->kunci == 1),

                /* 3. Tombol View Summary Hasil Produksi (Sekaligus buat Export) */
                Tables\Actions\Action::make('summary_view')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->color('info')
                    ->tooltip('Lihat Hasil Produksi')
                    ->modalHeading(fn(Boning $record) => 'Hasil Produksi Boning - ' . $record->doc_no)
                    ->modalWidth('4xl')
                    ->modalSubmitActionLabel('Export to Excel') // Mengubah tulisan tombol Submit
                    ->modalCancelActionLabel('Tutup') // Mengubah tulisan tombol Cancel
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
                        /* Fungsi Export berjalan ketika tombol Export to Excel (Submit) di dalam modal diklik */
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
                    ->tooltip('Edit Boning')
                    ->hidden(fn(Boning $record) => $record->kunci == 1),

                /* 5. Tombol Hapus */
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Hapus Data')
                    ->disabled(fn(Boning $record) => $record->items()->exists())
                    ->hidden(fn(Boning $record) => $record->kunci == 1),
            ]);
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
