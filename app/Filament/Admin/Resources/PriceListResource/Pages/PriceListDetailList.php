<?php

namespace App\Filament\Admin\Resources\PriceListResource\Pages;

use App\Filament\Admin\Resources\PriceListResource;
use App\Models\PriceListItem;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class PriceListDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PriceListResource::class;

    protected static string $view = 'filament.admin.resources.price-list-resource.pages.detail-list';

    public function getTitle(): string { return __('Price List Detail'); }

    public function table(Table $table): Table
    {
        return $table
            ->query(PriceListItem::query()->with(['priceList.customerGroup', 'product']))
            ->columns([
                Tables\Columns\TextColumn::make('priceList.customerGroup.name')
                    ->label(__('Customer Group'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Price'))
                    ->money('IDR', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Last Updated'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
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
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.price-list-details-pdf', ['records' => $records])->setPaper('a4', 'portrait');
                            return response()->streamDownload(fn () => print($pdf->output()), 'Detail_Price_List_' . now()->format('Y-m-d') . '.pdf');
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
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Customer Group', 'Product', 'Price', 'Note', 'Last Updated']));
                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->priceList->customerGroup->name ?? '',
                                        $record->product->name ?? '',
                                        (string) $record->price,
                                        $record->note ?? '',
                                        $record->updated_at ? $record->updated_at->format('Y-m-d H:i') : '',
                                    ]));
                                }
                                $writer->close();
                            }, 'Detail_Price_List_' . now()->format('Y-m-d') . '.xlsx');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_group')
                    ->label(__('Customer Group'))
                    ->relationship('priceList.customerGroup', 'name'),
            ]);
    }
}
