<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Page; // Gunakan Page standar agar registerRoutes tersedia
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

class ForceChangePassword extends Page
{
    protected ?string $heading = 'Change Your Password!';

    protected static ?string $slug = 'force-change-password';

    protected static string $view = 'filament.pages.force-change-password';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Menggunakan layout simple untuk tampilan terpusat tanpa sidebar.
     */
    protected static string $layout = 'filament-panels::components.layout.simple';

    public ?array $data = [];

    /**
     * Method bantuan yang dibutuhkan oleh layout 'simple'.
     */
    public function hasLogo(): bool
    {
        return true;
    }
    public function getLogoUrl(): ?string
    {
        return null;
    }
    public function getLogoHeight(): string
    {
        return '3rem';
    }
    public function getBrandName(): string
    {
        return config('app.name');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * Aksi logout sebagai pintu darurat.
     */
    public function logoutAction(): Action
    {
        return Action::make('logout')
            ->label('Logout dari Akun')
            ->color('gray')
            ->action(function () {
                Auth::logout();
                return redirect()->route('filament.admin.auth.login');
            });
    }

    /**
     * Schema form ganti password.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Set New Password')
                    ->description('Silakan buat password baru untuk mengamankan akun Anda.')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->confirmed()
                            ->revealable()
                            ->label('New Password'),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->required()
                            ->revealable()
                            ->label('Confirm Password'),
                    ])
            ])
            ->statePath('data');
    }

    /**
     * Eksekusi update password.
     */
    public function save(): void
    {
        $data = $this->form->getState();

        /** @var User $user */
        $user = Auth::user();

        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        Notification::make()
            ->success()
            ->title('Password Berhasil Diperbarui')
            ->send();

        $this->redirect(route('filament.admin.pages.dashboard'));
    }
}
