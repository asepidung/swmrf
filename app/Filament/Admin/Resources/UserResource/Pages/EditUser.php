<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
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

    /**
     * Penolakan ini menegakkan ULANG di server apa yang sudah disembunyikan
     * di form (`UserResource::form()`) -- checkbox izin cuma kosmetik kalau
     * `sync()` di sini tetap jalan untuk siapa saja yang mengetahui nama
     * field-nya. Dua syarat yang sama persis: harus punya
     * `manage_user_permissions`, dan tidak sedang mengedit akun sendiri.
     */
    protected function afterSave(): void
    {
        $user = auth()->user();

        if (! $user?->hasPermission('manage_user_permissions')) {
            return;
        }

        if ($this->record->id === $user->id) {
            return;
        }

        $data = $this->form->getRawState();
        $permissionIds = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'permissions_') && is_array($value)) {
                $permissionIds = array_merge($permissionIds, $value);
            }
        }

        $this->record->permissions()->sync($permissionIds);
    }
}
