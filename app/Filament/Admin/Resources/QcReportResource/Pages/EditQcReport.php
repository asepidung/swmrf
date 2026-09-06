<?php

namespace App\Filament\Admin\Resources\QcReportResource\Pages;

use App\Filament\Admin\Resources\QcReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQcReport extends EditRecord
{
    protected static string $resource = QcReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
