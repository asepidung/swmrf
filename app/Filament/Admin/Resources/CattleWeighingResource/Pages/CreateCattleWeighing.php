<?php

namespace App\Filament\Admin\Resources\CattleWeighingResource\Pages;

use App\Filament\Admin\Resources\CattleWeighingResource;
use App\Models\CattleReceiving;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateCattleWeighing extends CreateRecord
{
    protected static string $resource = CattleWeighingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $record->calculateAndSaveFinancialLoss();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}
