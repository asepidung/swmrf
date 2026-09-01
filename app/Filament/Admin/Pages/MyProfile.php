<?php

namespace App\Filament\Admin\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;

class MyProfile extends Page
{
    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return __('Edit Profile');
    }

    public static function getNavigationLabel(): string
    {
        return __('My Profile');
    }

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $slug = 'my-profile';
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.admin.pages.my-profile';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'name' => auth()->user()->name,
            'username' => auth()->user()->username,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Profile Information')
                    ->description('Update your account\'s profile information.')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('username')
                            ->label(__('Username'))
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'users', ignorable: auth()->user()),
                    ]),

                Section::make('Change Password')
                    ->description('Ensure your account is using a long, random password to stay secure.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->revealable()
                            ->currentPassword()
                            ->requiredWith('new_password'),
                        TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->revealable()
                            ->helperText('Biarkan kosong jika tidak ingin mengubah password.')
                            ->minLength(8)
                            ->same('new_password_confirmation'),
                        TextInput::make('new_password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->revealable()
                            ->requiredWith('new_password'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(filament()->getUrl()),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = auth()->user();
        
        $user->name = $data['name'];
        $user->username = $data['username'];

        if (! empty($data['new_password'])) {
            $user->password = Hash::make($data['new_password']);
        }

        $user->save();

        Notification::make()
            ->title('Profile updated successfully')
            ->success()
            ->send();
            
        $this->redirect(filament()->getUrl());
    }
}
