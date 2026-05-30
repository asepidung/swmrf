<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

class EditProfile extends BaseEditProfile
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                /* Menampilkan form input untuk nama */
                $this->getNameFormComponent(),

                /* Menampilkan form input untuk username */
                TextInput::make('username')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                /* Menampilkan form input untuk email */
                $this->getEmailFormComponent(),

                /* Menampilkan form input untuk password beserta teks instruksi tambahan */
                $this->getPasswordFormComponent()
                    ->helperText('Biarkan kosong jika tidak ingin mengganti password.'),

                /* Menampilkan form input untuk konfirmasi password */
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    /* Mengatur URL pengalihan setelah profil berhasil diperbarui */
    protected function getRedirectUrl(): ?string
    {
        return filament()->getUrl();
    }
}
