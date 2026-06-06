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

    protected static ?string $title = 'Detail Request Beef List';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductRequisitionItem::query()
                    ->with(['productRequisition.supplier', 'productRequisition.user', 'product'])
                    ->whereHas('productRequisition', function ($query) {
                        $query->where('is_deleted', false);
                    })
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
                        DatePicker::make('created_from')->label(__('Periode Awal'))->default(now()->startOfMonth()),
                        DatePicker::make('created_until')->label(__('Periode Akhir'))->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereHas('productRequisition', fn($q) => $q->whereDate('created_at', '>=', $date)),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereHas('productRequisition', fn($q) => $q->whereDate('created_at', '<=', $date)),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Dari: ' . Carbon::parse($data['created_from'])->format('d M Y'))
                                ->removeField('created_from');
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Sampai: ' . Carbon::parse($data['created_until'])->format('d M Y'))
                                ->removeField('created_until');
                        }
                        return $indicators;
                    }),
                SelectFilter::make('supplier')
                    ->label(__('Supplier'))
                    ->relationship('productRequisition.supplier', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('user')
                    ->label(__('User'))
                    ->relationship('productRequisition.user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(\App\Filament\Exports\ProductRequisitionItemExporter::class)
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(\App\Filament\Exports\ProductRequisitionItemExporter::class)
                ]),
            ]);
    }
}
