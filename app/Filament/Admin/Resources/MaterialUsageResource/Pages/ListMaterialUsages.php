<?php

namespace App\Filament\Admin\Resources\MaterialUsageResource\Pages;

use App\Filament\Admin\Resources\MaterialUsageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMaterialUsages extends ListRecords
{
    protected static string $resource = MaterialUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Create Manual Usage'))
                ->icon('heroicon-o-plus'),
        ];
    }
    
    // Apply silent default date filter
    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $this->applyColumnSearchesToTableQuery($query);

        if (filled($search = $this->getTableSearch())) {
            $query->whereIn('id', MaterialUsageResource::getGlobalSearchResultIds($search));
        }

        // Project Rule: Default date filter silently from start of month to today
        if (!$this->getTableFilterState('usage_date')['from']) {
            $query->whereDate('created_at', '>=', now()->startOfMonth());
        }
        
        if (!$this->getTableFilterState('usage_date')['until']) {
            $query->whereDate('created_at', '<=', now());
        }

        return $query;
    }
}
