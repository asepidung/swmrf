<?php

namespace App\Filament\Resources\BeefRequisitionResource\Pages;

use App\Filament\Resources\BeefRequisitionResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\BeefRequisition;

class CreateBeefRequisition extends CreateRecord
{
    protected static string $resource = BeefRequisitionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $year = date('y');
        // Ambil jumlah request tahun ini untuk jadi nomor urut
        $count = BeefRequisition::whereYear('created_at', date('Y'))->count();

        $data['document_number'] = 'BRQ#' . $year . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return $data;
    }
}
