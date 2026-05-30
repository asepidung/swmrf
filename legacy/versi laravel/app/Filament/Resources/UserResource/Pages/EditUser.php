<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    // Variabel penampung sementara
    public array $tempPermissions = [];

    // Filter data sebelum disimpan biar nggak error ke tabel users
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $permissionsToSync = [];

        foreach ($data as $key => $value) {
            if (Str::startsWith($key, 'custom_permissions_')) {
                if (is_array($value)) {
                    $permissionsToSync = array_merge($permissionsToSync, $value);
                }
                unset($data[$key]); // Hapus dari request bawaan
            }
        }

        $this->tempPermissions = $permissionsToSync;

        return $data;
    }

    // Tembakkan hak akses langsung ke si User setelah data disave
    protected function afterSave(): void
    {
        $this->record->syncPermissions($this->tempPermissions);
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
