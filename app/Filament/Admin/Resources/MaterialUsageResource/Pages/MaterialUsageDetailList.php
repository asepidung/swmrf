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
            ->filters([
                //
            ]);
    }
}
