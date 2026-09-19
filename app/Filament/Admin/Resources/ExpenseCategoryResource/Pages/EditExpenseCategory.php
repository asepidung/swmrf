<?php

namespace App\Filament\Admin\Resources\ExpenseCategoryResource\Pages;

use App\Filament\Admin\Resources\ExpenseCategoryResource;
use App\Support\MasterDataDeletion;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpenseCategory extends EditRecord
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->url(fn () => $this->getResource()::getUrl('index'))
                ->color('gray'),
            // Sebelumnya DeleteAction polos -- galat mentah kalau
            // kategorinya masih dipakai expense. Sama seperti EditCattleClass.
            Actions\DeleteAction::make()
                ->action(function () {
                    if (MasterDataDeletion::attempt(
                        fn () => $this->record->delete(),
                        __('Expense Category').' '.$this->record->name,
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
