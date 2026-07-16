<?php

namespace App\Filament\Admin\Resources\MaterialUsageResource\Pages;

use App\Filament\Admin\Resources\MaterialUsageResource;
use App\Models\MaterialUsage;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;

class MaterialUsageDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MaterialUsageResource::class;

    protected static string $view = 'filament.admin.resources.material-usage-resource.pages.detail-list';

    public function getTitle(): string { return __('Material Usage Detail'); }

    public function table(Table $table): Table
    {
        return $table
            ->query(MaterialUsage::query()->with(['material.unit', 'usageable']))
            ->columns([
                Tables\Columns\TextColumn::make('usageable_type')
                    ->label(__('Reference Document'))
                    ->formatStateUsing(function (MaterialUsage $record) {
                        if (!$record->usageable) {
                            return __('-');
                        }
                        $type = class_basename($record->usageable_type);
                        $docNo = $record->usageable->doc_no ?? $record->usageable_id;
                        return $type . ' (' . $docNo . ')';
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Usage Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Material'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label(__('Quantity'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.unit.name')
                    ->label(__('Unit'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->searchable(),
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('back')
                    ->label(__('Back'))
                    ->icon('heroicon-o-arrow-left')
                    ->color('secondary')
                    ->url(static::getResource()::getUrl('index')),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('pdf')
                        ->label(fn() => __('PDF'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.material-usage-details-pdf', ['records' => $records])->setPaper('a4', 'landscape');
                            return response()->streamDownload(fn () => print($pdf->output()), 'Detail_Material_Usage_' . now()->format('Y-m-d') . '.pdf');
                        }),
                    \Filament\Tables\Actions\Action::make('excel')
                        ->label(fn() => __('Excel'))
                        ->color('success')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Reference Document', 'Usage Date', 'Material', 'Quantity', 'Unit', 'Note']));
                                foreach ($records as $record) {
                                    $type = $record->usageable ? class_basename($record->usageable_type) : '-';
                                    $docNo = $record->usageable->doc_no ?? $record->usageable_id ?? '';
                                    $refDoc = $record->usageable ? $type . ' (' . $docNo . ')' : '-';
                                    
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $refDoc,
                                        $record->created_at ? $record->created_at->format('Y-m-d') : '',
                                        $record->material->name ?? '',
                                        (string) $record->qty,
                                        $record->material->unit->name ?? '',
                                        $record->note ?? '',
                                    ]));
                                }
                                $writer->close();
                            }, 'Detail_Material_Usage_' . now()->format('Y-m-d') . '.xlsx');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->filters([
                //
            ]);
    }
}
