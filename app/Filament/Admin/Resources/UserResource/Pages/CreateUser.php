<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = bcrypt('1234');
        $data['role'] = 'employee';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Penolakan yang sama dengan `EditUser::afterSave()`: seksi izin di form
     * hanya DISEMBUNYIKAN bagi yang tidak memegang `manage_user_permissions`,
     * sedangkan `getRawState()` tetap memuat apa pun yang dikirim browser ke
     * `data.permissions_*`. Tanpa syarat di sini, pemegang `create_users`
     * saja bisa membuat akun baru berisi izin apa pun lalu masuk memakai
     * kata sandi bawaannya (audit pra-rilis 14 November 2026, A-01).
     */
    protected function afterCreate(): void
    {
        if (! auth()->user()?->hasPermission('manage_user_permissions')) {
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
