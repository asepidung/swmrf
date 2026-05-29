<?php

namespace App\Filament\Clusters\ProductsCluster\Resources\ProductResource\Pages;

use App\Filament\Clusters\ProductsCluster\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
