<?php

namespace App\Filament\Admin\Resources\CarcassResource\Pages;

use App\Filament\Admin\Resources\CarcassResource;
use App\Models\CattleWeighing;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class DraftCarcass extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CarcassResource::class;

    protected static string $view = 'filament.admin.resources.carcass-resource.pages.draft-carcass';

    public function getTitle(): string { return __('Draft Carcass (Weighings)'); }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Ambil Weighing yang punya setidaknya 1 item yang belum ada di carcass_items
                CattleWeighing::query()
                    ->whereHas('items', function (Builder $query) {
                        $query->whereDoesntHave('carcassItems');
                    })
            )
            ->columns([
                TextColumn::make('weighing_number')
                    ->label(__('Weighing Number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('weighing_date')
                    ->label(__('Weighing Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('po_number')
                    ->label(__('PO Number'))
                    ->searchable(),
                TextColumn::make('supplier_name')
                    ->label(__('Supplier')),
                TextColumn::make('items_count')
                    ->label(__('Total cattle'))
                    ->counts('items'),
                TextColumn::make('unprocessed_count')
                    ->label(__('Not slaughtered yet'))
                    ->getStateUsing(function (CattleWeighing $record) {
                        return $record->items()->whereDoesntHave('carcassItems')->count();
                    })
                    ->badge()
                    ->color('warning'),
            ])
            ->actions([
                Action::make('tarik_data')
                    ->label(__('Tarik Data'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->url(fn (CattleWeighing $record): string => CarcassResource::getUrl('create', ['weighing_id' => $record->id])),
            ]);
    }
}
