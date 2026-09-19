<?php

namespace App\Filament\Admin\Resources\SalesReturnPlanResource\Pages;

use App\Filament\Admin\Resources\SalesReturnPlanResource;
use App\Models\SalesReturnPlan;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

/**
 * Hanya untuk plan `Draft`. Idiom mount()+beforeSave() sama dengan
 * EditCattleWeighing/EditGoodsReceiptMaterial: mount() mengalihkan
 * navigasi baru, beforeSave() menolak tab yang sudah terlanjur terbuka
 * sebelum plan-nya berubah status dari sesi lain.
 */
class EditSalesReturnPlan extends EditRecord
{
    protected static string $resource = SalesReturnPlanResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        if ($this->getRecord()->status !== SalesReturnPlan::STATUS_DRAFT) {
            Notification::make()
                ->title(__('This sales return plan can no longer be edited because it is no longer a draft.'))
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    protected function beforeSave(): void
    {
        $locked = SalesReturnPlan::whereKey($this->record->id)->lockForUpdate()->first();

        if ($locked && $locked->status !== SalesReturnPlan::STATUS_DRAFT) {
            Notification::make()
                ->title(__('This sales return plan can no longer be edited because it is no longer a draft.'))
                ->warning()
                ->send();

            throw new Halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back to List'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),

            Actions\Action::make('manage_items')
                ->label(__('Manage Items'))
                ->icon('heroicon-o-list-bullet')
                ->url(fn (): string => $this->getResource()::getUrl('items', ['record' => $this->getRecord()])),

            Actions\Action::make('submit_plan')
                ->label(__('Submit Plan'))
                ->color('success')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $this->getRecord()->submit();
                    } catch (\RuntimeException $e) {
                        report($e);
                        Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('Plan submitted.'))->success()->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
