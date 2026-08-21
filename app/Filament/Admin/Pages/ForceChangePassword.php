<?php

namespace App\Filament\Admin\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ForceChangePassword extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static string $view = 'filament.admin.pages.force-change-password';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Pakai layout "simple" (tanpa sidebar) supaya user tidak tergoda mengklik
     * menu dan mengabaikan penggantian password. Topbar sengaja tetap
     * ditampilkan agar user menu (Logout) masih bisa diakses dan user tidak
     * terjebak di halaman ini.
     *
     * Kelas ini tetap meng-extend Page (bukan SimplePage) karena panel
     * memakai discoverPages(), yang hanya mengenali turunan Page. Meng-extend
     * SimplePage akan membuat rute halaman ini hilang dan middleware
     * CheckPasswordChange mengarah ke rute yang tidak ada.
     */
    protected static string $layout = 'filament-panels::components.layout.simple';

    public ?array $data = [];

    /**
     * @return array<string, mixed>
     */
    protected function getLayoutData(): array
    {
        return [
            'hasTopbar' => true,
            'maxWidth' => MaxWidth::TwoExtraLarge,
        ];
    }

    /**
     * Dibutuhkan oleh komponen <x-filament-panels::page.simple>.
     */
    public function hasLogo(): bool
    {
        return true;
    }

    public function mount(): void
    {
        // Redirect back to dashboard if user has already changed default password
        $user = Auth::user();
        if ($user && !Hash::check('1234', $user->password)) {
            $this->redirect(route('filament.admin.pages.dashboard'));
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('new_password')
                    ->label(__('New Password'))
                    ->password()
                    ->required()
                    ->minLength(8)
                    ->same('new_password_confirmation')
                    ->helperText(__('At least 8 characters.')),
                TextInput::make('new_password_confirmation')
                    ->label(__('Confirm New Password'))
                    ->password()
                    ->required(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('changePassword')
                ->label(__('Change Password'))
                ->submit('changePassword'),
        ];
    }

    public function changePassword(): void
    {
        $data = $this->form->getState();

        $user = Auth::user();
        $user->update([
            'password' => Hash::make($data['new_password']),
        ]);

        request()->session()->put([
            'password_hash_web' => $user->getAuthPassword(),
        ]);

        Notification::make()
            ->title(__('Password Updated successfully'))
            ->body(__('Please continue using the application with your new password.'))
            ->success()
            ->send();

        $this->redirect(route('filament.admin.pages.dashboard'));
    }
}
