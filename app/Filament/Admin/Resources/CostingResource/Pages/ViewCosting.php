<?php

namespace App\Filament\Admin\Resources\CostingResource\Pages;

use App\Filament\Admin\Resources\CostingResource;
use App\Models\Costing;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Halaman final untuk costing `Locked` -- read-only kecuali tombol
 * "Unlock" (di balik permission `lock_costings`, sama seperti "Lock").
 */
class ViewCosting extends ViewRecord
{
    protected static string $resource = CostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back to List'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),

            Actions\Action::make('unlock')
                ->label(__('Unlock'))
                ->icon('heroicon-o-lock-open')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === Costing::STATUS_LOCKED
                    && (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('lock_costings') ?? false)))
                ->action(function () {
                    if (! (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('lock_costings') ?? false))) {
                        Notification::make()->title(__('You do not have permission to do this.'))->danger()->send();

                        return;
                    }

                    try {
                        $this->getRecord()->unlock();
                    } catch (\RuntimeException $e) {
                        report($e);
                        Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('Costing unlocked.'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->getRecord()]));
                }),
        ];
    }
}
