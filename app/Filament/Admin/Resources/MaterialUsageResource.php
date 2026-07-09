<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MaterialUsageResource\Pages;
use App\Models\MaterialUsageHeader;
use App\Models\MaterialUsage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Filament\Admin\Resources\BoningResource;
use App\Filament\Admin\Resources\RepackResource;
use Filament\Tables\Actions\ExportAction;
use App\Filament\Exports\MaterialUsageExporter;

class MaterialUsageResource extends Resource
{
    protected static ?string $model = MaterialUsageHeader::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    public static function getNavigationGroup(): ?string
    {
        return 'WAREHOUSE';
    }

    public static function getNavigationLabel(): string
    {
        return __('Material Usage');
    }

    public static function getModelLabel(): string
    {
        return __('Material Usage');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Material Usages');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->latest())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Usage Date'))
                    ->date('d M Y')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('usageable_type')
                    ->label(__('Reference'))
                    ->formatStateUsing(function (MaterialUsageHeader $record) {
                        if (!$record->usageable) {
                            return __('-');
                        }
                        
                        $type = class_basename($record->usageable_type);
                        $docNo = $record->usageable->doc_no ?? $record->usageable_id;
                        
                        return $type . ' (' . $docNo . ')';
                    })
                    ->badge()
                    ->color(fn (MaterialUsageHeader $record): string => match (class_basename($record->usageable_type)) {
                        'Boning' => 'info',
                        'Repack' => 'warning',
                        'MaterialAdjustment' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHasMorph('usageable', '*', function (Builder $q, $type) use ($search) {
                            $q->where('doc_no', 'like', "%{$search}%");
                        });
                    }),
                    
                Tables\Columns\TextColumn::make('material_count')
                    ->label(__('Material Count'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_qty')
                    ->label(__('Total Qty (Minus)'))
                    ->numeric(2)
                    ->color('danger')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('usage_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = __('From Date') . ': ' . Carbon::parse($data['from'])->format('d M Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = __('Until Date') . ': ' . Carbon::parse($data['until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                // We won't allow editing or deleting directly from this ledger.
                // It is view-only, except for manual usages which can be deleted if needed.
            ])
            ->recordUrl(function (MaterialUsageHeader $record) {
                return static::getUrl('view', ['record' => $record->id]);
            })
            ->bulkActions([
                //
            ])
            ->headerActions([
                // Export features
                ExportAction::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->exporter(MaterialUsageExporter::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialUsages::route('/'),
            'create' => Pages\CreateManualUsage::route('/create-manual-usage'),
            'view' => Pages\ViewMaterialUsage::route('/{record}'),
        ];
    }
}
