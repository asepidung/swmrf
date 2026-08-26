<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use App\Models\User;

class ReviewMaterialRequisition extends EditRecord
{
    protected static string $resource = MaterialRequisitionResource::class;
    
    public function getTitle(): string
    {
        return 'Review Request: ' . $this->record->document_number;
    }

    public array $itemsData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->mapWithKeys(function ($item) {
            return [(string) \Illuminate\Support\Str::uuid() => [
                'material_id' => $item->material_id,
                'qty' => (float) $item->qty,
                'price' => (float) $item->price,
                'item_total' => (float) ($item->qty * $item->price),
                'note' => $item->note,
            ]];
        })->toArray();
        return $data;
    }

    protected function beforeValidate(): void
    {
        $items = $this->data['items'] ?? [];
        foreach ($items as $key => $item) {
            if (empty($item['material_id'])) {
                unset($items[$key]);
            }
        }
        $this->data['items'] = $items;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->itemsData = $data['items'] ?? [];
        unset($data['items']);
        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->items()->delete();
        foreach ($this->itemsData as $item) {
            if (!empty($item['material_id'])) {
                $this->record->items()->create([
                    'material_id' => $item['material_id'],
                    'qty' => $item['qty'] ?? 0,
                    'price' => $item['price'] ?? 0,
                    'subtotal' => ($item['qty'] ?? 0) * ($item['price'] ?? 0),
                    'note' => $item['note'] ?? null,
                ]);
            }
        }
        $this->record->updateTotalAmount();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getApproveAction(),
            $this->getRejectAction(),
        ];
    }

    protected function getApproveAction(): Actions\Action
    {
        return Actions\Action::make('approve')
            ->label('Approve (Send to Finance)')
            ->color('success')
            ->icon('heroicon-s-check-circle')
            ->requiresConfirmation()
            ->action(function () {
                // Argumen KEDUA mematikan toast "Saved" bawaan Filament, supaya
                // pengguna tidak melihat dua toast sekaligus.
                $this->save(false, false);

                $this->record->update([
                    'status' => 'Pending Finance',
                    'reject_note' => null,
                ]);

                \App\Support\TaskNotifier::notifyPermissionHolders(
                    'approve_material_requisitions',
                    __('Material Request Awaiting Approval'),
                    __('Waiting for your approval.'),
                    \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('approve-finance', ['record' => $this->record]),
                    'material-request-' . $this->record->id,
                    auth()->id(),
                );

                \Filament\Notifications\Notification::make()
                    ->title(__('Approved successfully'))
                    ->success()
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));
            });
    }

    protected function getRejectAction(): Actions\Action
    {
        return Actions\Action::make('reject')
            ->label('Reject / Return')
            ->color('danger')
            ->icon('heroicon-s-x-circle')
            ->requiresConfirmation()
            ->form([
                Textarea::make('reject_note')
                    ->label('Alasan Penolakan/Revisi')
                    ->required(),
            ])
            ->action(function (array $data) {
                $this->record->update([
                    'status' => 'Rejected',
                    'reject_note' => $data['reject_note'],
                ]);

                // Dikirim ke PEMOHON, bukan ke peran lain: dialah yang harus
                // memperbaiki atau mengajukan ulang.
                if ($this->record->user) {
                    \App\Support\TaskNotifier::notifyUser(
                        $this->record->user,
                        __('Material Request Rejected'),
                        __('Your request was rejected by purchasing.'),
                        \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('view', ['record' => $this->record]),
                        'material-request-' . $this->record->id,
                    );
                }

                \Filament\Notifications\Notification::make()
                    ->title(__('Rejected successfully'))
                    ->success()
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));
            });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('Back')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
