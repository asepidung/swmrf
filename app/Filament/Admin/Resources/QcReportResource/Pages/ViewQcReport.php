<?php

namespace App\Filament\Admin\Resources\QcReportResource\Pages;

use App\Filament\Admin\Resources\QcReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewQcReport extends ViewRecord
{
    protected static string $resource = QcReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
