<?php

namespace App\Filament\Admin\Resources\BoningResource\Pages;

use App\Filament\Admin\Resources\BoningResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use App\Services\ProductionMaterialSummary;
use Illuminate\Support\Facades\DB;

class ViewBoning extends ViewRecord
{
    protected static string $resource = BoningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
            Actions\Action::make('print_material_usage')
                ->label(__('Print Material Usage'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('production-material.print', ['kind' => 'boning', 'id' => $this->getRecord()->getKey()]))
                ->openUrlInNewTab(),
            Actions\Action::make('export_excel')
                ->label(__('Excel'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(fn () => $this->exportExcel()),
                
            Actions\EditAction::make()
                ->label(__('Edit'))
                ->hidden(fn () => $this->getRecord()->kunci),
        ];
    }

    public function getProductionSummary()
    {
        return \App\Models\BoningItem::with('product')
            ->where('boning_id', $this->record->id)
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
    }

    /** Blok pemakaian bahan di berkas Excel: BOM, drylog, dan bahan terbuang. */
    public function materialUsageCsv(): string
    {
        $summary = ProductionMaterialSummary::for($this->getRecord());
        $q = fn (string $text): string => '"'.str_replace('"', '""', $text).'"';

        $csv = "\n".$q(__('Usage per BOM')).($summary['final'] ? '' : ','.$q(__('Not final yet. These figures are recalculated until the document is locked.')))."\n";
        $csv .= $q(__('Material')).','.$q(__('Quantity')).','.$q(__('Unit'))."\n";
        foreach ($summary['bom'] as $row) {
            $csv .= $q($row['material']).",{$row['qty']},".$q($row['unit'])."\n";
        }

        $csv .= "\n".$q(__('Drylog / Pad Absorber')).','.($summary['drylog'] ?? '')."\n";

        $csv .= "\n".$q(__('Wasted Material'))."\n";
        $csv .= $q(__('Material')).','.$q(__('Quantity')).','.$q(__('Reason')).','.$q(__('Value'))."\n";
        foreach ($summary['wastes'] as $row) {
            $csv .= $q($row['material']).",{$row['qty']},".$q($row['reason']).','.($row['amount'] ?? '')."\n";
        }
        if ($summary['final']) {
            $csv .= $q(__('Total wasted')).',,,'.$summary['waste_total']."\n";
        }

        return $csv;
    }

    public function exportExcel()
    {
        $summary = $this->getProductionSummary();

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

        $csvData .= $this->materialUsageCsv();

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, 'Hasil_Produksi_' . $this->record->doc_no . '.csv');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('Boning Document'))
                    ->schema([
                        Infolists\Components\TextEntry::make('doc_no')
                            ->label(__('Batch Number'))
                            ->weight('bold')
                            ->color('primary'),
                            
                        Infolists\Components\TextEntry::make('boning_date')
                            ->label(__('Boning Date'))
                            ->date('d M Y'),
                            
                        Infolists\Components\TextEntry::make('user.name')
                            ->label(__('Created By')),
                            
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'OPEN' => 'warning',
                                'LOCKED' => 'danger',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('carcasses_processed')
                            ->label(__('Carcasses Processed'))
                            ->state(fn ($record) => $record->carcasses->map(fn($c) => $c->carcass->carcass_number ?? '-')->toArray())
                            ->badge()
                            ->color('success')
                            ->columnSpan(['default' => 1, 'lg' => 2]),
                            
                        Infolists\Components\TextEntry::make('note')
                            ->label(__('Note'))
                            ->default('-')
                            ->columnSpan(['default' => 1, 'lg' => 2]),
                    ])->columns(4),

                Infolists\Components\Section::make(__('Production Summary'))
                    ->schema([
                        Infolists\Components\ViewEntry::make('summary')
                            ->hiddenLabel()
                            ->view('filament.resources.boning-resource.summary-table')
                    ]),

                Infolists\Components\Section::make(__('Material Usage'))
                    ->schema([
                        Infolists\Components\ViewEntry::make('material_usage')
                            ->hiddenLabel()
                            ->view('filament.partials.production-material-summary')
                            ->viewData(fn (): array => ['summary' => ProductionMaterialSummary::for($this->getRecord())]),
                    ])
            ]);
    }
}
