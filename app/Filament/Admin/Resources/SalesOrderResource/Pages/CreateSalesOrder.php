<?php

namespace App\Filament\Admin\Resources\SalesOrderResource\Pages;

use App\Filament\Admin\Resources\SalesOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected array $itemsData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->itemsData = $data['items'] ?? [];
        unset($data['items']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        foreach ($this->itemsData as $item) {
            $record->items()->create([
                'product_id' => $item['product_id'],
                'weight' => (int) str_replace('.', '', $item['weight'] ?? 0),
                'price' => (int) str_replace('.', '', $item['price'] ?? 0),
                'discount' => (int) str_replace('.', '', $item['discount'] ?? 0),
                'note' => $item['note'] ?? '',
            ]);
        }

        // Notify users who have permission to manage tallies
        $usersToNotify = \App\Models\User::where('is_active', true)->get()->filter(function ($user) {
            return $user->hasPermission('create_tallies') || $user->hasPermission('view_tallies');
        });

        if ($usersToNotify->isNotEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title(__('New Sales Order Created'))
                ->body(__('Sales Order ') . $record->so_number . __(' is ready for tally.'))
                ->success()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label(__('View Sales Order'))
                        ->url(SalesOrderResource::getUrl('index')),
                ])
                ->sendToDatabase($usersToNotify);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
