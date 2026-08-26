<?php

namespace App\Filament\Admin\Resources\ProductRequisitionResource\Pages;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use App\Models\ProductRequisitionItem;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;

class ListProductRequisitionDetails extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ProductRequisitionResource::class;

    protected static string $view = 'filament.admin.resources.product-requisition-resource.pages.list-product-requisition-details';

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return __('Detail Request Beef List');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductRequisitionItem::query()
                    ->with(['productRequisition.supplier', 'productRequisition.user', 'product'])
                    ->whereHas('productRequisition')
            )
            ->defaultSort('product_requisition_id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('productRequisition.created_at')
                    ->label(__('Request Date'))
                    ->date('d-M-y')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('productRequisition.document_number')
                    ->label(__('No. Request'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('productRequisition.supplier.name')
                    ->label(__('Supplier'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
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
                Tables\Columns\TextColumn::make('productRequisition.status')
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
                Tables\Columns\TextColumn::make('productRequisition.user.name')
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

                        return $query->whereHas('productRequisition', function ($q) use ($from, $until) {
                            $q->whereDate('created_at', '>=', $from)
                              ->whereDate('created_at', '<=', $until);
                        });
                    }),
                SelectFilter::make('supplier')
                    ->label(__('Supplier'))
                    ->relationship('productRequisition.supplier', 'name')
                    ->searchable()
                    ->preload(),
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
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.product-requisition-details-pdf', ['records' => $records]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'detail-request-beef.pdf');
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
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Request Date', 'No Request', 'Supplier', 'Item Name', 'Qty', 'Price', 'Status', 'User']));
                                foreach ($records as $record) {
                                    // Relasinya bernama productRequisition, bukan requisition.
                                    // Nama yang salah membuat seluruh export Excel gagal fatal
                                    // dengan "Attempt to read property on null" - bukan sekadar
                                    // kolom kosong. Versi PDF sejak awal sudah memakai nama ini.
                                    $requisition = $record->productRequisition;

                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $requisition?->created_at?->format('Y-m-d') ?? '',
                                        $requisition?->document_number ?? '',
                                        $requisition?->supplier?->name ?? '',
                                        $record->product?->name ?? '',
                                        (string) $record->qty,
                                        (string) $record->price,
                                        $requisition?->status ?? '',
                                        $requisition?->user?->name ?? '',
                                    ]));
                                }
                                $writer->close();
                            }, 'Detail_Beef' . '_' . now()->format('Y-m-d') . '.xlsx');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('pdf_bulk')
                        ->label(fn() => __('PDF'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.product-requisition-details-pdf', ['records' => $records]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'detail-request-beef.pdf');
                        }),
                                        \Filament\Tables\Actions\BulkAction::make('excel_bulk')
                        ->label(fn() => __('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Request Date', 'No Request', 'Supplier', 'Item Name', 'Qty', 'Price', 'Status', 'User']));
                                foreach ($records as $record) {
                                    // Relasinya bernama productRequisition, bukan requisition.
                                    // Nama yang salah membuat seluruh export Excel gagal fatal
                                    // dengan "Attempt to read property on null" - bukan sekadar
                                    // kolom kosong. Versi PDF sejak awal sudah memakai nama ini.
                                    $requisition = $record->productRequisition;

                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $requisition?->created_at?->format('Y-m-d') ?? '',
                                        $requisition?->document_number ?? '',
                                        $requisition?->supplier?->name ?? '',
                                        $record->product?->name ?? '',
                                        (string) $record->qty,
                                        (string) $record->price,
                                        $requisition?->status ?? '',
                                        $requisition?->user?->name ?? '',
                                    ]));
                                }
                                $writer->close();
                            }, 'Detail_Beef' . '_' . now()->format('Y-m-d') . '.xlsx');
                        }),
                ]),
            ]);
    }
}
