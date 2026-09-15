<?php

namespace App\Filament\Clusters\ProductsCluster\Resources\ProductCategoryResource\Pages;

use App\Filament\Clusters\ProductsCluster\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

        protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(fn() => __('Back'))
                ->url(fn () => $this->getResource()::getUrl('index'))
                ->color('gray'),
            // Sebelumnya DeleteAction polos -- galat SQL mentah kalau
            // kategorinya masih dipakai. Sama seperti EditWarehouse.
            Actions\DeleteAction::make()
                ->action(function () {
                    if (\App\Support\MasterDataDeletion::attempt(
                        fn () => $this->record->delete(),
                        __('Product Category').' '.$this->record->name,
                    )) {
                        $this->redirect($this->getResource()::getUrl('index'));
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }
}
