<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\MaterialRequisition;

class MaterialRequestStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $unreviewedCount = MaterialRequisition::where('status', 'Requested')->count();

        return [
            Stat::make('Pending Material Requests', $unreviewedCount)
                ->description('Material requests waiting for review')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color($unreviewedCount > 0 ? 'warning' : 'success')
                ->url(route('filament.admin.resources.material-requisitions.index', ['tableFilters[status][value]' => 'Requested'])),
        ];
    }
}
