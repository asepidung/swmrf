<?php

namespace App\Filament\Clusters\ProductsCluster\Resources\ProductCategoryResource\Pages;

use App\Filament\Clusters\ProductsCluster\Resources\ProductCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
