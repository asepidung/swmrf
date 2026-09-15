<?php

namespace App\Filament\Admin\Resources\GradeResource\Pages;

use App\Filament\Admin\Resources\GradeResource;
use App\Support\MasterDataDeletion;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGrade extends EditRecord
{
    protected static string $resource = GradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(fn () => __('Back'))
                ->url(fn () => $this->getResource()::getUrl('index'))
                ->color('gray'),
            // GradeResource's DeleteBulkAction di halaman Index sudah benar
            // dibungkus MasterDataDeletion::attempt() -- tombol Delete di
            // halaman Edit ini sendiri masih polos, jadi menghapus grade
            // yang masih dipakai menampilkan galat SQL mentah.
            Actions\DeleteAction::make()
                ->action(function () {
                    $record = $this->getRecord();

                    if (MasterDataDeletion::attempt(fn () => $record->delete(), __('Grade').' '.$record->name)) {
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
