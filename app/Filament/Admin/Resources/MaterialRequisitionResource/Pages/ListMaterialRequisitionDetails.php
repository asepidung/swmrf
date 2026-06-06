<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use App\Models\MaterialRequisitionItem;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;

class ListMaterialRequisitionDetails extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MaterialRequisitionResource::class;

    protected static string $view = 'filament.admin.resources.product-requisition-resource.pages.list-product-requisition-details'; // we can reuse the same empty view

    protected static ?string $title = 'Detail Request Material List';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MaterialRequisitionItem::query()
                    ->with(['requisition.supplier', 'requisition.user', 'material'])
                    ->whereHas('requisition')
            )
            ->defaultSort('material_requisition_id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('requisition.created_at')
                    ->label(__('Request Date'))
                    ->date('d-M-y')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('requisition.document_number')
                    ->label(__('No. Request'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('requisition.supplier.name')
                    ->label(__('Supplier'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Item Name'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label(__('Qty'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Price (Rp)'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('requisition.status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Request' => 'warning',
                        'Waiting' => 'info',
                        'Ordering' => 'primary',
                        'PO Created' => 'success',
                        default => 'secondary',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('requisition.user.name')
                    ->label(__('User'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('note')
                    ->label(__('Notes'))
                    ->limit(50),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label(__('Periode Awal')),
                        DatePicker::make('created_until')->label(__('Periode Akhir')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['created_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['created_until'] ?? now()->toDateString();

                        return $query->whereHas('requisition', function ($q) use ($from, $until) {
                            $q->whereDate('created_at', '>=', $from)
                              ->whereDate('created_at', '<=', $until);
                        });
                    }),
                SelectFilter::make('supplier')
                    ->label(__('Supplier'))
                    ->relationship('requisition.supplier', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->get();
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.material-requisition-details-pdf', ['records' => $records]);
                        return response()->streamDownload(fn () => print($pdf->output()), 'detail-request-material.pdf');
                    }),
                Tables\Actions\ExportAction::make('excel')
                    ->label('Excel')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->exporter(\App\Filament\Exports\MaterialRequisitionItemExporter::class)
                    ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('pdf_bulk')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.material-requisition-details-pdf', ['records' => $records]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'detail-request-material.pdf');
                        }),
                    Tables\Actions\ExportBulkAction::make('excel_bulk')
                        ->label('Excel')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->exporter(\App\Filament\Exports\MaterialRequisitionItemExporter::class)
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                ]),
            ]);
    }
}
