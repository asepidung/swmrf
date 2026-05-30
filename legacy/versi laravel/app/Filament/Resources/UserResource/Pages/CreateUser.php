<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public array $tempPermissions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $permissionsToSync = [];

        foreach ($data as $key => $value) {
            if (Str::startsWith($key, 'custom_permissions_')) {
                if (is_array($value)) {
                    $permissionsToSync = array_merge($permissionsToSync, $value);
                }
                unset($data[$key]);
            }
        }

        $this->tempPermissions = $permissionsToSync;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncPermissions($this->tempPermissions);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
