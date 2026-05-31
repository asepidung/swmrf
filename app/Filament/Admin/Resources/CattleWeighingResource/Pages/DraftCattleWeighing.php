<?php

namespace App\Filament\Admin\Resources\CattleWeighingResource\Pages;

use App\Filament\Admin\Resources\CattleWeighingResource;
use App\Models\CattleReceiving;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;

class DraftCattleWeighing extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CattleWeighingResource::class;

    protected static string $view = 'filament.admin.resources.cattle-weighing-resource.pages.draft-cattle-weighing';

    protected static ?string $title = 'Draft Cattle Weighing';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CattleReceiving::query()
                    ->whereDoesntHave('weighing')
                    ->latest('receive_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('receive_date')
                    ->label('Receive Date')
                    ->date()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('receiving_number')
                    ->label('Receive Number')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('purchaseCattle.document_number')
                    ->label('PO Number')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Heads')
                    ->formatStateUsing(fn ($state) => $state . ' Heads'),
            ])
            ->actions([
                Tables\Actions\Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-scale')
                    ->color('success')
                    ->url(fn (CattleReceiving $record): string => CattleWeighingResource::getUrl('create', ['receiving_id' => $record->id])),
            ]);
    }
}
