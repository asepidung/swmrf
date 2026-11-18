<?php

namespace App\Filament\Admin\Resources\BoningResource\Pages;

use App\Filament\Admin\Resources\BoningResource;
use App\Models\Boning;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBoning extends EditRecord
{
    protected static string $resource = BoningResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);
        abort_if($this->getRecord()->kunci, 403, 'Data has been locked.');
    }

    /** Tab lama tidak boleh menyimpan sesudah boning dikunci atau sudah berisi label (ED-01). */
    protected function beforeSave(): void
    {
        $boning = Boning::query()->whereKey($this->getRecord()->getKey())->lockForUpdate()->first();

        if (! $boning || $boning->kunci || $boning->items()->exists()) {
            Notification::make()->title(__('This boning is locked or already has labels, so it cannot be saved.'))->danger()->send();
            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->getRecord()->kunci == 1 || $this->getRecord()->items()->exists()),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->hidden(fn () => $this->getRecord()->kunci == 1 || $this->getRecord()->items()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
