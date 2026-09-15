<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\Permission;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        $data = $this->form->getRawState();
        $permissionIds = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'permissions_') && is_array($value)) {
                $permissionIds = array_merge($permissionIds, $value);
            }
        }

        $perubahan = $this->record->permissions()->sync($permissionIds);

        // sync() lewat tabel pivot -- LogsActivity pada model User TIDAK
        // menangkap ini sama sekali (itu bukan atribut model). Kalau
        // eskalasi izin lewat form ini pernah dipakai keliru, di sinilah
        // satu-satunya tempat yang bisa membuktikannya.
        $ditambah = $perubahan['attached'] ?? [];
        $dicabut = $perubahan['detached'] ?? [];

        if ($ditambah || $dicabut) {
            activity()
                ->performedOn($this->record)
                ->withProperties([
                    'attached' => Permission::whereIn('id', $ditambah)->pluck('name')->all(),
                    'detached' => Permission::whereIn('id', $dicabut)->pluck('name')->all(),
                ])
                ->log('permissions synced');
        }
    }
}
