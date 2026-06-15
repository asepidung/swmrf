<?php

namespace App\Filament\Admin\Resources\DeliveryOrderResource\Pages;

use App\Filament\Admin\Resources\DeliveryOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Tally;
use Illuminate\Support\Facades\DB;

class CreateDeliveryOrder extends CreateRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount(): void
    {
        $tallyId = request()->query('tally_id');

        if (!$tallyId || !Tally::where('id', $tallyId)->exists()) {
            $this->redirect($this->getResource()::getUrl('draft'));
            return;
        }

        parent::mount();
    }

    protected function getCreateAnotherFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }
}
