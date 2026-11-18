<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private string $temporaryPassword = '';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Akun baru: kata sandi sementara ACAK (angka), ditampilkan sekali ke admin di afterCreate().
        $this->temporaryPassword = \App\Support\TemporaryPassword::generate();
        $data['password'] = bcrypt($this->temporaryPassword);
        $data['must_change_password'] = true;
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
        \Filament\Notifications\Notification::make()
            ->title(__('User created'))
            ->body(__("Temporary password for ':username': :password. Pass it on now; it is shown only once and must be changed at the next login.", ['username' => $this->record->username, 'password' => $this->temporaryPassword]))
            ->success()
            ->persistent()
            ->send();

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
