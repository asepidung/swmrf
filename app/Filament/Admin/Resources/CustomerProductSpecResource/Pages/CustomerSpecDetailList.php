<?php

namespace App\Filament\Admin\Resources\CustomerProductSpecResource\Pages;

use App\Filament\Admin\Resources\CustomerProductSpecResource;
use App\Filament\Support\MasterSelect;
use App\Models\Customer;
use App\Models\CustomerProductSpec;
use App\Models\Product;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Detail spek SEMUA customer dalam satu tabel (satu baris per customer + produk), dengan
 * saringan dan Excel, seperti halaman Detail List di Sales Order.
 */
class CustomerSpecDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CustomerProductSpecResource::class;

    protected static string $view = 'filament.admin.resources.customer-spec.pages.detail-list';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Customer Specs Detail');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(CustomerProductSpec::query()->with(['customer', 'product']))
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight_per_pack')->label(__('Weight per pack'))->searchable(),
                Tables\Columns\TextColumn::make('cut')->label(__('Cut'))->searchable(),
                Tables\Columns\TextColumn::make('condition')
                    ->label(__('Condition'))
                    ->formatStateUsing(fn (?string $state): ?string => CustomerProductSpec::conditionLabel($state)),
                Tables\Columns\TextColumn::make('packaging')->label(__('Packaging'))->searchable(),
                Tables\Columns\TextColumn::make('note')->label(__('Spec Note'))->limit(40)->toggleable(),
            ])
            ->filters([
                MasterSelect::filter(
                    Tables\Filters\SelectFilter::make('customer_id')->label(__('Customer')),
                    Customer::class,
                ),
                MasterSelect::filter(
                    Tables\Filters\SelectFilter::make('product_id')->label(__('Product')),
                    Product::class,
                ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('back')
                    ->label(__('Back'))
                    ->icon('heroicon-o-arrow-left')
                    ->color('secondary')
                    ->url(static::getResource()::getUrl('index')),
                Tables\Actions\Action::make('excel')
                    ->label(__('Excel'))
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->with(['customer', 'product'])->get()
                            ->sortBy(fn ($r) => [$r->customer?->name, $r->product?->name]);

                        return response()->streamDownload(function () use ($records) {
                            $writer = new \OpenSpout\Writer\XLSX\Writer();
                            $writer->openToFile('php://output');
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                __('Customer'), __('Product'), __('Weight per pack'), __('Cut'),
                                __('Condition'), __('Packaging'), __('Spec Note'),
                            ]));
                            foreach ($records as $record) {
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    $record->customer?->name, $record->product?->name, $record->weight_per_pack,
                                    $record->cut, CustomerProductSpec::conditionLabel($record->condition),
                                    $record->packaging, $record->note,
                                ]));
                            }
                            $writer->close();
                        }, 'customer-specs.xlsx');
                    }),
            ])
            ->defaultSort('customer_id');
    }
}
